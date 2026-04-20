<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.url') . '/api/auth/verify-email?token=' . $this->token;

        return (new MailMessage)
            ->subject('Verify your WBT Forge email address')
            ->greeting('Welcome to WBT Forge!')
            ->line('Click the button below to verify your email address and activate your account.')
            ->action('Verify Email', $url)
            ->line('This link expires in 24 hours.')
            ->line('If you did not create an account, no further action is required.');
    }
}
