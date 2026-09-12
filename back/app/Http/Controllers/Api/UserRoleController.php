<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function index()
    {
        $startTime = microtime(true);

        $data = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->userPayload($user))
            ->values();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('UserRole index request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'count' => $data->count(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, User $user)
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $requestedAdminValue = (bool) $validated['is_admin'];
        $currentUser = $request->user();

        if ($currentUser && $currentUser->id === $user->id && ! $requestedAdminValue) {
            throw ValidationException::withMessages([
                'is_admin' => 'No puedes retirarte tu propio rol de administrador.',
            ]);
        }

        if (! $requestedAdminValue && $user->is_admin && User::where('is_admin', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'is_admin' => 'Debe existir al menos un administrador activo en la plataforma.',
            ]);
        }

        DB::transaction(function () use ($user, $requestedAdminValue): void {
            $isMysql = DB::getDriverName() === 'mysql';

            if ($isMysql) {
                DB::statement('SET @allow_admin_role_change = 1');
            }

            try {
                $user->is_admin = $requestedAdminValue;
                $user->save();
            } finally {
                if ($isMysql) {
                    DB::statement('SET @allow_admin_role_change = 0');
                }
            }

            // SEC-TOKEN-001: a previously issued token for this user was minted with the
            // abilities of the *old* role (e.g. a `['*']` admin PAT). Revoke every existing
            // token on any role change so a stale token can't keep exercising privileges
            // the user's current role no longer has. A promoted user simply re-logs in to
            // get a token reflecting the new role.
            $user->tokens()->delete();
        });

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('UserRole update request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'target_user_id' => $user->id,
            'is_admin' => $requestedAdminValue,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $this->userPayload($user->fresh()),
            'message' => 'Rol de usuario actualizado correctamente.',
        ]);
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
            'role' => $user->is_admin ? 'Administrador' : 'Usuario',
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
