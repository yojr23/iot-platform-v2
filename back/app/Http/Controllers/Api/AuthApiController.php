<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthApiController extends Controller
{
    private const ALLOWED_EMAIL_DOMAINS = [
        'gmail.com',
        'hotmail.com',
        'outlook.com',
        'unab.edu.co',
    ];

    public function login(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $context = [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
        ];

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('Auth login failed: invalid credentials', $context + [
                'email' => $validated['email'],
                'success' => false,
                'duration_ms' => $durationMs,
            ]);

            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('Auth login failed: email not verified', $context + [
                'user_id' => $user->id,
                'success' => false,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'message' => 'Email no verificado.',
            ], 403);
        }

        $tokenName = $validated['device_name'] ?? 'api-client';
        $abilities = $user->is_admin ? ['*'] : ['read'];
        $plainTextToken = $user->createToken($tokenName, $abilities)->plainTextToken;

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Auth login success', $context + [
            'user_id' => $user->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json($this->tokenResponse($user, $plainTextToken));
    }

    public function register(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $context = [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
        ];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->hasAllowedEmailDomain((string) $value)) {
                        $fail('Solo se permiten correos con dominio @gmail.com, @hotmail.com, @outlook.com o @unab.edu.co.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            event(new Registered($user));

        } catch (Throwable $e) {
            Log::error('Auth register error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Registration error',
                'message' => 'No fue posible registrar el usuario.',
            ], 500);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Auth register success', $context + [
            'user_id' => $user->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'message' => 'Usuario registrado correctamente. Verifica tu correo electrónico.',
            'verification_required' => true,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('Auth forgotPassword failed', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'status' => $status,
                'duration_ms' => $durationMs,
            ]);

            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Auth forgotPassword success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'message' => trans($status),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // SEC-TOKEN-001: a password reset must invalidate every previously issued
                // Sanctum PAT — a stolen/older token must stop working once the password
                // changes, otherwise the reset gives no real security benefit.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('Auth resetPassword failed', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'status' => $status,
                'duration_ms' => $durationMs,
            ]);

            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Auth resetPassword success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'message' => trans($status),
        ]);
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $startTime = microtime(true);

        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('Auth verifyEmail failed: invalid link', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => $id,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'message' => 'Enlace de verificación inválido.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Auth verifyEmail success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => $user->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'message' => 'Email verificado correctamente.',
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Auth resendVerificationEmail: already verified', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => $user->id,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'message' => 'El email ya está verificado.',
            ], 409);
        }

        $user->sendEmailVerificationNotification();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Auth resendVerificationEmail success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => $user->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'message' => 'Email de verificación reenviado correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Auth me request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Auth logout success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'message' => 'Sesión API cerrada correctamente.',
        ]);
    }

    private function hasAllowedEmailDomain(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return in_array($domain, self::ALLOWED_EMAIL_DOMAINS, true);
    }

    /**
     * @return array<string,mixed>
     */
    private function tokenResponse(User $user, string $plainTextToken): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $plainTextToken,
            'user' => $this->userPayload($user),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }
}
