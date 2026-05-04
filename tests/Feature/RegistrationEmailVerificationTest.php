<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_sends_email_verification_notification(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Usuario Prueba',
            'email' => 'usuario.prueba@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'usuario.prueba@gmail.com')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            function (VerifyEmailNotification $notification, array $channels) use ($user): bool {
                $mailMessage = $notification->toMail($user);

                return in_array('mail', $channels, true)
                    && $mailMessage->subject === 'Verifica tu correo electrónico'
                    && in_array('Por favor, haz clic en el siguiente botón para verificar tu dirección de correo electrónico.', $mailMessage->introLines, true)
                    && $mailMessage->actionText === 'Verificar correo electrónico'
                    && in_array('Si no creaste una cuenta, no es necesario realizar ninguna otra acción.', $mailMessage->outroLines, true);
            }
        );
    }

    public function test_register_rejects_email_domains_outside_allowed_list(): void
    {
        Notification::fake();

        $response = $this->from('/register')->post('/register', [
            'name' => 'Usuario Prueba',
            'email' => 'usuario.prueba@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'email' => 'Solo se permiten correos con dominio @gmail.com, @hotmail.com, @outlook.com o @unab.edu.co.',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'usuario.prueba@example.com',
        ]);
        Notification::assertNothingSent();
    }

    public function test_unverified_user_cannot_access_protected_dashboard_preferences(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/dashboard/preferences');

        $response->assertRedirect(route('verification.notice'));
    }
}
