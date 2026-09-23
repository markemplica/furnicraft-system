<?php

namespace App\Services;

use App\Models\CustomerPreference;
use App\Models\Favorite;
use App\Models\FurnitureProduct;
use App\Models\Recommendation;
use App\Models\Reservation;
use App\Models\RoomImage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public function __construct(private GeminiService $gemini) {}

    public function generate(User $user, CustomerPreference $preference, ?RoomImage $roomImage = null): Recommendation
    {
        $products = FurnitureProduct::query()->where('status', 'active')->get();
        $collaborative = $this->collaborativeScores($user, $products);

        $ranked = $products->map(function (FurnitureProduct $product) use ($preference, $collaborative) {
            $content = $this->contentScore($product, $preference);
            $collab = $collaborative[$product->id] ?? 0;
            $total = round(($content * .82) + ($collab * .18), 3);

            return ['product' => $product, 'content' => $content, 'collaborative' => $collab, 'total' => $total];
        })->sortByDesc('total')->take(8)->values();

        $ai = $this->gemini->recommendationSummary($preference->toArray(), $ranked->map(fn ($row) => [
            'name' => $row['product']->name,
            'score' => $row['total'],
            'price' => $row['product']->price,
            'wood' => $row['product']->wood_type,
            'finish' => $row['product']->varnish_finish,
            'style' => $row['product']->style,
        ])->all());

        return DB::transaction(function () use ($user, $preference, $roomImage, $ranked, $ai) {
            $recommendation = Recommendation::create([
                'user_id' => $user->id,
                'customer_preference_id' => $preference->id,
                'room_image_id' => $roomImage?->id,
                'summary' => $ai['text'],
                'algorithm_version' => 'hybrid-v1',
                'gemini_used' => $ai['used'],
            ]);

            foreach ($ranked as $index => $row) {
                $product = $row['product'];
                $reasons = collect([
                    $this->matches($preference->style, $product->style) ? "matches your {$product->style} style" : null,
                    $this->matches($preference->wood_type, $product->wood_type) ? "uses your preferred {$product->wood_type} wood" : null,
                    $this->matches($preference->varnish_finish, $product->varnish_finish) ? "has the {$product->varnish_finish} finish you selected" : null,
                    ((float) $product->price >= (float) $preference->budget_min && (float) $product->price <= (float) $preference->budget_max) ? 'fits your budget range' : null,
                ])->filter()->values();

                $recommendation->items()->create([
                    'furniture_product_id' => $product->id,
                    'content_score' => $row['content'],
                    'collaborative_score' => $row['collaborative'],
                    'total_score' => $row['total'],
                    'position' => $index + 1,
                    'explanation' => $reasons->isEmpty() ? 'This is the closest available catalog match based on your combined preferences.' : ucfirst($reasons->implode(', ')).'.',
                ]);
            }

            return $recommendation->load('items.product', 'preference', 'roomImage');
        });
    }

    private function contentScore(FurnitureProduct $product, CustomerPreference $preference): float
    {
        $score = 10.0;
        $price = (float) $product->price;
        $min = (float) $preference->budget_min;
        $max = max((float) $preference->budget_max, 1);
        $score += ($price >= $min && $price <= $max) ? 30 : max(0, 30 - (abs($price - min(max($price, $min), $max)) / $max * 30));
        $score += $this->matches($preference->style, $product->style) ? 20 : 0;
        $score += $this->matches($preference->wood_type, $product->wood_type) ? 15 : 0;
        $score += $this->matches($preference->varnish_finish, $product->varnish_finish) ? 15 : 0;

        if ($preference->room_width_m && $preference->room_depth_m && $product->width_cm && $product->depth_cm) {
            $fits = ((float) $product->width_cm / 100 <= (float) $preference->room_width_m * .85)
                && ((float) $product->depth_cm / 100 <= (float) $preference->room_depth_m * .85);
            $score += $fits ? 10 : 0;
        } else {
            $score += 5;
        }

        return round(min(100, $score), 3);
    }

    private function collaborativeScores(User $user, Collection $products): array
    {
        $own = Favorite::where('user_id', $user->id)->pluck('furniture_product_id')
            ->merge(Reservation::where('user_id', $user->id)->pluck('furniture_product_id'))->unique();

        $similarUserIds = $own->isEmpty() ? collect() : Favorite::whereIn('furniture_product_id', $own)
            ->where('user_id', '!=', $user->id)->pluck('user_id')->unique();

        $signals = collect();
        if ($similarUserIds->isNotEmpty()) {
            $signals = Favorite::whereIn('user_id', $similarUserIds)->select('furniture_product_id', DB::raw('count(*) as signal'))
                ->groupBy('furniture_product_id')->pluck('signal', 'furniture_product_id');
        }

        if ($signals->isEmpty()) {
            $signals = Favorite::select('furniture_product_id', DB::raw('count(*) as signal'))->groupBy('furniture_product_id')->pluck('signal', 'furniture_product_id');
        }

        $max = max(1, (int) $signals->max());

        return $products->mapWithKeys(fn ($product) => [$product->id => round(((int) ($signals[$product->id] ?? 0) / $max) * 100, 3)])->all();
    }

    private function matches(?string $expected, ?string $actual): bool
    {
        return blank($expected) || strcasecmp(trim($expected), trim((string) $actual)) === 0;
    }
}
