<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Receta extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'historial_medico_id',
        'admin_id',
        'created_by',
        'fecha_emision',
        'ars',
        'medicamentos',
        'indicaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function historial()
    {
        return $this->belongsTo(HistorialMedico::class, 'historial_medico_id');
    }

    /**
     * Médico dueño del tenant.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Quién emitió la receta (médico o superadmin).
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
