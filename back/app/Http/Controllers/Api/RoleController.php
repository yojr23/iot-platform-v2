<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json([
            'data' => $role,
        ]);
    }
}
