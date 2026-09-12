<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The Password::uncompromised() rule calls the HaveIBeenPwned range API.
        // Never let that hit the real network from tests.
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }

    public function test_register_rejects_weak_password(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Usuario Prueba',
            'email' => 'weak-password@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'weak-password@gmail.com']);
    }

    public function test_register_accepts_strong_password(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Usuario Fuerte',
            'email' => 'strong-password@gmail.com',
            'password' => 'Str0ng!Passw0rd#Secure',
            'password_confirmation' => 'Str0ng!Passw0rd#Secure',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'strong-password@gmail.com']);
    }

    public function test_password_reset_rejects_weak_password(): void
    {
        $user = User::factory()->create(['email' => 'reset-weak@example.com']);
        $token = Password::broker()->createToken($user);

        $response = $this->from('/password/reset/'.$token)->post('/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_forgot_password_gives_identical_response_for_known_and_unknown_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'known-user@example.com']);

        $known = $this->post('/password/email', ['email' => $user->email]);
        $known->assertSessionHasNoErrors();
        $known->assertSessionHas('status', trans('passwords.sent'));

        $unknown = $this->post('/password/email', ['email' => 'definitely-not-registered@example.com']);
        $unknown->assertSessionHasNoErrors();
        $unknown->assertSessionHas('status', trans('passwords.sent'));

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
    }

    public function test_password_recovery_routes_are_throttled(): void
    {
        $emailRoute = Route::getRoutes()->getByName('password.email');
        $updateRoute = Route::getRoutes()->getByName('password.update');
        $resendRoute = Route::getRoutes()->getByName('verification.resend');

        $this->assertNotNull($emailRoute);
        $this->assertNotNull($updateRoute);
        $this->assertNotNull($resendRoute);

        $this->assertContains('throttle:6,1', $emailRoute->gatherMiddleware());
        $this->assertContains('throttle:6,1', $updateRoute->gatherMiddleware());
        $this->assertContains('throttle:6,1', $resendRoute->gatherMiddleware());
    }
}
