<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class EstudioMedico extends Model
{
    use LogsActivity;
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
     *
     * Con MEDICAL_FILES_DISK=s3 (ver config/filesystems.php: medical_disk),
     * el archivo no vive en este servidor, así que la firma la genera S3
     * directamente (temporaryUrl) en vez de pasar por la ruta local
     * "estudios.archivo" — misma ventana de 5 minutos, mismo momento de
     * generación (después de la policy), solo cambia quién firma la URL.
     */
    public function getArchivoUrlAttribute(): ?string
    {
        if (!$this->archivo_path) {
            return null;
        }

        $disk = config('filesystems.medical_disk');

        if ($disk === 's3') {
            return Storage::disk('s3')->temporaryUrl($this->archivo_path, now()->addMinutes(5));
        }

        return URL::temporarySignedRoute('estudios.archivo', now()->addMinutes(5), ['estudio' => $this->id]);
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
