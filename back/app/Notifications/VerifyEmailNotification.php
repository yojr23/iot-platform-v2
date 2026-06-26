<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends BaseVerifyEmail
{
    /**
     * Build an API verification URL for headless frontend flows.
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'api.auth.verify-email',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Build the verification email message in Spanish.
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifica tu correo electrónico')
            ->line('Por favor, haz clic en el siguiente botón para verificar tu dirección de correo electrónico.')
            ->action('Verificar correo electrónico', $url)
            ->line('Si no creaste una cuenta, no es necesario realizar ninguna otra acción.');
    }
}
