<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
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

            // The MySQL is_admin protection trigger (2026_04_26_180000) rejects any
            // is_admin=1 INSERT unless the connection has explicitly authorized it. The
            // console (seeders/artisan) is a trusted actor, so lift the DB guard for the
            // upcoming write; `saved` resets it. Direct raw SQL outside the model never
            // sets this flag and still hits the trigger (defense-in-depth intact).
            if ($user->is_admin && app()->runningInConsole()) {
                self::allowAdminRoleChange();
            }

            if ($user->role_id !== null || ! Schema::hasTable('roles')) {
                return;
            }

            $roleCode = $user->is_admin ? 'admin' : 'user';
            $roleId = Role::query()->where('code', $roleCode)->value('id');

            if ($roleId === null) {
                throw new \RuntimeException("Role '{$roleCode}' not found. Run the RolePermissionSeeder.");
            }

            $user->role_id = $roleId;
        });

        static::updating(function (self $user): void {
            if (! $user->isDirty('is_admin')) {
                return;
            }

            if (app()->runningInConsole() && ! Auth::check()) {
                // Trusted console actor (seeders/artisan): authorize the DB write.
                self::allowAdminRoleChange();

                return;
            }

            $actor = Auth::user();
            if (! $actor || ! $actor->hasAnyRole(['admin', 'superadmin'])) {
                throw new AuthorizationException('No autorizado para modificar el rol de administrador.');
            }

            // App-level authorization passed — lift the DB trigger guard for this write.
            self::allowAdminRoleChange();
        });

        static::saved(function (self $user): void {
            self::resetAdminRoleChangeGuard();
        });
    }

    /**
     * Authorize the upcoming is_admin write against the MySQL protection trigger by
     * setting the connection session flag. No-op on non-MySQL drivers (SQLite/CI).
     */
    private static function allowAdminRoleChange(): void
    {
        $connection = (new self)->getConnection();

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement('SET @allow_admin_role_change = 1');
        }
    }

    private static function resetAdminRoleChangeGuard(): void
    {
        $connection = (new self)->getConnection();

        if ($connection->getDriverName() === 'mysql') {
            $connection->statement('SET @allow_admin_role_change = 0');
        }
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if the user has an admin or superadmin role.
     * Single authority for admin checks — replaces the legacy is_admin column.
     */
    public function isAdmin(): bool
    {
        return $this->relationLoaded('role')
            ? in_array($this->role?->code, ['admin', 'superadmin'], true)
            : $this->hasAnyRole(['admin', 'superadmin']);
    }

    public function dashboardPreference()
    {
        return $this->hasOne(DashboardPreference::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
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
     * @return Collection<int, string>
     */
    public function getAllPermissions(): Collection
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
