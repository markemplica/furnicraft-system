<?php

namespace App\Jobs;

use App\Models\FurnitureProduct;
use App\Models\RoomImage;
use App\Models\RoomPreviewGeneration;
use App\Services\GeminiService;
use App\Services\AiTelemetry;
use App\Services\PrivacyAudit;
use App\Services\RoomPhotoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateRoomPreview implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 540;

    public int $uniqueFor = 600;

    public function __construct(public int $roomImageId, public int $productId) {}

    public function uniqueId(): string
    {
        return $this->roomImageId.':'.$this->productId;
    }

    public function backoff(): array
    {
        return [30];
    }

    public function handle(GeminiService $gemini, RoomPhotoService $photos, PrivacyAudit $audit, AiTelemetry $telemetry): void
    {
        $room = RoomImage::find($this->roomImageId);
        $product = FurnitureProduct::find($this->productId);
        $generation = RoomPreviewGeneration::where('room_image_id', $this->roomImageId)
            ->where('furniture_product_id', $this->productId)->first();

        if (! $room || ! $product || ! $generation) return;

        if (! $room->gemini_consent_at) {
            $generation->update(['status' => 'cancelled', 'message' => 'AI processing is disabled for this photo.']);
            return;
        }

        $generation->update(['status' => 'processing', 'attempts' => $generation->attempts + 1, 'message' => null]);

        try {
            $audit->record('room_photo_sent_to_gemini', 'room_image', $room->id, ['purpose' => 'furnished_preview'], $room->user_id);
            $result = $gemini->furnishRoom($room->image_path, $product, true);

            if (! $result['used'] || ! filled($result['bytes'] ?? null)) {
                $generation->update(['status' => 'fallback', 'message' => 'Gemini could not create the image. The local 2D placement preview is shown instead.']);
                return;
            }

            if (! $room->fresh()->gemini_consent_at) {
                $generation->update(['status' => 'cancelled', 'message' => 'AI processing was disabled before the image could be saved.']);
                return;
            }

            $extension = match ($result['mime_type']) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/webp' => 'webp',
                default => 'png',
            };
            $path = 'rooms/'.$room->user_id.'/previews/room-'.$room->id.'-product-'.$product->id.'-'.$product->updated_at->timestamp.'.'.$extension;
            $photos->storeGenerated($path, $result['bytes']);

            if (! $room->fresh()->gemini_consent_at) {
                $photos->deleteGenerated($path);
                $generation->update(['status' => 'cancelled', 'message' => 'AI processing was disabled before the image could be saved.']);
                return;
            }

            $analysis = $room->fresh()->analysis ?? [];
            $analysis['generated_previews'][(string) $product->id] = [
                'path' => $path,
                'gemini_used' => true,
                'generated_at' => now()->toIso8601String(),
            ];
            $room->fresh()->update(['analysis' => $analysis]);
            $generation->update(['status' => 'completed', 'message' => 'Generated room preview is ready.']);
        } catch (Throwable $exception) {
            if ($this->attempts() >= $this->tries) {
                $telemetry->record('image', 'furnished_preview', 'fallback', fallbackUsed: true, failureCode: $telemetry->failureCode($exception));
            }
            $generation->update([
                'status' => $this->attempts() < $this->tries ? 'retrying' : 'failed',
                'message' => $this->attempts() < $this->tries
                    ? 'Gemini is temporarily unavailable. FurniCraft will retry automatically.'
                    : 'Gemini could not create the image. The local 2D placement preview is shown instead.',
            ]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        RoomPreviewGeneration::where('room_image_id', $this->roomImageId)
            ->where('furniture_product_id', $this->productId)
            ->update(['status' => 'failed', 'message' => 'Gemini could not create the image. The local 2D placement preview is shown instead.']);
    }
}
