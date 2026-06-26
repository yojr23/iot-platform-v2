<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthApiHeadlessTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_register_creates_unverified_user_returns_token_and_sends_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Usuario API',
            'email' => 'usuario.api@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'spa-client',
        ]);

        $user = User::where('email', 'usuario.api@gmail.com')->firstOrFail();

        $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonStructure(['access_token']);

        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            function (VerifyEmailNotification $notification) use ($user): bool {
                $mailMessage = $notification->toMail($user);

                return str_contains($mailMessage->actionUrl, '/api/auth/verify-email/'.$user->id.'/');
            }
        );
    }

    public function test_api_register_rejects_disallowed_email_domain(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Usuario API',
            'email' => 'usuario.api@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseMissing('users', [
            'email' => 'usuario.api@example.com',
        ]);

        Notification::assertNothingSent();
    }

    public function test_api_login_rejects_unverified_user(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified-user@gmail.com',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Email no verificado.');
    }

    public function test_api_forgot_password_sends_frontend_reset_link(): void
    {
        Notification::fake();
        config()->set('app.front_url', 'http://localhost:5173');

        $user = User::factory()->create([
            'email' => 'reset-api@gmail.com',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user): bool {
                $mailMessage = $notification->toMail($user);

                return str_starts_with($mailMessage->actionUrl, 'http://localhost:5173/reset-password?')
                    && str_contains($mailMessage->actionUrl, 'email='.urlencode($user->email));
            }
        );
    }

    public function test_api_reset_password_updates_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-update-api@gmail.com',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NuevaClaveSegura123!',
            'password_confirmation' => 'NuevaClaveSegura123!',
        ])->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();

        $this->assertTrue(Hash::check('NuevaClaveSegura123!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));
    }

    public function test_api_verify_email_marks_user_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'verify-api@gmail.com',
        ]);

        $url = URL::temporarySignedRoute(
            'api.auth.verify-email',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Email verificado correctamente.');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_api_resend_verification_notification_uses_bearer_token(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'resend-api@gmail.com',
        ]);
        $token = $user->createToken('spa-client')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/resend-verification')
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
