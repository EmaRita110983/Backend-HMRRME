<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTenantPatient;
use App\Models\EstudioMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EstudioMedicoController extends Controller
{
    use ResolvesTenantPatient;

    /**
     * Tipos válidos de estudio. Como no es un enum de BD, una lista nueva
     * acá alcanza para soportar un tipo nuevo, sin migración.
     */
    private const TIPOS = ['sonografia', 'rayos_x', 'tomografia', 'resonancia', 'laboratorio', 'otro'];

    /**
     * Solo médico y superadmin acceden. Filtra por tenant y, opcionalmente, por paciente.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', EstudioMedico::class);

        $user = $request->user();

        $query = EstudioMedico::query();

        if (!$user->isSuperAdmin()) {
            $query->where('admin_id', $user->id);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        return response()->json($query->latest('fecha_estudio')->get());
    }

    /**
     * El admin_id se deriva del paciente referenciado, nunca del input del cliente.
     */
    public function store(Request $request)
    {
        $this->authorize('create', EstudioMedico::class);

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'historial_medico_id' => 'nullable|exists:historial_medico,id',
            'tipo' => 'required|string|in:' . implode(',', self::TIPOS),
            'fecha_estudio' => 'required|date',
            'descripcion' => 'nullable|string',
            // Sin svg a propósito (mismo motivo que el logo del branding: un
            // SVG puede llevar <script> incrustado).
            'archivo' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $user = $request->user();
        $patient = $this->resolveTenantPatient($request, $request->patient_id);

        if ($request->filled('historial_medico_id')) {
            $this->assertHistorialBelongsToPatient($request->historial_medico_id, $patient->id);
        }

        // Disco privado a propósito: son documentos médicos (rayos X,
        // laboratorios), no branding. Se sirven solo vía archivo() más abajo,
        // con URL firmada de corta duración (ver
        // EstudioMedico::getArchivoUrlAttribute) — antes iban al disco
        // público y quedaban accesibles para siempre con solo conocer la URL.
        // El disco es "local" por defecto y "s3" cuando se configure
        // MEDICAL_FILES_DISK (ver config/filesystems.php: medical_disk).
        $file = $request->file('archivo');
        $path = $file->store('estudios', config('filesystems.medical_disk'));

        $estudio = EstudioMedico::create([
            'patient_id' => $request->patient_id,
            'historial_medico_id' => $request->historial_medico_id,
            'tipo' => $request->tipo,
            'fecha_estudio' => $request->fecha_estudio,
            'descripcion' => $request->descripcion,
            'archivo_path' => $path,
            'archivo_nombre_original' => $file->getClientOriginalName(),
            'archivo_mime' => $file->getClientMimeType(),
            'archivo_tamano' => $file->getSize(),
            'admin_id' => $patient->admin_id,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Estudio subido correctamente',
            'estudio' => $estudio,
        ], 201);
    }

    public function show(EstudioMedico $estudio)
    {
        $this->authorize('view', $estudio);

        return response()->json($estudio);
    }

    /**
     * Sirve el archivo del estudio. No lleva $this->authorize(): la ruta ya
     * exige firma válida (middleware 'signed'), y esa firma solo se genera
     * en EstudioMedico::getArchivoUrlAttribute después de que index()/show()
     * pasaron por EstudioMedicoPolicy::view. Sin firma válida, el middleware
     * corta la petición antes de llegar acá.
     *
     * Solo aplica con MEDICAL_FILES_DISK=local: con "s3", el archivo se
     * descarga directo desde una URL firmada por S3 (ver
     * EstudioMedico::getArchivoUrlAttribute) y esta ruta nunca se invoca.
     */
    public function archivo(EstudioMedico $estudio)
    {
        $disk = config('filesystems.medical_disk');

        if (!$estudio->archivo_path || !Storage::disk($disk)->exists($estudio->archivo_path)) {
            abort(404);
        }

        return Storage::disk($disk)->response(
            $estudio->archivo_path,
            $estudio->archivo_nombre_original
        );
    }

    /**
     * Solo metadatos: para reemplazar el archivo se sube un estudio nuevo
     * (mismo criterio simple que el resto de los documentos del historial).
     */
    public function update(Request $request, EstudioMedico $estudio)
    {
        $this->authorize('update', $estudio);

        $request->validate([
            'historial_medico_id' => 'nullable|exists:historial_medico,id',
            'tipo' => 'required|string|in:' . implode(',', self::TIPOS),
            'fecha_estudio' => 'required|date',
            'descripcion' => 'nullable|string',
        ]);

        // A diferencia de RecetaController::update() (que ni siquiera deja
        // editar historial_medico_id), acá sí es editable — mismo chequeo
        // que en store() para no dejarlo enlazar al historial de otro paciente.
        if ($request->filled('historial_medico_id')) {
            $this->assertHistorialBelongsToPatient($request->historial_medico_id, $estudio->patient_id);
        }

        $estudio->update($request->only([
            'historial_medico_id',
            'tipo',
            'fecha_estudio',
            'descripcion',
        ]));

        return response()->json([
            'message' => 'Estudio actualizado',
            'estudio' => $estudio,
        ]);
    }

    /**
     * Solo el médico dueño; siempre soft delete (ver EstudioMedicoPolicy).
     * El archivo físico se conserva (igual que el registro) por si hace
     * falta recuperarlo — no hay borrado físico expuesto todavía.
     */
    public function destroy(EstudioMedico $estudio)
    {
        $this->authorize('delete', $estudio);

        $estudio->delete();

        return response()->json(['message' => 'Estudio eliminado']);
    }
}
