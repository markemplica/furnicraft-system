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
        $bytes = $this->normalizeJpegOrientation($file, $mime);
        if (! Storage::disk('local')->put($path, $this->metadataStripper->strip($bytes, $mime))) {
            throw new RuntimeException('The room image could not be saved securely.');
        }

        return $path;
    }

    private function normalizeJpegOrientation(UploadedFile $file, string $mime): string
    {
        $bytes = $file->get();
        if (! in_array($mime, ['image/jpeg', 'image/jpg'], true)
            || ! function_exists('exif_read_data')
            || ! function_exists('imagecreatefromstring')
            || ! function_exists('imagerotate')) {
            return $bytes;
        }

        $metadata = @exif_read_data($file->getPathname());
        $orientation = (int) ($metadata['Orientation'] ?? 1);
        if ($orientation < 2 || $orientation > 8) return $bytes;

        $image = @imagecreatefromstring($bytes);
        if (! $image) return $bytes;
        if (in_array($orientation, [2, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
        $rotation = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($rotation !== 0) {
            $rotated = imagerotate($image, $rotation, 0);
            if ($rotated) { imagedestroy($image); $image = $rotated; }
        }
        ob_start();
        imagejpeg($image, null, 90);
        $normalized = (string) ob_get_clean();
        imagedestroy($image);
        return $normalized !== '' ? $normalized : $bytes;
    }

    public function metadata(string $path): array
    {
        $bytes = $this->get($path);
        $dimensions = @getimagesizefromstring($bytes);
        if (! is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1])) {
            throw new RuntimeException('The processed room image is invalid. Please upload another photo.');
        }

        return [
            'sha256' => hash('sha256', $bytes),
            'width' => (int) $dimensions[0],
            'height' => (int) $dimensions[1],
            'mime_type' => (string) ($dimensions['mime'] ?? 'image/jpeg'),
        ];
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
        foreach (\App\Models\RoomPreviewGeneration::where('room_image_id', $room->id)->get(['placement_json']) as $generation) {
            if (filled($artifact = data_get($generation->placement_json, 'artifact_path'))) $paths[] = $artifact;
        }
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
        foreach (\App\Models\RoomPreviewGeneration::where('room_image_id', $room->id)->get() as $generation) {
            $path = data_get($generation->placement_json, 'artifact_path');
            if (filled($path)) {
                Storage::disk('local')->delete($path);
                Storage::disk('public')->delete($path);
            }
            $data = $generation->placement_json ?? [];
            unset($data['artifact_path'], $data['verification'], $data['pixel_guard']);
            $generation->update([
                'status' => 'cancelled',
                'message' => 'AI processing is disabled for this photo.',
                'placement_json' => $data ?: null,
            ]);
        }
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
        foreach (\App\Models\RoomPreviewGeneration::where('room_image_id', $room->id)->get(['placement_json']) as $generation) {
            if (filled($artifact = data_get($generation->placement_json, 'artifact_path'))) $paths[] = $artifact;
        }
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
