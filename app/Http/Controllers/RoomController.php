<?php

namespace App\Http\Controllers;

use App\Models\FurnitureProduct;
use App\Models\Recommendation;
use App\Models\RoomImage;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->to(route('preferences.edit').'#room-preview');
    }

    public function store(Request $request, GeminiService $gemini)
    {
        $data = $request->validate(['room_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'room_width_m' => ['nullable', 'numeric', 'min:1', 'max:50'], 'room_depth_m' => ['nullable', 'numeric', 'min:1', 'max:50'], 'return_to' => ['nullable', 'in:recommendations']]);
        if (! $request->user()->preference) {
            return redirect()->to(route('preferences.edit').'#preference-form')->withErrors(['workflow' => 'Save your furniture preferences before uploading a room image.']);
        }

        $path = $request->file('room_image')->store('rooms/'.$request->user()->id, 'public');
        $analysis = $gemini->analyzeRoom($path, isset($data['room_width_m']) ? (float) $data['room_width_m'] : null, isset($data['room_depth_m']) ? (float) $data['room_depth_m'] : null);
        $roomWidth = $data['room_width_m'] ?? data_get($analysis, 'estimated_width_m');
        $roomDepth = $data['room_depth_m'] ?? data_get($analysis, 'estimated_depth_m');
        $room = RoomImage::create(['user_id' => $request->user()->id, 'image_path' => $path, 'room_width_m' => $roomWidth, 'room_depth_m' => $roomDepth, 'analysis' => $analysis, 'analyzed_at' => now()]);

        if (($data['return_to'] ?? null) === 'recommendations') {
            return redirect()->to(route('preferences.edit', ['room' => $room->id]).'#recommendation-form')->with('success', 'Your room is ready and selected for recommendations.');
        }

        return redirect()->route('room.visualize', $room)->with('success', 'Your room image has been analyzed.');
    }

    public function visualize(Request $request, RoomImage $roomImage)
    {
        abort_unless($roomImage->user_id === $request->user()->id, 403);

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

        $selectedProduct = $products->firstWhere('id', $request->integer('product')) ?? $products->first();

        return view('room.visualize', compact('roomImage', 'products', 'selectedProduct', 'recommendation'));
    }
}
