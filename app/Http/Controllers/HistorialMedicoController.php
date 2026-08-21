<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTenantPatient;
use App\Models\HistorialMedico;
use Illuminate\Http\Request;

class HistorialMedicoController extends Controller
{
    use ResolvesTenantPatient;

    /**
     * Solo médico y superadmin acceden. Filtra por tenant y, opcionalmente, por paciente.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', HistorialMedico::class);

        $user = $request->user();

        $query = HistorialMedico::query();

        if (!$user->isSuperAdmin()) {
            $query->where('admin_id', $user->id);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        return response()->json($query->latest('fecha_consulta')->get());
    }

    /**
     * El admin_id se deriva del paciente referenciado, nunca del input del cliente.
     */
    public function store(Request $request)
    {
        $this->authorize('create', HistorialMedico::class);

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'fecha_consulta' => 'required|date',
            'motivo_consulta' => 'required|string',
            'diagnostico' => 'required|string',
            'tratamiento' => 'nullable|string',
            'dieta' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        $user = $request->user();
        $patient = $this->resolveTenantPatient($request, $request->patient_id);

        $historial = HistorialMedico::create([
            ...$request->only([
                'patient_id',
                'fecha_consulta',
                'motivo_consulta',
                'diagnostico',
                'tratamiento',
                'dieta',
                'observaciones',
            ]),
            'admin_id' => $patient->admin_id,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Entrada de historial creada correctamente',
            'historial' => $historial,
        ], 201);
    }

    public function show(HistorialMedico $historial)
    {
        $this->authorize('view', $historial);

        return response()->json($historial);
    }

    public function update(Request $request, HistorialMedico $historial)
    {
        $this->authorize('update', $historial);

        $request->validate([
            'fecha_consulta' => 'required|date',
            'motivo_consulta' => 'required|string',
            'diagnostico' => 'required|string',
            'tratamiento' => 'nullable|string',
            'dieta' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        $historial->update($request->only([
            'fecha_consulta',
            'motivo_consulta',
            'diagnostico',
            'tratamiento',
            'dieta',
            'observaciones',
        ]));

        return response()->json([
            'message' => 'Historial actualizado',
            'historial' => $historial,
        ]);
    }

    /**
     * Solo el médico dueño; siempre soft delete (ver HistorialMedicoPolicy).
     */
    public function destroy(HistorialMedico $historial)
    {
        $this->authorize('delete', $historial);

        $historial->delete();

        return response()->json(['message' => 'Entrada de historial eliminada']);
    }
}
