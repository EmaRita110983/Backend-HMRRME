<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
{
    $user = $request->user();

    if ($user->role === 'superadmin') {

        return Patient::all();

    }

    if ($user->role === 'admin') {

        return Patient::where('admin_id', $user->id)->get();

    }

    if ($user->role === 'secretaria') {

        return Patient::where('admin_id', $user->admin_id)->get();

    }

    return response()->json([
        'message' => 'No autorizado'
    ], 403);
}
    /**
     * Store a newly created resource in storage.
     * Médico y secretaria pueden crear pacientes (para su propio tenant).
     */
    public function store(Request $request)
    {
        $this->authorize('create', Patient::class);

        $user = $request->user();

        $rules = [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'cedula' => 'nullable|string',
            'pasaporte' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'insurance' => 'nullable|string',
            'emergency_contact' => 'nullable|string',
            'emergency_phone' => 'nullable|string',
            'medical_conditions' => 'nullable|string',
        ];

        // El superadmin no tiene tenant propio: debe indicar a qué médico
        // pertenece el paciente que está creando.
        if ($user->isSuperAdmin()) {
            $rules['admin_id'] = 'required|exists:users,id';
        }

        $request->validate($rules);

        $patient = Patient::create([
            ...$request->only([
                'first_name',
                'last_name',
                'cedula',
                'pasaporte',
                'birth_date',
                'phone',
                'email',
                'address',
                'insurance',
                'emergency_contact',
                'emergency_phone',
                'medical_conditions',
            ]),
            'admin_id' => $user->isDoctor() ? $user->id : ($user->isSuperAdmin() ? $request->admin_id : $user->admin_id),
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Paciente creado correctamente',
            'patient' => $patient,
        ], 201);
    }

    /**
     * Display the specified resource. withTrashed a propósito: un paciente
     * eliminado se sigue pudiendo consultar (ver su ficha e historial), solo
     * que de solo lectura — ver buscarEliminado() y PatientPolicy::restore()
     * (siempre false, no se puede reactivar desde acá).
     */
    public function show(int $id)
    {
        $patient = Patient::withTrashed()->findOrFail($id);

        $this->authorize('view', $patient);

        return response()->json($patient);
    }

    /**
     * Busca, por cédula o pasaporte, un paciente eliminado (soft delete) del
     * propio tenant. Se usa cuando la búsqueda normal (solo pacientes activos)
     * no encuentra nada, para poder consultar su ficha e historial completo
     * aunque ya no aparezca en el listado — de solo lectura, no se reactiva.
     */
    public function buscarEliminado(Request $request)
    {
        $user = $request->user();
        $documento = trim((string) $request->query('documento', ''));

        if ($documento === '') {
            return response()->json([
                'message' => 'Debe indicar una cédula o pasaporte'
            ], 422);
        }

        $query = Patient::onlyTrashed()->where(function ($q) use ($documento) {
            $q->where('cedula', $documento)->orWhere('pasaporte', $documento);
        });

        if ($user->role === 'admin') {
            $query->where('admin_id', $user->id);
        } elseif ($user->role === 'secretaria') {
            $query->where('admin_id', $user->admin_id);
        } elseif ($user->role !== 'superadmin') {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        $patient = $query->first();

        if (!$patient) {
            return response()->json([
                'message' => 'No se encontró ningún paciente eliminado con ese documento'
            ], 404);
        }

        return response()->json($patient);
    }

    /**
     * Update the specified resource in storage.
     * Solo médico y superadmin editan (ver PatientPolicy::update()); la
     * secretaria no.
     */
    public function update(Request $request, Patient $patient)
    {
        $this->authorize('update', $patient);

        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'cedula' => 'nullable|string',
            'pasaporte' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'insurance' => 'nullable|string',
            'emergency_contact' => 'nullable|string',
            'emergency_phone' => 'nullable|string',
            'medical_conditions' => 'nullable|string',
        ]);

        $patient->update($request->only([
            'first_name',
            'last_name',
            'cedula',
            'pasaporte',
            'birth_date',
            'phone',
            'email',
            'address',
            'insurance',
            'emergency_contact',
            'emergency_phone',
            'medical_conditions',
        ]));

        return response()->json([
            'message' => 'Paciente actualizado',
            'patient' => $patient,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * Solo el médico puede eliminar, y siempre como soft delete (la secretaria nunca borra).
     */
    public function destroy(Patient $patient)
    {
        $this->authorize('delete', $patient);

        $patient->delete();

        return response()->json([
            'message' => 'Paciente eliminado',
        ]);
    }
}
