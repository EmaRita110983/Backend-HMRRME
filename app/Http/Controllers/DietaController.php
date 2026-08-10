<?php

namespace App\Http\Controllers;

use App\Models\Dieta;
use App\Models\Patient;
use Illuminate\Http\Request;

class DietaController extends Controller
{
    /**
     * Solo médico y superadmin acceden. Filtra por tenant y, opcionalmente, por paciente.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Dieta::class);

        $user = $request->user();

        $query = Dieta::query();

        if (!$user->isSuperAdmin()) {
            $query->where('admin_id', $user->id);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        return response()->json($query->latest('fecha')->get());
    }

    /**
     * El admin_id se deriva del paciente referenciado, nunca del input del cliente.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Dieta::class);

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'fecha' => 'required|date',
            'dieta' => 'required|string',
        ]);

        $user = $request->user();
        $patient = Patient::findOrFail($request->patient_id);

        if (!$user->isSuperAdmin() && $patient->admin_id !== $user->id) {
            return response()->json(['message' => 'No tiene permiso sobre este paciente.'], 403);
        }

        $dieta = Dieta::create([
            ...$request->only([
                'patient_id',
                'fecha',
                'dieta',
            ]),
            'admin_id' => $patient->admin_id,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Dieta creada correctamente',
            'dieta' => $dieta,
        ], 201);
    }

    public function show(Dieta $dieta)
    {
        $this->authorize('view', $dieta);

        return response()->json($dieta);
    }

    public function update(Request $request, Dieta $dieta)
    {
        $this->authorize('update', $dieta);

        $request->validate([
            'fecha' => 'required|date',
            'dieta' => 'required|string',
        ]);

        $dieta->update($request->only([
            'fecha',
            'dieta',
        ]));

        return response()->json([
            'message' => 'Dieta actualizada',
            'dieta' => $dieta,
        ]);
    }

    /**
     * Solo el médico dueño; siempre soft delete (ver DietaPolicy).
     */
    public function destroy(Dieta $dieta)
    {
        $this->authorize('delete', $dieta);

        $dieta->delete();

        return response()->json(['message' => 'Dieta eliminada']);
    }
}
