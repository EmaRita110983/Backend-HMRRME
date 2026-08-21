<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'cedula',
        'role',
        'admin_id',
        'status',
        'must_change_password',
        'blocked',
        'login_attempts',
        'created_by',
        'brand_name',
        'professional_title',
        'brand_color',
        'brand_color_secondary',
        'logo_path',
        'header_icon_left_path',
        'header_icon_right_path',
        'header_credentials',
        'licencia_declaracion',
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
            'must_change_password' => 'boolean',
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

    /**
     * El médico dueño del branding (logo/nombre/color) que este usuario debe
     * ver: él mismo si es admin, su médico si es secretaria, o null si es
     * superadmin (no pertenece a ningún tenant).
     */
    public function tenant(): ?User
    {
        if ($this->isDoctor()) {
            return $this;
        }

        if ($this->isSecretary()) {
            return $this->admin;
        }

        return null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getHeaderIconLeftUrlAttribute(): ?string
    {
        return $this->header_icon_left_path ? Storage::disk('public')->url($this->header_icon_left_path) : null;
    }

    public function getHeaderIconRightUrlAttribute(): ?string
    {
        return $this->header_icon_right_path ? Storage::disk('public')->url($this->header_icon_right_path) : null;
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

    // Auditoría de cambios (ver AUDITORIA.md, "Sin auditoría de
    // accesos/cambios"). password queda afuera a propósito: aunque solo se
    // guarda hasheada, no hay motivo para que el hash también quede
    // duplicado en el log de auditoría.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
