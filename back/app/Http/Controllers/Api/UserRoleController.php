<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function index()
    {
        $startTime = microtime(true);

        $data = User::with('role.permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->userPayload($user))
            ->values();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('UserRole index request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->attributes->get('request_id'),
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
            'role_code' => ['required', 'string', 'exists:roles,code'],
        ]);

        $currentUser = $request->user();
        $newRole = Role::where('code', $validated['role_code'])->firstOrFail();

        // Reject non-assignable roles
        if (! $newRole->assignable) {
            throw ValidationException::withMessages([
                'role_code' => 'Este rol no puede ser asignado.',
            ]);
        }

        // Check if actor can assign this role
        if (! $currentUser->canAssignRole($newRole->code)) {
            throw ValidationException::withMessages([
                'role_code' => 'No tienes permiso para asignar este rol.',
            ]);
        }

        // Check if actor can manage the target user
        if (! $currentUser->canManageRole($user)) {
            throw ValidationException::withMessages([
                'role_code' => 'No puedes modificar el rol de este usuario.',
            ]);
        }

        // Prevent self-demotion from superadmin
        if ($currentUser->id === $user->id && $currentUser->role?->code === 'superadmin' && $newRole->code !== 'superadmin') {
            throw ValidationException::withMessages([
                'role_code' => 'No puedes retirar tu propio rol de superadministrador.',
            ]);
        }

        // Last SuperAdmin protection — locked inside transaction to prevent race condition
        $oldRoleCode = $user->role?->code;

        DB::transaction(function () use ($user, $newRole, $request, $currentUser, $oldRoleCode): void {
            if ($user->role?->code === 'superadmin' && $newRole->code !== 'superadmin') {
                $superadminCount = User::where('role_id', $user->role_id)->lockForUpdate()->count();
                if ($superadminCount <= 1) {
                    throw ValidationException::withMessages([
                        'role_code' => 'Debe existir al menos un superadministrador activo en la plataforma.',
                    ]);
                }
            }

            $user->role_id = $newRole->id;

            // Sync is_admin for backward compatibility
            $user->is_admin = in_array($newRole->code, ['admin', 'superadmin'], true);

            $user->save();

            // Revoke all tokens on role change
            $user->tokens()->delete();
        });

        // Audit log
        $this->auditService->logRoleChange(
            $user->id,
            $oldRoleCode,
            $newRole->code,
            $request
        );

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('UserRole update request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->attributes->get('request_id'),
            'user_id' => auth()->id(),
            'target_user_id' => $user->id,
            'old_role' => $oldRoleCode,
            'new_role' => $newRole->code,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $this->userPayload($user->fresh()->load('role.permissions')),
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
            'role' => $user->role ? [
                'code' => $user->role->code,
                'name' => $user->role->name,
                'level' => $user->role->level,
            ] : null,
            'permissions' => $user->getAllPermissions()->toArray(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
