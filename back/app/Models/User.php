<?php

namespace App\Models;

use App\Models\DashboardPreference;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (! app()->runningInConsole()) {
                $user->is_admin = false;
            }

            if ($user->role_id !== null || ! Schema::hasTable('roles')) {
                return;
            }

            $roleCode = $user->is_admin ? 'admin' : 'user';
            $user->role_id = Role::query()->where('code', $roleCode)->value('id');
        });

        static::updating(function (self $user): void {
            if (! $user->isDirty('is_admin')) {
                return;
            }

            if (app()->runningInConsole() && ! Auth::check()) {
                return;
            }

            $actor = Auth::user();
            if (! $actor || ! $actor->hasAnyRole(['admin', 'superadmin'])) {
                throw new AuthorizationException('No autorizado para modificar el rol de administrador.');
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function dashboardPreference()
    {
        return $this->hasOne(DashboardPreference::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Check if user has a specific permission.
     */
    public function can($abilities, $arguments = []): bool
    {
        if (! is_string($abilities) || $arguments !== []) {
            return parent::can($abilities, $arguments);
        }

        if (! $this->role) {
            return false;
        }

        // SuperAdmin bypass
        if ($this->role->code === 'superadmin') {
            return true;
        }

        return $this->role->permissions->contains('code', $abilities);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $code): bool
    {
        return $this->role?->code === $code;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $codes): bool
    {
        return in_array($this->role?->code, $codes, true);
    }

    /**
     * Get all permissions for the user.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        if (! $this->role) {
            return collect();
        }

        if ($this->role->code === 'superadmin') {
            return Permission::pluck('code');
        }

        return $this->role->permissions->pluck('code');
    }

    /**
     * Check if user can manage another user's role.
     */
    public function canManageRole(User $targetUser): bool
    {
        if (! $this->role) {
            return false;
        }

        // SuperAdmin can manage anyone except other SuperAdmins
        if ($this->role->code === 'superadmin') {
            return $targetUser->role?->code !== 'superadmin' || $this->id === $targetUser->id;
        }

        // Admin can only manage users
        if ($this->role->code === 'admin') {
            return $targetUser->role?->code === 'user';
        }

        return false;
    }

    /**
     * Check if user can assign a specific role.
     */
    public function canAssignRole(string $roleCode): bool
    {
        if (! $this->role) {
            return false;
        }

        // SuperAdmin can assign any role
        if ($this->role->code === 'superadmin') {
            return true;
        }

        // Admin can only assign user role
        if ($this->role->code === 'admin') {
            return $roleCode === 'user';
        }

        return false;
    }
}
