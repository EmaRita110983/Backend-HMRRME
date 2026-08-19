<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

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

    /**
     * URL firmada válida 5 minutos, no una URL pública fija: el archivo vive
     * en el disco privado (ver EstudioMedicoController::store/archivo) y solo
     * se genera acá, es decir después de que quien pidió el estudio ya pasó
     * por EstudioMedicoPolicy::view (index/show/store). Antes era una URL
     * pública permanente, accesible para siempre por cualquiera que la
     * obtuviera sin volver a pasar por ningún control de acceso.
     */
    public function getArchivoUrlAttribute(): ?string
    {
        return $this->archivo_path
            ? URL::temporarySignedRoute('estudios.archivo', now()->addMinutes(5), ['estudio' => $this->id])
            : null;
    }
}
