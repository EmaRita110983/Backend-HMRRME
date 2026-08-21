<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesListings;
use App\Http\Controllers\Concerns\ResolvesTenantPatient;
use App\Models\LicenciaMedica;
use Illuminate\Http\Request;

class LicenciaMedicaController extends Controller
{
    use PaginatesListings;
    use ResolvesTenantPatient;

    /**
     * Solo médico y superadmin acceden. Filtra por tenant y, opcionalmente, por paciente.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', LicenciaMedica::class);

        $user = $request->user();

        $query = LicenciaMedica::query();

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
        $this->authorize('create', LicenciaMedica::class);

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'fecha' => 'required|date',
            'constatado' => 'required|string',
            'recomendacion' => 'required|string',
            'certificacion_cierre' => 'required|string',
        ]);

        $user = $request->user();
        $patient = $this->resolveTenantPatient($request, $request->patient_id);

        $licencia = LicenciaMedica::create([
            ...$request->only([
                'patient_id',
                'fecha',
                'constatado',
                'recomendacion',
                'certificacion_cierre',
            ]),
            'admin_id' => $patient->admin_id,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Licencia creada correctamente',
            'licencia' => $licencia,
        ], 201);
    }

    public function show(LicenciaMedica $licencia)
    {
        $this->authorize('view', $licencia);

        return response()->json($licencia);
    }

    public function update(Request $request, LicenciaMedica $licencia)
    {
        $this->authorize('update', $licencia);

        $request->validate([
            'fecha' => 'required|date',
            'constatado' => 'required|string',
            'recomendacion' => 'required|string',
            'certificacion_cierre' => 'required|string',
        ]);

        $licencia->update($request->only([
            'fecha',
            'constatado',
            'recomendacion',
            'certificacion_cierre',
        ]));

        return response()->json([
            'message' => 'Licencia actualizada',
            'licencia' => $licencia,
        ]);
    }

    /**
     * Solo el médico dueño; siempre soft delete (ver LicenciaMedicaPolicy).
     */
    public function destroy(LicenciaMedica $licencia)
    {
        $this->authorize('delete', $licencia);

        $licencia->delete();

        return response()->json(['message' => 'Licencia eliminada']);
    }
}
