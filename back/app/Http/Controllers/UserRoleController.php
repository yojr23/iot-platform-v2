<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index()
    {
        $users = User::with('role')->orderBy('name')->get();

        return view('config.user_roles', compact('users'));
    }

    public function update(Request $request, User $user)
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $requestedAdminValue = (bool) $validated['is_admin'];
        $currentUser = $request->user();

        // Determine target role from the toggle
        $targetRoleCode = $requestedAdminValue ? 'admin' : 'user';
        $targetRole = Role::where('code', $targetRoleCode)->firstOrFail();

        if ($currentUser && $currentUser->id === $user->id && ! $requestedAdminValue) {
            return back()->withErrors([
                'is_admin' => 'No puedes retirarte tu propio rol de administrador.',
            ]);
        }

        if (! $requestedAdminValue && $user->role?->code === 'superadmin') {
            return back()->withErrors([
                'is_admin' => 'No puedes degradar un superadministrador desde esta interfaz.',
            ]);
        }

        // Last admin protection
        if (! $requestedAdminValue && $user->role?->code === 'admin') {
            $adminCount = User::where('role_id', $user->role_id)->count();
            if ($adminCount <= 1) {
                return back()->withErrors([
                    'is_admin' => 'Debe existir al menos un administrador activo en la plataforma.',
                ]);
            }
        }

        $oldRoleCode = $user->role?->code;

        DB::transaction(function () use ($user, $targetRole): void {
            $user->role_id = $targetRole->id;
            $user->is_admin = in_array($targetRole->code, ['admin', 'superadmin'], true);
            $user->save();

            // Revoke stale tokens minted under the old role
            $user->tokens()->delete();
        });

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('UserRole update success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'target_user_id' => $user->id,
            'old_role' => $oldRoleCode,
            'new_role' => $targetRole->code,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return redirect()
            ->route('config.user-roles.index')
            ->with('success', "El usuario {$user->name} ahora tiene rol " . ($targetRole->name) . '.');
    }
}
