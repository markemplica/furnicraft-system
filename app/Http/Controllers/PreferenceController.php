<?php

namespace App\Http\Controllers;

use App\Models\FurnitureProduct;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public function edit(Request $request)
    {
        $preference = $request->user()->preference;
        $styles = FurnitureProduct::where('status', 'active')->distinct()->orderBy('style')->pluck('style');
        $rooms = $request->user()->roomImages()->latest()->get();
        $selectedRoomId = $rooms->contains('id', $request->integer('room')) ? $request->integer('room') : null;

        return view('preferences.edit', compact('preference', 'styles', 'rooms', 'selectedRoomId'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'budget_min' => ['required', 'numeric', 'min:0'],
            'budget_max' => ['required', 'numeric', 'gt:budget_min'],
            'style' => ['nullable', 'string', 'max:80'],
            'wood_type' => ['nullable', Rule::in(['Gmelina', 'Mahogany'])],
            'varnish_finish' => ['nullable', Rule::in(['Natural', 'Narra Look', 'Mahogany', 'Chocolate Brown'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->preference()->updateOrCreate([], $data);

        return redirect()
            ->to(route('preferences.edit').'#room-preview')
            ->with('success', 'Preferences saved. Next, upload a room image for space analysis.');
    }
}
