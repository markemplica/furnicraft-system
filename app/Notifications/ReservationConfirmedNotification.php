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

    public const SUBJECT = 'Reservation request approved - confirm item readiness';

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
            ->line('Good news! Shop staff approved your FurniCraft reservation request.')
            ->line("Reservation reference: {$this->reservation->reference}")
            ->line("Furniture: {$this->reservation->product->name}")
            ->line("Selected shop: {$this->reservation->branch}")
            ->line('This approval confirms the request review only. FurniCraft does not track inventory, and this message does not guarantee that the item is physically in stock or ready.')
            ->line("Contact Lance Furniture Shop before visiting to confirm physical availability, item readiness, and operating hours. Share your reservation reference: {$this->reservation->reference}.")
            ->line('No purchase or payment has been made through FurniCraft.')
            ->action('View reservation request', rtrim((string) config('app.url'), '/').route('reservations.index', [], false))
            ->salutation('Lance Furniture Shop');
    }
}
