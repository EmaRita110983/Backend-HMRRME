<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class EstudioMedico extends Model
{
    use SoftDeletes;

    // Eloquent adivina el nombre de tabla pluralizando solo la última
    // palabra ("estudio_medicos"), pero la migración usa el plural natural
    // en español ("estudios_medicos") — mismo caso que LicenciaMedica.
    protected $table = 'estudios_medicos';

    protected $fillable = [
        'patient_id',
        'historial_medico_id',
        'admin_id',
        'created_by',
        'tipo',
        'fecha_estudio',
        'descripcion',
        'archivo_path',
        'archivo_nombre_original',
        'archivo_mime',
        'archivo_tamano',
    ];

    protected $appends = [
        'archivo_url',
    ];

    protected function casts(): array
    {
        return [
            'fecha_estudio' => 'date',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function historialMedico()
    {
        return $this->belongsTo(HistorialMedico::class);
    }

    /**
     * Médico dueño del tenant.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Quién subió este estudio (médico o superadmin).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getArchivoUrlAttribute(): ?string
    {
        return $this->archivo_path ? Storage::disk('public')->url($this->archivo_path) : null;
    }
}
