<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

/**
 * SEC-ALERT-001: authorization gate for alert reads and lifecycle mutations.
 *
 * Existing code reused: `User::hasVerifiedEmail()` (Illuminate\Auth\MustVerifyEmail, already used
 * by the `verified` route middleware) and `User::$is_admin` (existing admin flag, already the sole
 * gate for the `admin` route middleware — see `routes/api.php`).
 * Existing owner retired/delegated: none — `AlertController::resolve()/resolveAll()` previously had
 * no authorization check at all; this is a net-new gate, not a replacement of prior logic.
 * Compatibility window: none needed. If an operator role is introduced later, replace the
 * `is_admin` check in `resolve()`/`resolveAll()` with an explicit operator capability check —
 * do NOT reopen alert resolution to every authenticated/verified user.
 */
class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function resolve(User $user, Alert $alert): bool
    {
        return (bool) $user->is_admin;
    }

    public function resolveAll(User $user): bool
    {
        return (bool) $user->is_admin;
    }
}
