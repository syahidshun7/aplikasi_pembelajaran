<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmail extends VerifyEmail
{
    use Queueable;

    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('AUTHENTICATION_REQUIRED: Verify Email')
            ->view('emails.auth.verify-email', [
                'appName' => config('app.name', 'P-QUEST'),
                'userName' => $notifiable->name ?? 'Adventurer',
                'verificationUrl' => $this->verificationUrl($notifiable),
            ]);
    }
}
