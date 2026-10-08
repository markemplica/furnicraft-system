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
use Illuminate\Support\Facades\DB;

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
            $this->updateState($room, [
                'analysis_status' => 'failed',
                'analysis_error' => 'The uploaded room image is no longer available.',
            ]);

            return;
        }

        if (! $this->updateState($room, [
            'analysis_status' => 'processing',
            'analysis_attempts' => $room->analysis_attempts + 1,
            'analysis_error' => null,
        ])) return;

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
            $httpStatus = $telemetry->statusCode($exception);
            $transientFailure = $httpStatus === null
                || in_array($httpStatus, [408, 425, 429], true)
                || $httpStatus >= 500;

            if ($transientFailure && $this->attempts() < $this->tries) {
                if (! $this->updateState($room, [
                    'analysis_status' => 'retrying',
                    'analysis_error' => 'The AI service is temporarily unavailable. FurniCraft will retry automatically.',
                ])) return;

                throw $exception;
            }

            $fallback = $gemini->roomFallback(
                $room->room_width_m ? (float) $room->room_width_m : null,
                $room->room_depth_m ? (float) $room->room_depth_m : null,
                $this->furnitureType,
            );
            $message = $transientFailure
                ? 'Gemini is temporarily unavailable. Local room guidance is being used; you can retry the analysis.'
                : 'Gemini could not process this analysis request. Local room guidance is being used; you can retry the analysis.';
            $this->complete($room, $fallback, 'fallback', $message);
        }
    }

    public function failed(?Throwable $exception): void
    {
        RoomImage::whereKey($this->roomImageId)->whereIn('analysis_status', ['queued', 'processing', 'retrying'])->update([
            'analysis_status' => 'failed',
            'analysis_error' => 'Room AI analysis could not be completed. You can retry without uploading the photo again.',
        ]);
    }

    private function updateState(RoomImage $snapshot, array $attributes): bool
    {
        return DB::transaction(function () use ($snapshot, $attributes) {
            $current = RoomImage::whereKey($snapshot->id)->lockForUpdate()->first();
            if (! $this->canPersist($snapshot, $current)) return false;

            $current->update($attributes);
            return true;
        });
    }

    private function canPersist(RoomImage $snapshot, ?RoomImage $current): bool
    {
        return $current
            && $current->analysis_requested_at == $snapshot->analysis_requested_at
            && $current->gemini_consent_at == $snapshot->gemini_consent_at;
    }

    private function complete(RoomImage $room, array $analysis, string $status, ?string $message = null): void
    {
        DB::transaction(function () use ($room, $analysis, $status, $message) {
            $current = RoomImage::whereKey($room->id)->lockForUpdate()->first();
            if (! $this->canPersist($room, $current)) return;

            // Preview jobs share this JSON field; retain their latest committed entries.
            foreach (['generated_previews', 'generated_preview_attempts'] as $key) {
                unset($analysis[$key]);
                if (array_key_exists($key, $current->analysis ?? [])) {
                    $analysis[$key] = $current->analysis[$key];
                }
            }
            $analysis['zone_analysis_attempted_at'] = now()->toIso8601String();
            $current->update([
                'room_width_m' => $current->room_width_m ?: data_get($analysis, 'estimated_width_m'),
                'room_depth_m' => $current->room_depth_m ?: data_get($analysis, 'estimated_depth_m'),
                'analysis' => $analysis,
                'analysis_status' => $status,
                'analysis_error' => $message,
                'analyzed_at' => now(),
            ]);
        });
    }
}
