<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return config('app.frontend_url')
            . '/reset-password'
            . '?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your WBT Forge password')
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line('We received a request to reset your password.')
            ->action('Reset Password', $this->resetUrl($notifiable))
            ->line('This link expires in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
