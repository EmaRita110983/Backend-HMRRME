<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Patient;
use Illuminate\Http\Request;

class CitaController extends Controller
{
    /**
     * Filtra por tenant y, opcionalmente, por paciente/fecha/estado.
     * Usado también por el dashboard para "citas de hoy" (?fecha=YYYY-MM-DD&estado=pendiente).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Cita::class);

        $user = $request->user();

        $query = Cita::with('patient:id,first_name,last_name,cedula');

        if ($user->isDoctor()) {
            $query->where('admin_id', $user->id);
        } elseif ($user->isSecretary()) {
            $query->where('admin_id', $user->admin_id);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->fecha);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->orderBy('fecha')->orderBy('hora')->get());
    }

    /**
     * El admin_id se deriva del paciente referenciado, nunca del input del cliente.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Cita::class);

        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'fecha' => 'required|date',
            'hora' => 'required',
            'motivo' => 'nullable|string',
            'estado' => 'nullable|in:pendiente,completada,cancelada',
        ]);

        $user = $request->user();
        $patient = Patient::findOrFail($request->patient_id);

        if (!$user->isSuperAdmin() && $patient->admin_id !== ($user->isDoctor() ? $user->id : $user->admin_id)) {
            return response()->json(['message' => 'No tiene permiso sobre este paciente.'], 403);
        }

        $cita = Cita::create([
            'patient_id' => $patient->id,
            'fecha' => $request->fecha,
            'hora' => $request->hora,
            'motivo' => $request->motivo,
            'estado' => $request->estado ?? 'pendiente',
            'admin_id' => $patient->admin_id,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Cita creada correctamente',
            'cita' => $cita,
        ], 201);
    }

    public function show(Cita $cita)
    {
        $this->authorize('view', $cita);

        return response()->json($cita);
    }

    public function update(Request $request, Cita $cita)
    {
        $this->authorize('update', $cita);

        $request->validate([
            'fecha' => 'required|date',
            'hora' => 'required',
            'motivo' => 'nullable|string',
            'estado' => 'nullable|in:pendiente,completada,cancelada',
        ]);

        $cita->update($request->only(['fecha', 'hora', 'motivo', 'estado']));

        return response()->json([
            'message' => 'Cita actualizada',
            'cita' => $cita,
        ]);
    }

    /**
     * Solo el médico dueño; siempre soft delete (ver CitaPolicy).
     */
    public function destroy(Cita $cita)
    {
        $this->authorize('delete', $cita);

        $cita->delete();

        return response()->json(['message' => 'Cita eliminada']);
    }
}
