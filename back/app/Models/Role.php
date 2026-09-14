<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'level',
        'name',
        'description',
        'assignable',
        'is_system',
    ];

    protected $casts = [
        'assignable' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->using(RolePermission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $code): bool
    {
        return $this->permissions->contains('code', $code);
    }

    /**
     * Get all permission codes for the role.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getPermissionCodes(): \Illuminate\Support\Collection
    {
        return $this->permissions->pluck('code');
    }
}
