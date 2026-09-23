<?php

namespace App\Http\Controllers;

use App\Models\FurnitureProduct;
use App\Models\Recommendation;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        return view('reservations.index', ['reservations' => $request->user()->reservations()->with('product')->latest()->paginate(12)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['furniture_product_id' => ['required', 'exists:furniture_products,id'], 'recommendation_id' => ['nullable', 'exists:recommendations,id'], 'branch' => ['required', Rule::in(['Lingayen', 'Calasiao', 'San Jacinto', 'Sta. Barbara', 'Pozorrubio', 'Tarlac'])], 'notes' => ['nullable', 'string', 'max:1000']]);
        $product = FurnitureProduct::where('status', 'active')->findOrFail($data['furniture_product_id']);
        if (! empty($data['recommendation_id'])) {
            $recommendation = Recommendation::where('user_id', $request->user()->id)->findOrFail($data['recommendation_id']);
            abort_unless($recommendation->items()->where('furniture_product_id', $product->id)->exists(), 422, 'That item is not part of this recommendation.');
        }
        $reservation = Reservation::create(array_merge($data, ['reference' => 'FC-'.now()->format('ymd').'-'.strtoupper(Str::random(6)), 'user_id' => $request->user()->id]));
        $reservation->transactions()->create(['actor_id' => $request->user()->id, 'action' => 'submitted', 'notes' => 'Reservation request submitted by customer.']);

        return redirect()->route('reservations.index')->with('success', "Reservation {$reservation->reference} was submitted for review. No payment has been collected.");
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $request->user()->id, 403);
        abort_unless($reservation->status === 'pending', 422, 'Only pending reservations can be cancelled.');
        $reservation->update(['status' => 'cancelled']);
        $reservation->transactions()->create(['actor_id' => $request->user()->id, 'action' => 'cancelled', 'notes' => 'Cancelled by customer.']);

        return back()->with('success', 'The reservation request was cancelled.');
    }
}
