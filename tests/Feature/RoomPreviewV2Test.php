<?php

namespace Tests\Feature;

use App\Models\RoomImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomPreviewV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_v2_endpoint_stays_hidden_until_feature_flag_is_enabled(): void
    {
        config()->set('roompreview.use_v2_pipeline', false);
        $user = User::factory()->create(['role' => 'customer']);
        $room = RoomImage::create([
            'user_id' => $user->id,
            'image_path' => 'rooms/'.$user->id.'/sample.png',
            'image_sha256' => str_repeat('a', 64),
            'analysis_status' => 'completed',
        ]);

        $this->actingAs($user)
            ->post(route('room.preview-v2.store', $room), ['furniture_id' => 1])
            ->assertNotFound();
    }

    public function test_preview_image_normalizer_rejects_invalid_image_bytes(): void
    {
        $this->expectException(\RuntimeException::class);
        app(\App\Services\RoomPreviewRasterService::class)->normalize('not-an-image');
    }
    public function test_v2_endpoint_rejects_client_prompts_and_placement_values(): void
    {
        config()->set('roompreview.use_v2_pipeline', true);
        config()->set('roompreview.rollout_percent', 100);
        $user = User::factory()->create(['role' => 'customer']);
        $room = RoomImage::create([
            'user_id' => $user->id,
            'image_path' => 'rooms/'.$user->id.'/sample.png',
            'image_sha256' => str_repeat('b', 64),
            'analysis_status' => 'completed',
        ]);

        $this->actingAs($user)
            ->post(route('room.preview-v2.store', $room), [
                'furniture_id' => 1,
                'prompt' => 'put furniture by the window',
                'placement' => ['x' => 0.5, 'y' => 0.8],
            ])
            ->assertUnprocessable();
    }

    public function test_server_verifier_uses_header_auth_and_requires_all_five_scores(): void
    {
        config([
            'services.gemini.key' => 'unit-test-secret',
            'services.gemini.model' => 'gemini-test',
            'services.gemini.fallback_model' => null,
        ]);
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        $png = file_get_contents(base_path('tests/Fixtures/room-600.png'));
        \Illuminate\Support\Facades\Storage::disk('local')->put('rooms/v2-source.png', $png);
        \Illuminate\Support\Facades\Storage::disk('public')->put('products/v2-item.png', $png);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode([
                'scores' => ['scale' => .8, 'placement' => .9, 'catalog_fidelity' => .85, 'room_preservation' => .9, 'lighting' => .8],
                'single_item_only' => true,
                'catalog_item_only' => true,
            ])]]]]],
        ])]);

        $product = new \App\Models\FurnitureProduct([
            'name' => 'Catalog Item', 'category' => 'Chair', 'width_cm' => 80, 'depth_cm' => 70, 'height_cm' => 90,
            'image_path' => 'products/v2-item.png',
        ]);
        $result = app(\App\Services\GeminiService::class)->verifyGeneratedRoom('rooms/v2-source.png', $png, $product, 1);

        $this->assertTrue($result['passed']);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) =>
            $request->header('x-goog-api-key') === ['unit-test-secret']
            && ! str_contains($request->url(), 'key=')
        );
    }

}
