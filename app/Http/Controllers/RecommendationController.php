<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
use App\Services\FurniturePlacementService;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(Request $request)
    {
        $recommendations = $request->user()->recommendations()->withCount('items')->latest()->paginate(10);

        return view('recommendations.index', compact('recommendations'));
    }

    public function store(Request $request, RecommendationService $service)
    {
        $preference = $request->user()->preference()->first();
        if (! $preference || blank($preference->furniture_type)) {
            return redirect()->to(route('preferences.edit').'#preference-form')->withErrors(['workflow' => 'Save your furniture preferences before generating recommendations.']);
        }

        $data = $request->validate([
            'room_image_id' => ['required', 'integer'],
        ]);

        $room = $request->user()->roomImages()->findOrFail($data['room_image_id']);
        if (in_array($room->analysis_status, ['queued', 'processing', 'retrying'], true)) {
            return redirect()->to(route('preferences.edit', ['room' => $room->id]).'#room-preview')
                ->withErrors(['room_image_id' => 'Room analysis is still running. Recommendations will be available as soon as it finishes.']);
        }
        $preference->update([
            'room_width_m' => $room->room_width_m ?: $preference->room_width_m,
            'room_depth_m' => $room->room_depth_m ?: $preference->room_depth_m,
        ]);
        $recommendation = $service->generate($request->user(), $preference, $room, $preference->furniture_type);

        return redirect()->route('recommendations.show', $recommendation)->with('success', 'Your recommendations and room preview are ready.');
    }

    public function show(Request $request, Recommendation $recommendation, FurniturePlacementService $placementService)
    {
        abort_unless($recommendation->user_id === $request->user()->id, 403);
        $recommendation->load('items.product', 'messages', 'preference', 'roomImage');
        $room = $recommendation->roomImage;
        $placements = $room
            ? $recommendation->items->mapWithKeys(fn ($item) => [
                $item->product->id => $placementService->forProduct(
                    $room->analysis ?? [],
                    $item->product,
                    $room->room_width_m ? (float) $room->room_width_m : null,
                    $room->room_depth_m ? (float) $room->room_depth_m : null,
                ),
            ])
            : collect();

        return view('recommendations.show', compact('recommendation', 'placements'));
    }
}
