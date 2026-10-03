<?php

namespace App\Services;

use App\Models\RoomImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RoomPhotoService
{
    public function __construct(private ImageMetadataStripper $metadataStripper) {}

    public function storeUpload(UploadedFile $file, int $userId): string
    {
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $path = 'rooms/'.$userId.'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('local')->put($path, $this->metadataStripper->strip($file->get(), $mime))) {
            throw new RuntimeException('The room image could not be saved securely.');
        }

        return $path;
    }

    public function exists(string $path): bool
    {
        return Storage::disk('local')->exists($path) || Storage::disk('public')->exists($path);
    }

    public function get(string $path): string
    {
        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';

        return Storage::disk($disk)->get($path);
    }

    public function mimeType(string $path): string
    {
        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';

        return Storage::disk($disk)->mimeType($path) ?: 'image/jpeg';
    }

    public function storeGenerated(string $path, string $bytes): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';
        $safeBytes = $this->metadataStripper->strip($bytes, $mime);
        if (! Storage::disk('local')->put($path, $safeBytes)) throw new RuntimeException('Generated preview could not be saved privately.');
    }

    public function deleteGenerated(string $path): void
    {
        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);
    }

    public function deleteRoomFiles(RoomImage $room): void
    {
        $paths = [$room->image_path];
        foreach (data_get($room->analysis, 'generated_previews', []) as $preview) {
            if (filled(data_get($preview, 'path'))) $paths[] = data_get($preview, 'path');
        }
        foreach (array_unique(array_filter($paths)) as $path) {
            Storage::disk('local')->delete($path);
            Storage::disk('public')->delete($path);
        }
    }

    public function deleteGeneratedPreviews(RoomImage $room): void
    {
        foreach (data_get($room->analysis, 'generated_previews', []) as $preview) {
            $path = data_get($preview, 'path');
            if (! filled($path)) continue;
            Storage::disk('local')->delete($path);
            Storage::disk('public')->delete($path);
        }
    }

    public function moveLegacyRoomFiles(RoomImage $room): bool
    {
        $moved = false;
        $paths = [$room->image_path];
        foreach (data_get($room->analysis, 'generated_previews', []) as $preview) {
            if (filled(data_get($preview, 'path'))) $paths[] = data_get($preview, 'path');
        }
        foreach (array_unique(array_filter($paths)) as $path) {
            if (! Storage::disk('public')->exists($path)) continue;
            if (! Storage::disk('local')->exists($path)) {
                if (! Storage::disk('local')->put($path, Storage::disk('public')->get($path))) {
                    throw new RuntimeException('A legacy room photo could not be secured; the public original was preserved.');
                }
                $moved = true;
            }
            Storage::disk('public')->delete($path);
        }

        return $moved;
    }

    public function secureLegacyStorage(): array
    {
        $moved = 0;
        RoomImage::query()->orderBy('id')->chunkById(100, function ($rooms) use (&$moved): void {
            foreach ($rooms as $room) {
                if ($this->moveLegacyRoomFiles($room)) $moved++;
            }
        });
        $orphansMoved = 0;
        foreach (Storage::disk('public')->allFiles('rooms') as $path) {
            if (! Storage::disk('local')->exists($path)) {
                if (! Storage::disk('local')->put($path, Storage::disk('public')->get($path))) {
                    throw new RuntimeException('A legacy room file could not be secured; the public original was preserved.');
                }
                $orphansMoved++;
            }
            Storage::disk('public')->delete($path);
        }

        return ['rooms_moved' => $moved, 'orphan_files_moved' => $orphansMoved];
    }
}
