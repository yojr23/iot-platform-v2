<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\DashboardPreference;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            // En flujos HTTP, cualquier alta de usuario siempre inicia como no-admin.
            if (! app()->runningInConsole()) {
                $user->is_admin = false;
            }
        });

        static::updating(function (User $user): void {
            if (! $user->isDirty('is_admin')) {
                return;
            }

            // Permite seeds/migraciones en consola cuando no existe usuario autenticado.
            if (app()->runningInConsole() && ! Auth::check()) {
                return;
            }

            $actor = Auth::user();
            if (! $actor || ! $actor->is_admin) {
                throw new AuthorizationException('No autorizado para modificar el rol de administrador.');
            }
        });
    }

    public function dashboardPreference()
    {
        return $this->hasOne(DashboardPreference::class);
    }
}
