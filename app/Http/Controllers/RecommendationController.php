<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
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
        $data = $request->validate([
            'room_image_id' => ['required', 'integer'],
        ]);
        $preference = $request->user()->preference;
        if (! $preference) {
            return redirect()->to(route('preferences.edit').'#preference-form')->withErrors(['workflow' => 'Save your furniture preferences before generating recommendations.']);
        }

        $room = $request->user()->roomImages()->findOrFail($data['room_image_id']);
        $preference->update([
            'room_width_m' => $room->room_width_m ?: $preference->room_width_m,
            'room_depth_m' => $room->room_depth_m ?: $preference->room_depth_m,
        ]);
        $recommendation = $service->generate($request->user(), $preference, $room);

        return redirect()->route('recommendations.show', $recommendation)->with('success', 'Your recommendations and room preview are ready.');
    }

    public function show(Request $request, Recommendation $recommendation)
    {
        abort_unless($recommendation->user_id === $request->user()->id, 403);
        $recommendation->load('items.product', 'messages', 'preference', 'roomImage');

        return view('recommendations.show', compact('recommendation'));
    }
}
