<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );

        return $url;
    }

    protected function buildMailMessage($url): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Verify your WBT Forge email address')
            ->greeting('Welcome to WBT Forge!')
            ->line('Click the button below to verify your email address and activate your account.')
            ->action('Verify Email', $url)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}
