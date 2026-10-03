<?php

namespace App\Console\Commands;

use App\Services\RoomPhotoService;
use Illuminate\Console\Command;

class SecureRoomPhotos extends Command
{
    protected $signature = 'room-images:secure-legacy';

    protected $description = 'Move existing room photos and generated previews from public to private storage';

    public function handle(RoomPhotoService $photos): int
    {
        $result = $photos->secureLegacyStorage();
        $this->info("Secured files for {$result['rooms_moved']} room records and moved {$result['orphan_files_moved']} unlinked room files into private storage.");

        return self::SUCCESS;
    }
}
