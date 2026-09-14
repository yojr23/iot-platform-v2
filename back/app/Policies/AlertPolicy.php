<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

/**
 * SEC-ALERT-001: authorization gate for alert reads and lifecycle mutations.
 *
 * RBAC integration: This policy now uses the roles + permissions system instead of is_admin.
 * The user must have the appropriate permission for the requested operation.
 */
class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->can('alert.view');
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->hasVerifiedEmail() && $user->can('alert.view');
    }

    public function resolve(User $user, Alert $alert): bool
    {
        return $user->can('alert.resolve');
    }

    public function resolveAll(User $user): bool
    {
        return $user->can('alert.resolve');
    }
}
