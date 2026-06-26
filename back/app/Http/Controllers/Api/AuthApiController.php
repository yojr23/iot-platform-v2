<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email no verificado.',
            ], 403);
        }

        $tokenName = $validated['device_name'] ?? 'api-client';
        $abilities = $user->is_admin ? ['*'] : ['read'];
        $plainTextToken = $user->createToken($tokenName, $abilities)->plainTextToken;

        return response()->json($this->tokenResponse($user, $plainTextToken));
    }

    public function register(Request $request): JsonResponse
    {
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

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        $tokenName = $validated['device_name'] ?? 'spa-client';
        $plainTextToken = $user->createToken($tokenName, ['read'])->plainTextToken;

        return response()->json($this->tokenResponse($user, $plainTextToken) + [
            'message' => 'Usuario registrado correctamente. Verifica tu correo electrónico.',
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return response()->json([
            'message' => trans($status),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
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

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return response()->json([
            'message' => trans($status),
        ]);
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Enlace de verificación inválido.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verificado correctamente.',
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El email ya está verificado.',
            ], 409);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Email de verificación reenviado correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

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
