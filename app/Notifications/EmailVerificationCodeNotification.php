<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your FurniCraft account')
            ->greeting('Welcome to FurniCraft')
            ->line('Enter this verification code to activate your account:')
            ->line($this->code)
            ->line('The code expires in 15 minutes and can only be used once. If you did not create this account, you can ignore this email.');
    }
}