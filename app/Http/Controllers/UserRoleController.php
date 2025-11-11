<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $user->is_admin = (bool) $validated['is_admin'];
        $user->save();

        return redirect()
            ->route('config.user-roles.index')
            ->with('success', "El usuario {$user->name} ahora tiene rol " . ($user->is_admin ? 'administrador' : 'estándar') . '.');
    }
}
