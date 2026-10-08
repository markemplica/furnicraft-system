<?php

namespace App\Console\Commands;

use App\Models\RoomImage;
use App\Models\RoomPreviewAnalysis;
use App\Services\PrivacyAudit;
use App\Services\RoomPhotoService;
use Illuminate\Console\Command;

class PruneRoomImages extends Command
{
    protected $signature = 'room-images:prune';

    protected $description = 'Delete room images and generated previews older than the configured retention period';

    public function handle(RoomPhotoService $photos, PrivacyAudit $audit): int
    {
        $days = max(1, (int) config('privacy.room_image_retention_days', config('roompreview.retention_days', 30)));
        $deleted = 0;
        RoomImage::where('created_at', '<', now()->subDays($days))->orderBy('id')->chunkById(100, function ($rooms) use ($photos, $audit, &$deleted): void {
            foreach ($rooms as $room) {
                $roomId = $room->id;
                $userId = $room->user_id;
                $photos->deleteRoomFiles($room);
                $room->delete();
                $audit->record('room_photo_retention_expired', 'room_image', $roomId, ['retention_days' => $days], $userId);
                $deleted++;
            }
        });
        $analysisRows = RoomPreviewAnalysis::where('updated_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} room records and {$analysisRows} shared room analyses older than {$days} days.");

        return self::SUCCESS;
    }
}
