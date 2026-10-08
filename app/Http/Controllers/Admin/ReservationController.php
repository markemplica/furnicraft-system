<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Reservation;
use App\Notifications\ReservationConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'declined', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'decline_reason' => ['nullable', 'required_if:status,declined', 'string', 'max:1000'],
            'item_ready' => ['nullable', 'boolean'],
            'outcome' => ['nullable', Rule::in(['completed', 'no_show'])],
        ]);
        [$reservation, $log] = DB::transaction(function () use ($request, $reservation, $data) {
            $log = null;
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($reservation->status, ['pending', 'confirmed', 'declined'], true), 422, 'This reservation is already closed.');
            if (isset($data['outcome'])) {
                abort_unless($reservation->status === 'confirmed' && $data['status'] === 'confirmed', 422, 'Only confirmed reservations can be closed out.');
                $data['status'] = $data['outcome'];
            }
            if ($request->boolean('item_ready')) {
                abort_unless($reservation->status === 'confirmed' && $data['status'] === 'confirmed', 422, 'Shop readiness can only be recorded for a confirmed reservation.');
            }
            $previousStatus = $reservation->status;
            $wasConfirmed = $previousStatus === 'confirmed';
            $updates = ['status' => $data['status'], 'confirmed_at' => $data['status'] === 'confirmed' ? ($reservation->confirmed_at ?: now()) : $reservation->confirmed_at, 'confirmed_by' => $data['status'] === 'confirmed' ? ($reservation->confirmed_by ?: $request->user()->id) : $reservation->confirmed_by, 'decline_reason' => $data['decline_reason'] ?? $reservation->decline_reason];
            if ($request->boolean('item_ready')) $updates['item_ready_confirmed_at'] = now();
            if (($data['outcome'] ?? null) === 'completed') $updates['completed_at'] = now();
            if (($data['outcome'] ?? null) === 'no_show') $updates['no_show_at'] = now();
            $reservation->update($updates);
            $action = $data['outcome'] ?? ($request->boolean('item_ready') ? 'item_ready_confirmed' : $data['status']);
            $reservation->transactions()->create(['actor_id' => $request->user()->id, 'action' => $action, 'notes' => $data['notes'] ?? $data['decline_reason'] ?? null, 'metadata' => ['previous_status' => $previousStatus]]);
            if ($data['status'] === 'confirmed' && ! $wasConfirmed) {
                $reservation->load('user', 'product');
                $log = NotificationLog::create(['user_id' => $reservation->user_id, 'type' => 'reservation_confirmed', 'recipient' => $reservation->user->email, 'subject' => ReservationConfirmedNotification::SUBJECT, 'status' => 'queued', 'payload' => ['reservation' => $reservation->reference, 'product' => $reservation->product->name, 'branch' => $reservation->branch, 'confirmed_at' => $reservation->confirmed_at?->toIso8601String(), 'next_step' => 'contact_branch_to_confirm_item_readiness']]);
            }
            return [$reservation, $log];
        });
        if ($log) {
            $reservation->user->notify((new ReservationConfirmedNotification($reservation))->afterCommit());
            if (config('queue.default') === 'sync') {
                DB::afterCommit(fn () => $log->update(['status' => 'sent', 'sent_at' => now()]));
            }
        }
        $statusMessage = match ($reservation->status) {
            'confirmed' => 'request approved',
            'completed' => 'completed / claimed',
            'no_show' => 'recorded as no-show',
            default => $reservation->status,
        };
        return back()->with('success', "Reservation {$reservation->reference} is now {$statusMessage}.");
    }
}
