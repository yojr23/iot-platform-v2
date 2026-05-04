<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_request_sends_spanish_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset-user@example.com',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status', trans('passwords.sent'));

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification, array $channels) use ($user): bool {
                $mailMessage = $notification->toMail($user);

                return in_array('mail', $channels, true)
                    && $mailMessage->subject === 'Restablecer contraseña'
                    && in_array('Recibiste este correo porque se solicitó restablecer la contraseña de tu cuenta.', $mailMessage->introLines, true)
                    && $mailMessage->actionText === 'Restablecer contraseña'
                    && in_array('Si no solicitaste restablecer tu contraseña, no es necesario realizar ninguna otra acción.', $mailMessage->outroLines, true);
            }
        );
    }

    public function test_password_reset_updates_password_in_database(): void
    {
        $user = User::factory()->create([
            'email' => 'password-update@example.com',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NuevaClaveSegura123!',
            'password_confirmation' => 'NuevaClaveSegura123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClaveSegura123!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));
    }
}
