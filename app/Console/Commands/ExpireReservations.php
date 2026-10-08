<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';
    protected $description = 'Expire pending reservation requests past their seven day response window';

    public function handle(): int
    {
        $count = 0;
        Reservation::where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($reservations) use (&$count) {
            foreach ($reservations as $reservation) {
                DB::transaction(function () use ($reservation, &$count) {
                    $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->first();
                    if (! $reservation || $reservation->status !== 'pending' || ! $reservation->expires_at || $reservation->expires_at->isFuture()) return;
                    $reservation->update(['status' => 'cancelled', 'cancellation_reason' => 'Automatically expired after seven days without a decision.']);
                    $reservation->transactions()->create(['action' => 'expired', 'notes' => 'Automatically expired after seven days without a decision.']);
                    $count++;
                });
            }
        });
        $this->info("Expired {$count} pending reservation requests.");
        return self::SUCCESS;
    }
}
