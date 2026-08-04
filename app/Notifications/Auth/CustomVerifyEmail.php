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
            ->subject('Verifikasi email akun '.config('app.name', 'P-QUEST'))
            ->view('emails.auth.verify-email', [
                'appName' => config('app.name', 'P-QUEST'),
                'userName' => $notifiable->name ?? 'Adventurer',
                'verificationUrl' => $this->verificationUrl($notifiable),
            ]);
    }
}
