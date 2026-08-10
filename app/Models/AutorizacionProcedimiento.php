<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutorizacionProcedimiento extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'admin_id',
        'created_by',
        'fecha',
        'ars',
        'historia_enfermedad',
        'estudios_realizados',
        'tiempo_evolucion',
        'tratamiento_previo',
        'diagnostico_presuntivo',
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
     * Quién registró esta autorización (médico o superadmin).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
