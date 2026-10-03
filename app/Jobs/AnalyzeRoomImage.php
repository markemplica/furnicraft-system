<?php

namespace App\Jobs;

use App\Models\RoomImage;
use App\Services\GeminiService;
use App\Services\AiTelemetry;
use App\Services\PrivacyAudit;
use App\Services\RoomPhotoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AnalyzeRoomImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public function __construct(public int $roomImageId, public ?string $furnitureType = null) {}

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(GeminiService $gemini, RoomPhotoService $photos, PrivacyAudit $audit, AiTelemetry $telemetry): void
    {
        $room = RoomImage::find($this->roomImageId);

        if (! $room) {
            return;
        }

        if (! $photos->exists($room->image_path)) {
            $telemetry->record('room', 'room_analysis', 'failure', failureCode: 'room_photo_missing');
            $room->update([
                'analysis_status' => 'failed',
                'analysis_error' => 'The uploaded room image is no longer available.',
            ]);

            return;
        }

        $room->update([
            'analysis_status' => 'processing',
            'analysis_attempts' => $room->analysis_attempts + 1,
            'analysis_error' => null,
        ]);

        if (! $room->gemini_consent_at || ! $gemini->isConfigured()) {
            $telemetry->record('room', 'room_analysis', 'fallback', fallbackUsed: true, failureCode: ! $room->gemini_consent_at ? 'consent_not_granted' : 'not_configured');
            $analysis = $gemini->roomFallback(
                $room->room_width_m ? (float) $room->room_width_m : null,
                $room->room_depth_m ? (float) $room->room_depth_m : null,
                $this->furnitureType,
            );
            $message = ! $room->gemini_consent_at
                ? 'AI image processing was not authorized; local planning guidance is being used.'
                : 'Gemini is not configured; safe local placement guidance is being used.';
            $this->complete($room, $analysis, 'fallback', $message);

            return;
        }

        try {
            $audit->record('room_photo_sent_to_gemini', 'room_image', $room->id, ['purpose' => 'room_analysis'], $room->user_id);
            $analysis = $gemini->analyzeRoom(
                $room->image_path,
                $room->room_width_m ? (float) $room->room_width_m : null,
                $room->room_depth_m ? (float) $room->room_depth_m : null,
                $this->furnitureType,
                true,
            );
            $this->complete($room, $analysis, 'completed');
        } catch (Throwable $exception) {
            $room->update([
                'analysis_status' => $this->attempts() < $this->tries ? 'retrying' : 'failed',
                'analysis_error' => $this->attempts() < $this->tries
                    ? 'The AI service is temporarily unavailable. FurniCraft will retry automatically.'
                    : 'Room AI analysis could not be completed. You can retry without uploading the photo again.',
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        RoomImage::whereKey($this->roomImageId)->update([
            'analysis_status' => 'failed',
            'analysis_error' => 'Room AI analysis could not be completed. You can retry without uploading the photo again.',
        ]);
    }

    private function complete(RoomImage $room, array $analysis, string $status, ?string $message = null): void
    {
        $analysis['zone_analysis_attempted_at'] = now()->toIso8601String();
        $room->update([
            'room_width_m' => $room->room_width_m ?: data_get($analysis, 'estimated_width_m'),
            'room_depth_m' => $room->room_depth_m ?: data_get($analysis, 'estimated_depth_m'),
            'analysis' => $analysis,
            'analysis_status' => $status,
            'analysis_error' => $message,
            'analyzed_at' => now(),
        ]);
    }
}
