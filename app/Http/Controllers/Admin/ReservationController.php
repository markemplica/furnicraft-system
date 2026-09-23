<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Reservation;
use App\Notifications\ReservationConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $reservations = Reservation::with('user', 'product')->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(15)->withQueryString();

        return view('admin.reservations.index', compact('reservations'));
    }

    public function update(Request $request, Reservation $reservation)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'confirmed', 'declined'])], 'notes' => ['nullable', 'string', 'max:1000']]);
        $previousStatus = $reservation->status;
        $wasConfirmed = $previousStatus === 'confirmed';
        $reservation->update(['status' => $data['status'], 'confirmed_at' => $data['status'] === 'confirmed' ? ($reservation->confirmed_at ?: now()) : null, 'confirmed_by' => $data['status'] === 'confirmed' ? $request->user()->id : null]);
        $reservation->transactions()->create(['actor_id' => $request->user()->id, 'action' => $data['status'], 'notes' => $data['notes'] ?? null, 'metadata' => ['previous_status' => $previousStatus]]);
        if ($data['status'] === 'confirmed' && ! $wasConfirmed) {
            $reservation->load('user', 'product');
            $log = NotificationLog::create([
                'user_id' => $reservation->user_id,
                'type' => 'reservation_confirmed',
                'recipient' => $reservation->user->email,
                'subject' => ReservationConfirmedNotification::SUBJECT,
                'status' => 'queued',
                'payload' => [
                    'reservation' => $reservation->reference,
                    'product' => $reservation->product->name,
                    'branch' => $reservation->branch,
                    'confirmed_at' => $reservation->confirmed_at?->toIso8601String(),
                    'next_step' => 'visit_selected_branch',
                ],
            ]);
            $reservation->user->notify(new ReservationConfirmedNotification($reservation));
            if (config('queue.default') === 'sync') {
                $log->update(['status' => 'sent', 'sent_at' => now()]);
            }
        }

        return back()->with('success', "Reservation {$reservation->reference} is now {$reservation->status}.");
    }
}
