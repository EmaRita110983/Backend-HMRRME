<?php

namespace App\Http\Controllers;

use App\Models\EstudioMedico;
use App\Models\Patient;
use Illuminate\Http\Request;

class EstudioMedicoController extends Controller
{
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
        $patient = Patient::findOrFail($request->patient_id);

        if (!$user->isSuperAdmin() && $patient->admin_id !== $user->id) {
            return response()->json(['message' => 'No tiene permiso sobre este paciente.'], 403);
        }

        $file = $request->file('archivo');
        $path = $file->store('estudios', 'public');

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
