<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesListings;
use App\Http\Controllers\Concerns\ResolvesTenantPatient;
use App\Models\AutorizacionProcedimiento;
use Illuminate\Http\Request;

class AutorizacionProcedimientoController extends Controller
{
    use PaginatesListings;
    use ResolvesTenantPatient;

    /**
     * Solo médico y superadmin acceden. Filtra por tenant y, opcionalmente, por paciente.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', AutorizacionProcedimiento::class);

        $user = $request->user();

        $query = AutorizacionProcedimiento::query();

        if (!$user->isSuperAdmin()) {
            $query->where('admin_id', $user->id);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        return response()->json($this->paginateOrGet($query->latest('fecha'), $request));
    }

    /**
     * El admin_id se deriva del paciente referenciado, nunca del input del cliente.
     */
    public function store(Request $request)
    {
        $this->authorize('create', AutorizacionProcedimiento::class);

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'fecha' => 'required|date',
            'ars' => 'nullable|string|max:255',
            'historia_enfermedad' => 'required|string',
            'estudios_realizados' => 'nullable|string',
            'tiempo_evolucion' => 'nullable|string',
            'tratamiento_previo' => 'nullable|string',
            'diagnostico_presuntivo' => 'required|string',
        ]);

        $user = $request->user();
        $patient = $this->resolveTenantPatient($request, $request->patient_id);

        $autorizacion = AutorizacionProcedimiento::create([
            ...$request->only([
                'patient_id',
                'fecha',
                'ars',
                'historia_enfermedad',
                'estudios_realizados',
                'tiempo_evolucion',
                'tratamiento_previo',
                'diagnostico_presuntivo',
            ]),
            'admin_id' => $patient->admin_id,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Autorización creada correctamente',
            'autorizacion' => $autorizacion,
        ], 201);
    }

    public function show(AutorizacionProcedimiento $autorizacion)
    {
        $this->authorize('view', $autorizacion);

        return response()->json($autorizacion);
    }

    public function update(Request $request, AutorizacionProcedimiento $autorizacion)
    {
        $this->authorize('update', $autorizacion);

        $request->validate([
            'fecha' => 'required|date',
            'ars' => 'nullable|string|max:255',
            'historia_enfermedad' => 'required|string',
            'estudios_realizados' => 'nullable|string',
            'tiempo_evolucion' => 'nullable|string',
            'tratamiento_previo' => 'nullable|string',
            'diagnostico_presuntivo' => 'required|string',
        ]);

        $autorizacion->update($request->only([
            'fecha',
            'ars',
            'historia_enfermedad',
            'estudios_realizados',
            'tiempo_evolucion',
            'tratamiento_previo',
            'diagnostico_presuntivo',
        ]));

        return response()->json([
            'message' => 'Autorización actualizada',
            'autorizacion' => $autorizacion,
        ]);
    }

    /**
     * Solo el médico dueño; siempre soft delete (ver AutorizacionProcedimientoPolicy).
     */
    public function destroy(AutorizacionProcedimiento $autorizacion)
    {
        $this->authorize('delete', $autorizacion);

        $autorizacion->delete();

        return response()->json(['message' => 'Autorización eliminada']);
    }
}
