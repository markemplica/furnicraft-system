<?php
namespace App\Notifications;
use App\Models\Reservation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class ReservationVisitReminderNotification extends Notification
{
 public function __construct(public Reservation $reservation) {}
 public function via(object $notifiable): array { return ['mail']; }
 public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject('Reminder: planned visit to Lance Furniture Shop')->greeting('Hello '.$notifiable->name.'!')->line('Your preferred visit time is coming up: '.$this->reservation->preferred_visit_at?->format('F j, Y g:i A').'.')->line('Please contact Lance Furniture Shop to reconfirm item availability and readiness before travelling. FurniCraft does not track inventory.')->line('Reservation reference: '.$this->reservation->reference)->action('View reservation request', rtrim((string) config('app.url'), '/').route('reservations.index', [], false)); }
}
