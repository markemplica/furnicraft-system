<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const SUBJECT = 'Reservation confirmed - you may visit your selected branch';

    public function __construct(public Reservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(self::SUBJECT)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Good news! The owner has confirmed your FurniCraft reservation.')
            ->line("Reservation reference: {$this->reservation->reference}")
            ->line("Furniture: {$this->reservation->product->name}")
            ->line("Selected branch: {$this->reservation->branch}")
            ->line("You may now visit the {$this->reservation->branch} branch you selected as the most convenient location.")
            ->line('Please bring your reservation reference and contact the branch before visiting to confirm its operating hours and item readiness.')
            ->line('This is a reservation hold only. No payment has been collected through FurniCraft.')
            ->action('View confirmed reservation', route('reservations.index'))
            ->salutation('Lance Furniture Shop');
    }
}
