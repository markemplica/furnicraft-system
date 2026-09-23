<?php

namespace App\Services;

use App\Models\Recommendation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiService
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function recommendationSummary(array $preference, array $products): array
    {
        $fallback = 'These suggestions balance your budget, preferred materials and finish, room fit, and signals from customers with similar interests.';
        $prompt = "You are FurniCraft for Lance Furniture Shop. In 2 concise sentences, explain this ranked furniture recommendation. Stay strictly within the supplied data. Do not mention products outside the list, payments, delivery tracking, inventory, AR, or 3D.\nPreferences: ".json_encode($preference)."\nRanked products: ".json_encode($products);

        return $this->generate($prompt, $fallback);
    }

    public function chat(Recommendation $recommendation, string $question): array
    {
        $items = $recommendation->items->map(fn ($item) => [
            'name' => $item->product->name,
            'category' => $item->product->category,
            'wood' => $item->product->wood_type,
            'finish' => $item->product->varnish_finish,
            'style' => $item->product->style,
            'price' => $item->product->price,
            'dimensions_cm' => [$item->product->width_cm, $item->product->depth_cm, $item->product->height_cm],
            'reason' => $item->explanation,
        ])->values()->all();

        $fallback = $this->fallbackChat($items, $question);
        $prompt = "You are the FurniCraft assistant for Lance Furniture Shop. Answer only from the supplied recommendation and product facts. If the question is outside that scope, say you can only help with these recommended furniture options. Never claim stock availability, take payment, arrange delivery, or replace shop staff. Keep the answer under 130 words.\nRecommendation: ".json_encode($items)."\nCustomer question: {$question}";

        return $this->generate($prompt, $fallback);
    }

    public function analyzeRoom(string $storagePath, ?float $width, ?float $depth): array
    {
        $fallback = [
            'summary' => 'The room image is ready for a 2D placement preview. Confirm the room dimensions for the most reliable fit check.',
            'layout' => 'Keep clear walking paths and place larger pieces against an open wall.',
            'lighting' => 'Use the uploaded image as the visual reference; lighting estimates are advisory.',
            'constraints' => 'Image-only measurements are estimates and depend on image quality and perspective.',
            'estimated_width_m' => $width,
            'estimated_depth_m' => $depth,
            'dimension_confidence' => ($width && $depth) ? 'customer-provided' : 'unavailable',
            'gemini_used' => false,
        ];

        if (! $this->isConfigured() || ! Storage::disk('public')->exists($storagePath)) {
            return $fallback;
        }

        try {
            $bytes = Storage::disk('public')->get($storagePath);
            $mime = Storage::disk('public')->mimeType($storagePath) ?: 'image/jpeg';
            $text = 'Analyze this room only for furniture placement. Return JSON with keys summary, layout, lighting, constraints, estimated_width_m, estimated_depth_m, dimension_confidence. Width and depth must be numbers from 1 to 50 meters or null. Use supplied measurements when present; otherwise make a conservative visual estimate and label confidence low or medium. Be concise and state that image-based dimensions are estimates, not construction measurements. Known room width: '.($width ?: 'unknown').' m; depth: '.($depth ?: 'unknown').' m.';
            $response = $this->request([
                ['text' => $text],
                ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
            ], true);
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                foreach (['estimated_width_m', 'estimated_depth_m'] as $key) {
                    $value = isset($decoded[$key]) && is_numeric($decoded[$key]) ? (float) $decoded[$key] : null;
                    $decoded[$key] = $value && $value >= 1 && $value <= 50 ? round($value, 1) : null;
                }
                $decoded['estimated_width_m'] = $width ?: $decoded['estimated_width_m'];
                $decoded['estimated_depth_m'] = $depth ?: $decoded['estimated_depth_m'];
                $decoded['dimension_confidence'] = ($width && $depth)
                    ? 'customer-provided'
                    : ($decoded['dimension_confidence'] ?? 'low');

                return array_merge($fallback, array_intersect_key($decoded, $fallback), ['gemini_used' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini room analysis failed', ['message' => $e->getMessage()]);
        }

        return $fallback;
    }

    private function generate(string $prompt, string $fallback): array
    {
        if (! $this->isConfigured()) {
            return ['text' => $fallback, 'used' => false];
        }

        try {
            return ['text' => $this->request([['text' => $prompt]]), 'used' => true];
        } catch (\Throwable $e) {
            Log::warning('Gemini request failed', ['message' => $e->getMessage()]);

            return ['text' => $fallback, 'used' => false];
        }
    }

    private function request(array $parts, bool $json = false): string
    {
        $model = config('services.gemini.model');
        $response = Http::timeout(30)
            ->retry(2, 250)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".config('services.gemini.key'), [
                'contents' => [['parts' => $parts]],
                'generationConfig' => $json ? ['response_mime_type' => 'application/json'] : ['temperature' => 0.25],
            ])->throw()->json();

        return trim(data_get($response, 'candidates.0.content.parts.0.text', ''));
    }

    private function fallbackChat(array $items, string $question): string
    {
        $names = collect($items)->pluck('name')->implode(', ');
        $lower = strtolower($question);
        if (str_contains($lower, 'price') || str_contains($lower, 'budget')) {
            $prices = collect($items)->map(fn ($item) => $item['name'].' (PHP '.number_format((float) $item['price'], 2).')')->implode(', ');

            return "Here are the listed prices: {$prices}. A reservation only asks the shop to hold an item; it is not a payment.";
        }
        if (str_contains($lower, 'wood') || str_contains($lower, 'material') || str_contains($lower, 'finish')) {
            $materials = collect($items)->map(fn ($item) => "{$item['name']}: {$item['wood']} wood, {$item['finish']} finish")->implode('; ');

            return $materials.'.';
        }
        if (str_contains($lower, 'fit') || str_contains($lower, 'room') || str_contains($lower, 'size')) {
            return 'Use the dimensions shown on each recommendation with your saved room width and depth. The 2D room preview is a planning aid; confirm final measurements with Lance Furniture Shop before reserving.';
        }

        return "I can help compare {$names} by price, wood, finish, style, dimensions, and room suitability. Ask about one of those details or choose an item to reserve.";
    }
}
