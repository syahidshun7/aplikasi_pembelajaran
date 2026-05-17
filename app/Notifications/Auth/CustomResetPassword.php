<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPassword
{
    use Queueable;

    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $passwordBroker = config('auth.defaults.passwords', 'users');
        $expireMinutes = (int) config("auth.passwords.{$passwordBroker}.expire", 60);

        return (new MailMessage)
            ->subject('AUTHENTICATION_REQUIRED: Reset Password')
            ->view('emails.auth.reset-password', [
                'appName' => config('app.name', 'P-QUEST'),
                'userName' => $notifiable->name ?? 'Adventurer',
                'resetUrl' => $this->resetUrl($notifiable),
                'expireMinutes' => $expireMinutes,
            ]);
    }
}
