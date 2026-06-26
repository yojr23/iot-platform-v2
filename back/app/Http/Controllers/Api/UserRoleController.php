<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => $this->userPayload($user))
                ->values(),
        ]);
    }

    public function update(Request $request, User $user)
    {
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
        });

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
