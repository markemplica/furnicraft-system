<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeRoomImage;
use App\Jobs\GenerateRoomPreview;
use App\Models\FurnitureProduct;
use App\Models\Recommendation;
use App\Models\RoomImage;
use App\Models\RoomPreviewGeneration;
use App\Services\FurniturePlacementService;
use App\Services\GeminiService;
use App\Services\PrivacyAudit;
use App\Services\RoomPhotoService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->to(route('preferences.edit').'#room-preview');
    }

    public function store(Request $request, GeminiService $gemini, RoomPhotoService $photos, PrivacyAudit $audit)
    {
        $data = $request->validate(['room_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'room_width_m' => ['nullable', 'numeric', 'min:1', 'max:50'], 'room_depth_m' => ['nullable', 'numeric', 'min:1', 'max:50'], 'return_to' => ['nullable', 'in:recommendations'], 'gemini_consent' => ['nullable', 'boolean']]);
        $preference = $request->user()->preference()->first();
        if (! $preference || blank($preference->furniture_type)) {
            return redirect()->to(route('preferences.edit').'#preference-form')->withErrors(['workflow' => 'Save your furniture preferences before uploading a room image.']);
        }

        $path = $photos->storeUpload($request->file('room_image'), $request->user()->id);
        $consented = $request->boolean('gemini_consent');
        $width = isset($data['room_width_m']) ? (float) $data['room_width_m'] : null;
        $depth = isset($data['room_depth_m']) ? (float) $data['room_depth_m'] : null;
        $room = RoomImage::create([
            'user_id' => $request->user()->id,
            'image_path' => $path,
            'room_width_m' => $width,
            'room_depth_m' => $depth,
            'analysis' => $gemini->roomFallback($width, $depth, $preference->furniture_type),
            'analysis_status' => 'queued',
            'analysis_requested_at' => now(),
            'gemini_consent_at' => $consented ? now() : null,
            'gemini_consent_version' => $consented ? 'room-ai-v1' : null,
        ]);
        if ($consented) $audit->record('room_photo_gemini_consent_granted', 'room_image', $room->id, ['consent_version' => 'room-ai-v1']);
        AnalyzeRoomImage::dispatch($room->id, $preference->furniture_type);
        $room->refresh();
        $message = in_array($room->analysis_status, ['completed', 'fallback'], true)
            ? 'Your room is ready and selected for recommendations.'
            : 'Your room was uploaded. FurniCraft is analyzing it in the background.';

        if (($data['return_to'] ?? null) === 'recommendations') {
            return redirect()->to(route('preferences.edit', ['room' => $room->id]).'#room-preview')->with('success', $message);
        }

        return redirect()->route('room.visualize', $room)->with('success', $message);
    }

    public function visualize(Request $request, RoomImage $roomImage, GeminiService $gemini, FurniturePlacementService $placementService, RoomPhotoService $photos, PrivacyAudit $audit)
    {
        abort_unless($roomImage->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $recommendation = null;

        if ($request->filled('recommendation')) {
            $recommendation = Recommendation::with('items.product')
                ->where('user_id', $request->user()->id)
                ->findOrFail($request->integer('recommendation'));

            abort_unless($recommendation->room_image_id === $roomImage->id, 403);
        }

        $products = $recommendation
            ? $recommendation->items->pluck('product')->filter(fn ($product) => $product?->status === 'active')->values()
            : FurnitureProduct::where('status', 'active')->orderBy('name')->get();

        if (! $recommendation && filled($request->user()->preference?->furniture_type)) {
            $furnitureType = $request->user()->preference->furniture_type;
            $products = $products
                ->filter(fn (FurnitureProduct $product) => $product->recommendationType() === $furnitureType)
                ->values();
        }

        $selectedProduct = $request->filled('product')
            ? $products->firstWhere('id', $request->integer('product'))
            : $products->first();

        abort_if($request->filled('product') && ! $selectedProduct, 404);

        $analysis = $roomImage->analysis ?? [];
        $furnitureType = $selectedProduct?->recommendationType() ?: $request->user()->preference?->furniture_type;
        $zonesMissing = blank($analysis['candidate_zones'] ?? null)
            || ($furnitureType && strcasecmp((string) ($analysis['analyzed_furniture_type'] ?? ''), $furnitureType) !== 0);
        $analysisRunning = in_array($roomImage->analysis_status, ['queued', 'processing', 'retrying'], true);
        $canAnalyzeZones = ! $roomImage->analysis_requested_at || $roomImage->analysis_requested_at->lt(now()->subMinutes(10));

        if (! $request->user()->isAdmin() && $roomImage->gemini_consent_at && $selectedProduct && $zonesMissing && $gemini->isConfigured() && ! $analysisRunning && $canAnalyzeZones) {
            $roomImage->update([
                'analysis_status' => 'queued',
                'analysis_error' => null,
                'analysis_requested_at' => now(),
            ]);
            AnalyzeRoomImage::dispatch($roomImage->id, $furnitureType);
            $roomImage->refresh();
        }

        $generatedPreview = null;
        $generatedWithGemini = false;
        $previewGeneration = null;

        if ($recommendation && $selectedProduct) {
            $analysis = $roomImage->analysis ?? [];
            $cached = data_get($analysis, 'generated_previews.'.$selectedProduct->id);
            $lastAttempt = data_get($analysis, 'generated_preview_attempts.'.$selectedProduct->id);
            $canAttempt = blank($lastAttempt) || now()->subMinutes(10)->greaterThan(Carbon::parse($lastAttempt));

            if ($roomImage->gemini_consent_at && is_array($cached) && filled($cached['path'] ?? null) && $photos->exists($cached['path'])) {
                $generatedPreview = $cached['path'];
                $generatedWithGemini = (bool) ($cached['gemini_used'] ?? false);
            } elseif ($roomImage->gemini_consent_at && $gemini->isConfigured()) {
                $previewGeneration = RoomPreviewGeneration::firstOrCreate(
                    ['room_image_id' => $roomImage->id, 'furniture_product_id' => $selectedProduct->id],
                    ['status' => 'queued'],
                );
                $newGeneration = $previewGeneration->wasRecentlyCreated;
                $isActive = in_array($previewGeneration->status, ['queued', 'processing', 'retrying'], true);
                $canRetry = ! $previewGeneration->updated_at || $previewGeneration->updated_at->lt(now()->subMinutes(10));
                if ($newGeneration || (! $isActive && ($previewGeneration->status !== 'completed' || ! $generatedPreview) && $canRetry && $canAttempt)) {
                    $previewGeneration->update(['status' => 'queued', 'message' => null]);
                    GenerateRoomPreview::dispatch($roomImage->id, $selectedProduct->id);
                    $previewGeneration->refresh();
                } elseif ($previewGeneration->status === 'queued' && $canRetry) {
                    GenerateRoomPreview::dispatch($roomImage->id, $selectedProduct->id);
                }
            }
        }

        $geminiPreviewUnavailable = $recommendation && $selectedProduct && $roomImage->gemini_consent_at && $gemini->isConfigured() && ! $generatedPreview;
        $selectedPlacement = $selectedProduct
            ? $placementService->forProduct(
                $roomImage->analysis ?? [],
                $selectedProduct,
                $roomImage->room_width_m ? (float) $roomImage->room_width_m : null,
                $roomImage->room_depth_m ? (float) $roomImage->room_depth_m : null,
            )
            : null;

        return view('room.visualize', compact('roomImage', 'selectedProduct', 'recommendation', 'generatedPreview', 'generatedWithGemini', 'geminiPreviewUnavailable', 'selectedPlacement', 'previewGeneration'));
    }

    public function destroy(Request $request, RoomImage $roomImage, RoomPhotoService $photos, PrivacyAudit $audit)
    {
        abort_unless($roomImage->user_id === $request->user()->id, 403);
        $roomId = $roomImage->id;
        $photos->deleteRoomFiles($roomImage);
        $roomImage->delete();
        $audit->record('room_photo_deleted', 'room_image', $roomId);

        return redirect()->route('preferences.edit')->with('success', 'The room photo and generated previews were deleted.');
    }

    public function revokeAiConsent(Request $request, RoomImage $roomImage, RoomPhotoService $photos, PrivacyAudit $audit, GeminiService $gemini)
    {
        abort_unless($roomImage->user_id === $request->user()->id, 403);
        $photos->deleteGeneratedPreviews($roomImage);
        RoomPreviewGeneration::where('room_image_id', $roomImage->id)->whereIn('status', ['queued', 'processing', 'retrying'])
            ->update(['status' => 'cancelled', 'message' => 'AI processing is disabled for this photo.']);
        $analysis = $gemini->roomFallback(
            $roomImage->room_width_m ? (float) $roomImage->room_width_m : null,
            $roomImage->room_depth_m ? (float) $roomImage->room_depth_m : null,
            $request->user()->preference?->furniture_type,
        );
        $roomImage->update([
            'gemini_consent_at' => null,
            'gemini_consent_version' => null,
            'analysis' => $analysis,
            'analysis_status' => 'fallback',
            'analysis_error' => 'AI processing disabled by you; local planning guidance is being used.',
            'analyzed_at' => now(),
        ]);
        $audit->record('room_photo_gemini_consent_revoked', 'room_image', $roomImage->id);

        return back()->with('success', 'AI processing is disabled for this photo. Future AI requests are stopped and generated previews were deleted.');
    }

    public function reanalyze(Request $request, RoomImage $roomImage)
    {
        abort_unless($roomImage->user_id === $request->user()->id, 403);
        abort_if(in_array($roomImage->analysis_status, ['queued', 'processing', 'retrying'], true), 422, 'Room analysis is already in progress.');

        $furnitureType = $request->user()->preference?->furniture_type
            ?: data_get($roomImage->analysis, 'analyzed_furniture_type');
        $roomImage->update([
            'analysis_status' => 'queued',
            'analysis_error' => null,
            'analysis_requested_at' => now(),
        ]);
        AnalyzeRoomImage::dispatch($roomImage->id, $furnitureType);
        $roomImage->refresh();

        $message = $roomImage->analysis_status === 'completed'
            ? 'Room analysis completed successfully.'
            : ($roomImage->analysis_status === 'fallback'
                ? 'Local room guidance is ready; Gemini is not configured.'
                : 'Room re-analysis has been queued.');

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'status' => $roomImage->analysis_status])
            : back()->with('success', $message);
    }

    public function analysisStatus(Request $request, RoomImage $roomImage)
    {
        abort_unless($roomImage->user_id === $request->user()->id, 403);

        return response()->json([
            'status' => $roomImage->analysis_status,
            'attempts' => $roomImage->analysis_attempts,
            'message' => $roomImage->analysis_error,
            'ready' => in_array($roomImage->analysis_status, ['completed', 'fallback'], true),
            'analyzed_at' => $roomImage->analyzed_at?->toIso8601String(),
        ]);
    }

    public function previewStatus(Request $request, RoomImage $roomImage, int $product)
    {
        abort_unless($roomImage->user_id === $request->user()->id, 403);
        abort_unless($roomImage->gemini_consent_at, 404);
        $generation = RoomPreviewGeneration::where('room_image_id', $roomImage->id)
            ->where('furniture_product_id', $product)->firstOrFail();
        $path = data_get($roomImage->analysis, 'generated_previews.'.$product.'.path');

        return response()->json([
            'status' => $generation->status,
            'message' => $generation->message,
            'preview_url' => $generation->status === 'completed' && $path
                ? route('room-images.preview', [$roomImage, $product])
                : null,
        ]);
    }
}
