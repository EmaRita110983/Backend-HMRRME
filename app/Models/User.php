<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'cedula',
        'role',
        'admin_id',
        'status',
        'blocked',
        'login_attempts',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'blocked' => 'boolean',
            'status' => 'boolean',
            'login_attempts' => 'integer',
        ];
    }

    /**
     * Administrador al que pertenece la secretaria.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Secretarias pertenecientes al administrador.
     */
    public function secretaries()
    {
        return $this->hasMany(User::class, 'admin_id');
    }
    /**
     * Pacientes pertenecientes al administrador.
     */
    public function patients()
    {
        return $this->hasMany(Patient::class, 'admin_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * "admin" es el rol que representa al médico dueño del tenant.
     */
    public function isDoctor(): bool
    {
        return $this->role === 'admin';
    }

    public function isSecretary(): bool
    {
        return $this->role === 'secretaria';
    }
}
