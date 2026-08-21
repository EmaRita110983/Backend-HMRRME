<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class HistorialMedico extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'historial_medico';

    protected $fillable = [
        'patient_id',
        'admin_id',
        'created_by',
        'fecha_consulta',
        'motivo_consulta',
        'diagnostico',
        'tratamiento',
        'dieta',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_consulta' => 'date',
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
     * Quién registró esta entrada (médico o superadmin).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recetas()
    {
        return $this->hasMany(Receta::class, 'historial_medico_id');
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
