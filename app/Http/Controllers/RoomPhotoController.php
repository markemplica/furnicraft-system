<?php

namespace App\Http\Controllers;

use App\Models\RoomImage;
use App\Services\RoomPhotoService;
use Illuminate\Http\Request;

class RoomPhotoController extends Controller
{
    public function original(Request $request, RoomImage $roomImage, RoomPhotoService $photos)
    {
        abort_unless($roomImage->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return $this->image($photos, $roomImage->image_path);
    }

    public function preview(Request $request, RoomImage $roomImage, int $product, RoomPhotoService $photos)
    {
        abort_unless($roomImage->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless($roomImage->gemini_consent_at, 404);
        $path = data_get($roomImage->analysis, 'generated_previews.'.$product.'.path');
        abort_unless(filled($path), 404);

        return $this->image($photos, $path);
    }

    private function image(RoomPhotoService $photos, string $path)
    {
        abort_unless($photos->exists($path), 404);
        $mime = $photos->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response($photos->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Vary' => 'Cookie, Authorization',
        ]);
    }
}
