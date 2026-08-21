<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTenantPatient;
use App\Models\Receta;
use Illuminate\Http\Request;

class RecetaController extends Controller
{
    use ResolvesTenantPatient;

    /**
     * Solo médico y superadmin acceden. Filtra por tenant y, opcionalmente, por paciente.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Receta::class);

        $user = $request->user();

        $query = Receta::query();

        if (!$user->isSuperAdmin()) {
            $query->where('admin_id', $user->id);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        return response()->json($query->latest('fecha_emision')->get());
    }

    /**
     * El admin_id se deriva del paciente referenciado, nunca del input del cliente.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Receta::class);

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'historial_medico_id' => 'nullable|exists:historial_medico,id',
            'fecha_emision' => 'required|date',
            'ars' => 'nullable|string|max:255',
            'medicamentos' => 'required|string',
            'indicaciones' => 'nullable|string',
        ]);

        $user = $request->user();
        $patient = $this->resolveTenantPatient($request, $request->patient_id);

        if ($request->filled('historial_medico_id')) {
            $this->assertHistorialBelongsToPatient($request->historial_medico_id, $patient->id);
        }

        $receta = Receta::create([
            ...$request->only([
                'patient_id',
                'historial_medico_id',
                'fecha_emision',
                'ars',
                'medicamentos',
                'indicaciones',
            ]),
            'admin_id' => $patient->admin_id,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Receta creada correctamente',
            'receta' => $receta,
        ], 201);
    }

    public function show(Receta $receta)
    {
        $this->authorize('view', $receta);

        return response()->json($receta);
    }

    public function update(Request $request, Receta $receta)
    {
        $this->authorize('update', $receta);

        $request->validate([
            'fecha_emision' => 'required|date',
            'ars' => 'nullable|string|max:255',
            'medicamentos' => 'required|string',
            'indicaciones' => 'nullable|string',
        ]);

        $receta->update($request->only([
            'fecha_emision',
            'ars',
            'medicamentos',
            'indicaciones',
        ]));

        return response()->json([
            'message' => 'Receta actualizada',
            'receta' => $receta,
        ]);
    }

    /**
     * Solo el médico dueño; siempre soft delete (ver RecetaPolicy).
     */
    public function destroy(Receta $receta)
    {
        $this->authorize('delete', $receta);

        $receta->delete();

        return response()->json(['message' => 'Receta eliminada']);
    }
}
