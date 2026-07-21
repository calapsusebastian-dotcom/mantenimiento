<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values for new, in-memory instances (mirrors the
     * DB column default so a freshly created model has `role` populated
     * without needing a reload from the database).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'operador',
        'active' => true,
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
            'role' => UserRole::class,
            'active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isTecnico(): bool
    {
        return $this->role === UserRole::Tecnico;
    }

    public function isOperador(): bool
    {
        return $this->role === UserRole::Operador;
    }

    /**
     * Órdenes de trabajo reportadas por este usuario.
     *
     * @return HasMany<WorkOrder, $this>
     */
    public function reportedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'reported_by');
    }

    /**
     * Órdenes de trabajo asignadas a este usuario (técnico).
     *
     * @return HasMany<WorkOrder, $this>
     */
    public function assignedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'assigned_to');
    }
}
