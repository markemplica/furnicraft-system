<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your FurniCraft password reset code')
            ->greeting('FurniCraft password reset')
            ->line('Use this verification code to reset your password:')
            ->line($this->code)
            ->line('The code expires in 15 minutes and can only be used once. If you did not request it, you can ignore this email.');
    }
}