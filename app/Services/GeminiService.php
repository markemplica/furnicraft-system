<?php

namespace App\Services;

use App\Models\FurnitureProduct;
use App\Models\Recommendation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiService
{
    public function __construct(private RoomPhotoService $roomPhotos, private AiTelemetry $telemetry) {}

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function recommendationSummary(array $preference, array $products): array
    {
        $fallback = 'These suggestions balance your budget, preferred materials and finish, room fit, and signals from customers with similar interests.';
        $prompt = "You are FurniCraft for Lance Furniture Shop. In 2 concise sentences, explain this ranked furniture recommendation. Stay strictly within the supplied data. Do not mention products outside the list, payments, delivery tracking, inventory, AR, or 3D.\nPreferences: ".json_encode($preference)."\nRanked products: ".json_encode($products);

        return $this->generate($prompt, $fallback, 'recommendation_explanation');
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

        $furnitureType = $recommendation->furniture_type ?: 'Furniture';
        $catalog = FurnitureProduct::query()->where('status', 'active')->orderBy('name')->get()
            ->filter(fn (FurnitureProduct $product) => $product->recommendationType() === $furnitureType)
            ->map(fn (FurnitureProduct $product) => [
                'name' => $product->name,
                'category' => $product->category,
                'wood' => $product->wood_type,
                'finish' => $product->varnish_finish,
                'style' => $product->style,
                'price' => $product->price,
                'dimensions_cm' => [$product->width_cm, $product->depth_cm, $product->height_cm],
            ])->values()->all();

        if ($availability = $this->catalogAvailabilityAnswer($catalog, $furnitureType, $question)) {
            return ['text' => $availability, 'used' => false, 'source' => 'catalog-rule'];
        }

        $fallback = $this->fallbackChat($items, $catalog, $furnitureType, $question);
        $prompt = "You are the FurniCraft assistant for Lance Furniture Shop. Answer only from the supplied recommendation and active catalog facts. The active catalog is the complete list of products currently available in the system for the customer's selected furniture type. If the customer asks for or requests a product, material, finish, style, size, color, or budget option, clearly begin with 'Available in our current catalog:' or 'Not available in our current catalog:' based only on an exact or close match in ACTIVE CATALOG. When unavailable, suggest the closest active options. Explain that catalog availability is not a guarantee of physical branch stock and branch stock must be confirmed by shop staff. Never invent a product or claim confirmed branch stock, take payment, arrange delivery, or replace shop staff. Keep the answer natural and under 130 words.\nSelected furniture type: {$furnitureType}\nRecommended products: ".json_encode($items)."\nActive catalog: ".json_encode($catalog)."\nCustomer question: {$question}";

        return $this->generate($prompt, $fallback, 'chat_response');
    }

    public function roomFallback(?float $width, ?float $depth, ?string $furnitureType = null): array
    {
        return [
            'summary' => 'The room image is ready for a 2D placement preview. Confirm the room dimensions for the most reliable fit check.',
            'layout' => 'Keep clear walking paths and place larger pieces against an open wall.',
            'lighting' => 'Use the uploaded image as the visual reference; lighting estimates are advisory.',
            'constraints' => 'Image-only measurements are estimates and depend on image quality and perspective.',
            'room_type' => 'Unspecified room',
            'estimated_width_m' => $width,
            'estimated_depth_m' => $depth,
            'dimension_confidence' => ($width && $depth) ? 'customer-provided' : 'unavailable',
            'analyzed_furniture_type' => $furnitureType,
            'placement_x_percent' => 50,
            'placement_y_percent' => 86,
            'placement_width_percent' => 72,
            'placement_confidence' => 'low',
            'placement_reason' => 'Centered on the visible floor area as a conservative starting point.',
            'floor_polygon' => [],
            'wall_regions' => [],
            'openings' => [],
            'obstacles' => [],
            'candidate_zones' => [],
            'horizon_y_percent' => 38,
            'vanishing_point' => ['x_percent' => 50, 'y_percent' => 38],
            'scene_confidence' => 'low',
            'gemini_used' => false,
        ];
    }

    public function analyzeRoom(string $storagePath, ?float $width, ?float $depth, ?string $furnitureType = null, bool $throwOnFailure = false): array
    {
        $fallback = $this->roomFallback($width, $depth, $furnitureType);

        if (! $this->isConfigured()) {
            $this->telemetry->record('room', 'room_analysis', 'fallback', fallbackUsed: true, failureCode: 'not_configured');
            return $fallback;
        }
        if (! $this->roomPhotos->exists($storagePath)) {
            $this->telemetry->record('room', 'room_analysis', 'failure', failureCode: 'room_photo_missing');
            return $fallback;
        }

        try {
            $bytes = $this->roomPhotos->get($storagePath);
            $mime = $this->roomPhotos->mimeType($storagePath);
            $text = 'Analyze this uploaded room photo for realistic 2D furniture placement. The customer wants this furniture type: '.($furnitureType ?: 'unspecified furniture').'. Identify the visible floor polygon, usable wall regions, door/window openings, existing furniture or obstacles, horizon, vanishing point, and up to 6 valid placement zones. All polygon points and coordinates must use percentages from 0 to 100 in the original image coordinate system. Each candidate zone needs a surface (floor, wall-floor, or opening), floor-contact anchor_x_percent and anchor_y_percent, max_width_percent, rotation_deg, perspective_deg, flip, suitable_types, clearance_score, occlusion_score, confidence, and a concise reason. For every door/opening candidate, quad_percent is required and must contain exactly four image-coordinate points ordered top-left, top-right, bottom-right, bottom-left, following the visible frame perspective even when it is tilted or trapezoidal. Never place furniture across a doorway, window, obstacle, or primary walkway. Door products require an opening. Cabinets and beds prefer wall-floor zones. Tables, chairs, and sala sets require clear floor zones; sala sets should use the main living area near a usable wall or focal direction. Also return the single best legacy placement_x_percent, placement_y_percent, and placement_width_percent. Width and depth must be 1 to 50 meters or null. Coordinates: x 5-95, y 30-96, displayed width 15-90, rotation -18 to 18, perspective -24 to 24, and scores 0-100. Use supplied room measurements when present; otherwise make conservative visual estimates. State that results are planning estimates, not construction measurements. Known room width: '.($width ?: 'unknown').' m; depth: '.($depth ?: 'unknown').' m.';
            $pointSchema = ['type' => 'array', 'minItems' => 2, 'maxItems' => 2, 'items' => ['type' => 'number']];
            $polygonSchema = ['type' => 'array', 'items' => $pointSchema];
            $regionSchema = ['type' => 'object', 'properties' => ['label' => ['type' => 'string'], 'polygon' => $polygonSchema], 'required' => ['label', 'polygon']];
            $zoneSchema = ['type' => 'object', 'properties' => [
                'surface' => ['type' => 'string'], 'anchor_x_percent' => ['type' => 'number'], 'anchor_y_percent' => ['type' => 'number'], 'max_width_percent' => ['type' => 'number'],
                'rotation_deg' => ['type' => 'number'], 'perspective_deg' => ['type' => 'number'], 'flip' => ['type' => 'boolean'], 'suitable_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                'quad_percent' => ['type' => 'array', 'minItems' => 4, 'maxItems' => 4, 'items' => $pointSchema],
                'clearance_score' => ['type' => 'number'], 'occlusion_score' => ['type' => 'number'], 'confidence' => ['type' => 'string'], 'reason' => ['type' => 'string'],
            ], 'required' => ['surface', 'anchor_x_percent', 'anchor_y_percent', 'max_width_percent', 'suitable_types', 'clearance_score', 'occlusion_score', 'confidence', 'reason']];
            $schema = [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'], 'layout' => ['type' => 'string'], 'lighting' => ['type' => 'string'], 'constraints' => ['type' => 'string'], 'room_type' => ['type' => 'string'],
                    'estimated_width_m' => ['type' => ['number', 'null']], 'estimated_depth_m' => ['type' => ['number', 'null']], 'dimension_confidence' => ['type' => 'string'],
                    'placement_x_percent' => ['type' => 'number'], 'placement_y_percent' => ['type' => 'number'], 'placement_width_percent' => ['type' => 'number'], 'placement_confidence' => ['type' => 'string'], 'placement_reason' => ['type' => 'string'],
                    'floor_polygon' => $polygonSchema,
                    'wall_regions' => ['type' => 'array', 'items' => $regionSchema],
                    'openings' => ['type' => 'array', 'items' => $regionSchema],
                    'obstacles' => ['type' => 'array', 'items' => $regionSchema],
                    'candidate_zones' => ['type' => 'array', 'maxItems' => 6, 'items' => $zoneSchema],
                    'horizon_y_percent' => ['type' => 'number'],
                    'vanishing_point' => ['type' => 'object', 'properties' => ['x_percent' => ['type' => 'number'], 'y_percent' => ['type' => 'number']], 'required' => ['x_percent', 'y_percent']],
                    'scene_confidence' => ['type' => 'string'],
                ],
                'required' => ['summary', 'layout', 'constraints', 'room_type', 'placement_x_percent', 'placement_y_percent', 'placement_width_percent', 'placement_confidence', 'placement_reason', 'candidate_zones', 'scene_confidence'],
            ];
            $response = $this->request([
                ['text' => $text],
                ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
            ], true, $schema, 'room_analysis');
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
                foreach ([
                    'placement_x_percent' => [5, 95],
                    'placement_y_percent' => [30, 96],
                    'placement_width_percent' => [15, 90],
                ] as $key => [$minimum, $maximum]) {
                    $value = isset($decoded[$key]) && is_numeric($decoded[$key]) ? (float) $decoded[$key] : $fallback[$key];
                    $decoded[$key] = round(min($maximum, max($minimum, $value)), 1);
                }
                $decoded['placement_confidence'] = in_array($decoded['placement_confidence'] ?? null, ['low', 'medium', 'high'], true)
                    ? $decoded['placement_confidence']
                    : 'low';
                foreach (['summary', 'layout', 'lighting', 'constraints', 'room_type', 'placement_reason'] as $key) {
                    $decoded[$key] = isset($decoded[$key]) && is_string($decoded[$key])
                        ? trim($decoded[$key])
                        : $fallback[$key];
                }
                $decoded['floor_polygon'] = $this->sanitizePolygon($decoded['floor_polygon'] ?? []);
                foreach (['wall_regions', 'openings', 'obstacles'] as $key) {
                    $decoded[$key] = $this->sanitizeRegions($decoded[$key] ?? []);
                }
                $decoded['candidate_zones'] = $this->sanitizeCandidateZones($decoded['candidate_zones'] ?? []);
                $decoded['horizon_y_percent'] = $this->clampPercent($decoded['horizon_y_percent'] ?? 38, 5, 85);
                $decoded['vanishing_point'] = [
                    'x_percent' => $this->clampPercent(data_get($decoded, 'vanishing_point.x_percent', 50), 0, 100),
                    'y_percent' => $this->clampPercent(data_get($decoded, 'vanishing_point.y_percent', 38), 0, 100),
                ];
                $decoded['scene_confidence'] = in_array($decoded['scene_confidence'] ?? null, ['low', 'medium', 'high'], true) ? $decoded['scene_confidence'] : 'low';
                $decoded['analyzed_furniture_type'] = $furnitureType;

                return array_merge($fallback, array_intersect_key($decoded, $fallback), ['gemini_used' => true]);
            }
            $this->telemetry->record('room', 'room_analysis', 'fallback', fallbackUsed: true, failureCode: 'invalid_response');
        } catch (\Throwable $e) {
            Log::warning('Gemini room analysis failed', $this->errorContext($e));
            if ($throwOnFailure) {
                throw $e;
            }
        }

        return $fallback;
    }

    public function furnishRoom(string $roomStoragePath, FurnitureProduct $product, bool $throwOnFailure = false): array
    {
        $fallback = ['bytes' => null, 'mime_type' => null, 'used' => false];

        if (! $this->isConfigured()) {
            $this->telemetry->record('image', 'furnished_preview', 'fallback', fallbackUsed: true, failureCode: 'not_configured');
            return $fallback;
        }
        if (! $this->roomPhotos->exists($roomStoragePath)) {
            $this->telemetry->record('image', 'furnished_preview', 'failure', failureCode: 'room_photo_missing');
            return $fallback;
        }

        $productImage = $this->productImage($product);
        if (! $productImage) {
            $this->telemetry->record('image', 'furnished_preview', 'fallback', fallbackUsed: true, failureCode: 'product_image_missing');
            return $fallback;
        }

        try {
            $roomBytes = $this->roomPhotos->get($roomStoragePath);
            $roomMime = $this->roomPhotos->mimeType($roomStoragePath);
            $prompt = 'Create one photorealistic furnished-room preview from the two supplied images. The first image is the customer room and must remain the exact same camera angle, crop, architecture, walls, windows, doors, floor, ceiling, lighting, and existing permanent fixtures. The second image is the exact selected Lance Furniture Shop product: '.$product->name.' ('.$product->category.'), approximately '.$product->width_cm.' × '.$product->depth_cm.' × '.$product->height_cm.' centimeters. Place only that exact furniture product into the most suitable clear area of the room, at physically realistic scale and perspective, with natural contact shadows and matching light. Preserve its wood color, finish, structure, cushions, and number of pieces. Do not substitute another design. Do not add rugs, plants, paintings, lamps, decorations, other furniture, people, text, logos, borders, or labels. Return only the completed room image.';
            $models = collect([
                config('services.gemini.image_model', 'gemini-3.1-flash-image'),
                config('services.gemini.image_fallback_model', 'gemini-2.5-flash-image'),
            ])->filter()->unique();
            $lastException = null;

            foreach ($models as $index => $model) {
                $started = hrtime(true);
                try {
                    $response = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                        ->timeout(120)
                        ->retry(1, 500)
                        ->post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent", [
                            'contents' => [[
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $prompt],
                                    ['inline_data' => ['mime_type' => $roomMime, 'data' => base64_encode($roomBytes)]],
                                    ['inline_data' => ['mime_type' => $productImage['mime_type'], 'data' => base64_encode($productImage['bytes'])]],
                                ],
                            ]],
                            'generationConfig' => ['responseModalities' => ['IMAGE']],
                        ]);
                    $httpStatus = $response->status();
                    $response->throw();
                    $response = $response->json();

                    $imagePart = collect(data_get($response, 'candidates.0.content.parts', []))
                        ->first(fn ($part) => filled(data_get($part, 'inlineData.data')) || filled(data_get($part, 'inline_data.data')));
                    $encoded = data_get($imagePart, 'inlineData.data') ?: data_get($imagePart, 'inline_data.data');
                    $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;

                    if (! $bytes) {
                        throw new \RuntimeException('Gemini returned no generated room image.');
                    }

                    $this->telemetry->record('image', 'furnished_preview', 'success', $model, $httpStatus, (int) ((hrtime(true) - $started) / 1_000_000), $index > 0);

                    return [
                        'bytes' => $bytes,
                        'mime_type' => data_get($imagePart, 'inlineData.mimeType') ?: data_get($imagePart, 'inline_data.mime_type') ?: 'image/png',
                        'used' => true,
                    ];
                } catch (\Throwable $e) {
                    $lastException = $e;
                    $this->telemetry->record('image', 'furnished_preview', 'failure', $model, $this->telemetry->statusCode($e), (int) ((hrtime(true) - $started) / 1_000_000), failureCode: $this->telemetry->failureCode($e));
                    Log::warning('Gemini image model attempt failed', array_merge(['model' => $model], $this->errorContext($e)));
                }
            }

            throw $lastException ?? new \RuntimeException('No Gemini image model is configured.');
        } catch (\Throwable $e) {
            if (! $throwOnFailure) {
                $this->telemetry->record('image', 'furnished_preview', 'fallback', fallbackUsed: true, failureCode: $this->telemetry->failureCode($e));
            }
            Log::warning('Gemini furnished-room generation failed', $this->errorContext($e));

            if ($throwOnFailure) throw $e;

            return $fallback;
        }
    }

    private function generate(string $prompt, string $fallback, string $operation): array
    {
        if (! $this->isConfigured()) {
            $this->telemetry->record('text', $operation, 'fallback', fallbackUsed: true, failureCode: 'not_configured');
            return ['text' => $fallback, 'used' => false, 'source' => 'local-fallback'];
        }

        try {
            return ['text' => $this->request([['text' => $prompt]], operation: $operation), 'used' => true, 'source' => 'gemini'];
        } catch (\Throwable $e) {
            $this->telemetry->record('text', $operation, 'fallback', fallbackUsed: true, failureCode: $this->telemetry->failureCode($e));
            Log::warning('Gemini request failed', $this->errorContext($e));

            return ['text' => $fallback, 'used' => false, 'source' => 'local-fallback'];
        }
    }

    private function request(array $parts, bool $json = false, ?array $schema = null, string $operation = 'text_generation'): string
    {
        $models = collect([
            config('services.gemini.model'),
            config('services.gemini.fallback_model'),
        ])->filter()->unique();
        $lastException = null;

        foreach ($models as $index => $model) {
            $started = hrtime(true);
            try {
                $response = Http::timeout(30)
                    ->retry(2, 250)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".config('services.gemini.key'), [
                        'contents' => [['parts' => $parts]],
                        'generationConfig' => $json
                            ? ['responseFormat' => ['text' => array_filter([
                                'mimeType' => 'application/json',
                                'schema' => $schema,
                            ])]]
                            : ['temperature' => 0.25],
                    ]);
                $httpStatus = $response->status();
                $response->throw();
                $response = $response->json();
                $text = trim(data_get($response, 'candidates.0.content.parts.0.text', ''));
                if ($text === '') throw new \RuntimeException('Gemini returned an empty response.');
                $this->telemetry->record('text', $operation, 'success', $model, $httpStatus, (int) ((hrtime(true) - $started) / 1_000_000), $index > 0);

                return $text;
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->telemetry->record('text', $operation, 'failure', $model, $this->telemetry->statusCode($e), (int) ((hrtime(true) - $started) / 1_000_000), failureCode: $this->telemetry->failureCode($e));
                Log::warning('Gemini model attempt failed', array_merge(['model' => $model], $this->errorContext($e)));
            }
        }

        throw $lastException ?? new \RuntimeException('No Gemini model is configured.');
    }

    private function sanitizePolygon(mixed $polygon): array
    {
        return collect(is_array($polygon) ? $polygon : [])->take(16)->map(function ($point) {
            if (! is_array($point) || count($point) < 2) {
                return null;
            }

            return [$this->clampPercent($point[0] ?? 0), $this->clampPercent($point[1] ?? 0)];
        })->filter()->values()->all();
    }

    private function sanitizeRegions(mixed $regions): array
    {
        return collect(is_array($regions) ? $regions : [])->take(12)->map(function ($region) {
            if (! is_array($region)) {
                return null;
            }

            return [
                'label' => substr(trim((string) ($region['label'] ?? 'region')), 0, 80),
                'polygon' => $this->sanitizePolygon($region['polygon'] ?? []),
            ];
        })->filter(fn ($region) => $region && count($region['polygon']) >= 3)->values()->all();
    }

    private function sanitizeCandidateZones(mixed $zones): array
    {
        return collect(is_array($zones) ? $zones : [])->take(6)->map(function ($zone) {
            if (! is_array($zone)) {
                return null;
            }

            $surface = strtolower((string) ($zone['surface'] ?? 'floor'));

            return [
                'surface' => in_array($surface, ['floor', 'wall-floor', 'opening'], true) ? $surface : 'floor',
                'anchor_x_percent' => $this->clampPercent($zone['anchor_x_percent'] ?? 50, 5, 95),
                'anchor_y_percent' => $this->clampPercent($zone['anchor_y_percent'] ?? 86, 30, 96),
                'max_width_percent' => $this->clampPercent($zone['max_width_percent'] ?? 60, 15, 90),
                'rotation_deg' => $this->clampPercent($zone['rotation_deg'] ?? 0, -18, 18),
                'perspective_deg' => $this->clampPercent($zone['perspective_deg'] ?? 0, -24, 24),
                'flip' => filter_var($zone['flip'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'quad_percent' => count($this->sanitizePolygon($zone['quad_percent'] ?? [])) === 4 ? $this->sanitizePolygon($zone['quad_percent']) : null,
                'suitable_types' => collect($zone['suitable_types'] ?? [])->take(8)->map(fn ($value) => substr(trim((string) $value), 0, 50))->filter()->values()->all(),
                'clearance_score' => $this->clampPercent($zone['clearance_score'] ?? 50),
                'occlusion_score' => $this->clampPercent($zone['occlusion_score'] ?? 25),
                'confidence' => in_array($zone['confidence'] ?? null, ['low', 'medium', 'high'], true) ? $zone['confidence'] : 'low',
                'reason' => substr(trim((string) ($zone['reason'] ?? 'Detected clear placement zone.')), 0, 280),
            ];
        })->filter()->values()->all();
    }

    private function clampPercent(mixed $value, float $minimum = 0, float $maximum = 100): float
    {
        $number = is_numeric($value) ? (float) $value : $minimum;

        return round(min($maximum, max($minimum, $number)), 1);
    }

    private function errorContext(\Throwable $exception): array
    {
        $context = ['exception' => $exception::class];

        if (property_exists($exception, 'response') && $exception->response) {
            $context['status'] = $exception->response->status();
        }

        return $context;
    }

    private function productImage(FurnitureProduct $product): ?array
    {
        if (! $product->image_path) {
            return null;
        }

        if (str_starts_with($product->image_path, 'demo:')) {
            $path = public_path('images/furniture/'.substr($product->image_path, 5));
        } elseif (str_starts_with($product->image_path, 'catalog:')) {
            $path = public_path('images/catalog/'.substr($product->image_path, 8));
        } elseif (Storage::disk('public')->exists($product->image_path)) {
            return [
                'bytes' => Storage::disk('public')->get($product->image_path),
                'mime_type' => Storage::disk('public')->mimeType($product->image_path) ?: 'image/png',
            ];
        } else {
            return null;
        }

        if (! is_file($path)) {
            return null;
        }

        return ['bytes' => file_get_contents($path), 'mime_type' => mime_content_type($path) ?: 'image/png'];
    }

    private function fallbackChat(array $items, array $catalog, string $furnitureType, string $question): string
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

        $availableNames = collect($catalog)->pluck('name')->implode(', ');

        return "I can help compare {$names} by price, wood, finish, style, dimensions, room suitability, and catalog availability. Available {$furnitureType} options in the system are: {$availableNames}. Physical branch stock must still be confirmed with Lance Furniture Shop staff.";
    }

    private function catalogAvailabilityAnswer(array $catalog, string $furnitureType, string $question): ?string
    {
        $lower = strtolower($question);
        $asksAvailability = (bool) preg_match('/\b(available|availability|stock|meron|mayroon|gusto|nais|want|need|looking|hanap|request|kulay|color|do you have)\b/i', $question);

        if (! $asksAvailability) {
            return null;
        }

        $products = collect($catalog);
        if ($products->isEmpty()) {
            return "Not available in our current catalog: there are no active {$furnitureType} products listed right now. Please ask Lance Furniture Shop staff when new options may become available.";
        }

        $requested = [];
        foreach (['name', 'category', 'wood', 'finish', 'style'] as $field) {
            $values = $products->pluck($field)->filter()->unique(fn ($value) => strtolower((string) $value));
            foreach ($values as $value) {
                if (str_contains($lower, strtolower((string) $value))) {
                    $requested[$field][] = (string) $value;
                }
            }
        }

        $genericAvailabilityQuestion = preg_match('/\b(what|which|ano|anong|list|show)\b/i', $question)
            || preg_match('/\b(available|availability|stock)\b/i', $question) && $requested === [];

        if ($genericAvailabilityQuestion) {
            $list = $products->map(fn ($product) => $this->availabilityProductLabel($product))->implode('; ');

            return "Available in our current {$furnitureType} catalog: {$list}. These are active system listings; please confirm physical stock at your preferred branch with Lance Furniture Shop staff.";
        }

        $matches = $products->filter(function ($product) use ($requested): bool {
            if ($requested === []) {
                return false;
            }

            foreach ($requested as $field => $values) {
                if (! collect($values)->contains(fn ($value) => strcasecmp((string) $product[$field], $value) === 0)) {
                    return false;
                }
            }

            return true;
        });

        if ($matches->isNotEmpty()) {
            $list = $matches->map(fn ($product) => $this->availabilityProductLabel($product))->implode('; ');

            return "Available in our current {$furnitureType} catalog: {$list}. This confirms an active system listing, but physical stock at a specific branch must still be verified by shop staff.";
        }

        $alternatives = $products->take(3)->map(fn ($product) => $this->availabilityProductLabel($product))->implode('; ');

        return "Not available in our current {$furnitureType} catalog with that exact request. Closest available options are: {$alternatives}. Please confirm physical branch stock with Lance Furniture Shop staff.";
    }

    private function availabilityProductLabel(array $product): string
    {
        return $product['name'].' ('.$product['wood'].' wood, '.$product['finish'].' finish, '.$product['style'].', PHP '.number_format((float) $product['price'], 2).')';
    }
}
