<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Con "q" (usado hoy solo por el autocomplete de "Nueva cita" en el
     * Dashboard, ver Dashboard.vue), busca por nombre/cédula y limita a 15
     * resultados en vez de traer el listado completo — antes el Dashboard
     * pedía TODOS los pacientes del tenant en cada carga solo para filtrar
     * en el navegador, algo que no escala a medida que crece la cantidad de
     * pacientes. Sin "q", el comportamiento es exactamente el mismo de
     * siempre (usado por Pacientes.vue, que pagina del lado del cliente).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'superadmin') {
            $query = Patient::query();
        } elseif ($user->role === 'admin') {
            $query = Patient::where('admin_id', $user->id);
        } elseif ($user->role === 'secretaria') {
            $query = Patient::where('admin_id', $user->admin_id);
        } else {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($request->filled('q')) {
            $term = $request->query('q');

            return $query->where(function ($sub) use ($term) {
                $sub->where('first_name', 'ilike', "%{$term}%")
                    ->orWhere('last_name', 'ilike', "%{$term}%")
                    ->orWhere('cedula', 'ilike', "%{$term}%");
            })
                ->select(['id', 'admin_id', 'first_name', 'last_name', 'cedula'])
                ->limit(15)
                ->get();
        }

        return $query->get();
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
            // Solo exists:users,id (sin filtrar por rol) dejaba crear un
            // paciente "huérfano" si el superadmin mandaba el id de una
            // secretaria/otro superadmin: ningún médico lo ve jamás en su
            // listado (index() filtra por admin_id = médico dueño). Mismo
            // criterio que ya usa UserController::store al validar a quién
            // se le asigna una secretaria.
            $rules['admin_id'] = ['required', Rule::exists('users', 'id')->where('role', 'admin')];
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
