<?php

namespace App\Jobs;

use App\Models\RoomPreviewGeneration;
use App\Services\RoomPreviewV2Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateRoomPreviewV2 implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 540;
    public int $uniqueFor = 600;

    public function __construct(public int $generationId, public string $cacheKey) {}

    public function uniqueId(): string
    {
        return $this->generationId.':'.$this->cacheKey;
    }

    public function backoff(): array
    {
        return [20, 60];
    }

    public function handle(RoomPreviewV2Service $pipeline): void
    {
        $pipeline->process($this->generationId);
    }

    public function failed(?Throwable $exception): void
    {
        RoomPreviewGeneration::whereKey($this->generationId)->whereIn('status', ['queued', 'processing', 'retrying'])->update([
            'status' => 'failed',
            'failure_code' => 'queue_job_failed',
            'message' => 'The room preview could not be completed. Please try again.',
            'completed_at' => now(),
        ]);
    }
}
