<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Cita extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'admin_id',
        'created_by',
        'fecha',
        'hora',
        'motivo',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Médico dueño del tenant.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Quién agendó la cita (médico, secretaria o superadmin).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Auditoría de cambios (ver AUDITORIA.md, "Sin auditoría de
    // accesos/cambios").
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
