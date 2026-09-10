<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        $users = User::orderBy('name')->get();

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

        if ($currentUser && $currentUser->id === $user->id && ! $requestedAdminValue) {
            return back()->withErrors([
                'is_admin' => 'No puedes retirarte tu propio rol de administrador.',
            ]);
        }

        if (! $requestedAdminValue && $user->is_admin) {
            $adminCount = User::where('is_admin', true)->count();

            if ($adminCount <= 1) {
                return back()->withErrors([
                    'is_admin' => 'Debe existir al menos un administrador activo en la plataforma.',
                ]);
            }
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

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('UserRole update success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'target_user_id' => $user->id,
            'payload_keys' => array_keys($validated),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return redirect()
            ->route('config.user-roles.index')
            ->with('success', "El usuario {$user->name} ahora tiene rol " . ($user->is_admin ? 'administrador' : 'estándar') . '.');
    }
}
