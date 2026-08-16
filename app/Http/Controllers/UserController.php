<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->role === 'superadmin') {
            return response()->json(User::all());
        }

        return response()->json(
            User::where('created_by', $usuario->id)->get()
        );
    }

    /**
     * Busca, por cédula, un usuario (médico o secretaria) eliminado (soft
     * delete). Se usa cuando la búsqueda normal (solo usuarios activos) no
     * encuentra nada, para poder consultar sus datos aunque ya no aparezca en
     * el listado — de solo lectura, no se reactiva (ver UserPolicy::restore(),
     * siempre false).
     */
    public function buscarEliminado(Request $request)
    {
        $creador = $request->user();
        $documento = trim((string) $request->query('documento', ''));

        if ($documento === '') {
            return response()->json([
                'message' => 'Debe indicar una cédula'
            ], 422);
        }

        $query = User::onlyTrashed()->where('cedula', $documento);

        if (!$creador->isSuperAdmin()) {
            $query->where('created_by', $creador->id);
        }

        $usuario = $query->first();

        if (!$usuario) {
            return response()->json([
                'message' => 'No se encontró ningún usuario eliminado con esa cédula'
            ], 404);
        }

        return response()->json($usuario);
    }


    public function store(Request $request)
    {
        $creador = $request->user();

        if ($creador->role === 'admin' && $request->role !== 'secretaria') {
            return response()->json([
                'message' => 'Un administrador solo puede crear secretarias.'
            ], 403);
        }

        // Un médico (role=admin) se crea con la password genérica de
        // DEFAULT_ADMIN_PASSWORD (ver abajo), no con una que escriba el
        // superadmin: por eso acá no es obligatoria.
        $creandoMedico = $request->role === 'admin';

        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => $creandoMedico ? 'nullable' : 'required|min:8',
            'cedula' => 'required',
            'role' => 'required|in:superadmin,admin,secretaria',
        ];

        // El superadmin no tiene tenant propio: si crea una secretaria, debe
        // indicar a qué médico pertenece (a diferencia del admin, que solo
        // puede crear secretarias para sí mismo).
        $superadminCreandoSecretaria = $creador->isSuperAdmin() && $request->role === 'secretaria';

        if ($superadminCreandoSecretaria) {
            $rules['admin_id'] = 'required|exists:users,id';
        }

        $request->validate($rules);

        if ($superadminCreandoSecretaria) {
            $medico = User::find($request->admin_id);

            if (!$medico || $medico->role !== 'admin') {
                return response()->json([
                    'message' => 'El admin_id indicado no corresponde a un médico.'
                ], 422);
            }
        }

        $usuario = User::create([

            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($creandoMedico ? env('DEFAULT_ADMIN_PASSWORD', 'Cambiar123') : $request->password),
            'cedula' => $request->cedula,
            'role' => $request->role,
            'created_by' => auth()->id(),
            'admin_id' => $request->role === 'secretaria'
                ? ($superadminCreandoSecretaria ? $request->admin_id : auth()->id())
                : null,
            'must_change_password' => $creandoMedico,

        ]);


        return response()->json([

            'message' => 'Usuario creado correctamente',
            'usuario' => $usuario,
            // Para que el superadmin pueda copiar y entregarle la contraseña
            // genérica al médico en el momento (ver Usuarios.vue): solo va en
            // texto plano acá, nunca se guarda así en la base de datos.
            'password_generica' => $creandoMedico ? env('DEFAULT_ADMIN_PASSWORD', 'Cambiar123') : null

        ], 201);
    }


    public function show(int $id)
    {
        $usuario = User::findOrFail($id);

        $this->authorize('view', $usuario);

        return response()->json($usuario);
    }


    public function update(Request $request, int $id)
    {
        $request->validate([

            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'cedula' => 'required|unique:users,cedula,' . $id,
            'role' => 'required|in:superadmin,admin,secretaria'

        ]);


        $usuario = User::findOrFail($id);

        $this->authorize('update', $usuario);

        if (
            $request->user()->role === 'admin' &&
            $request->role !== 'secretaria'
        ) {
            return response()->json([
                'message' => 'Un administrador solo puede asignar el rol de secretaria.'
            ], 403);
        }

        $usuario->update([

            'name' => $request->name,
            'email' => $request->email,
            'cedula' => $request->cedula,
            'role' => $request->role

        ]);


        return response()->json([

            'message' => 'Usuario actualizado',
            'usuario' => $usuario

        ]);
    }

    public function toggleStatus(Request $request, int $id)
    {
        $usuario = User::findOrFail($id);

        $this->authorize('update', $usuario);

        $usuario->update([
            'status' => !$usuario->status
        ]);

        // Al desactivar, además de bloquear el próximo login (ver funLogin),
        // se revocan los tokens ya emitidos para que una sesión abierta en
        // ese momento pierda el acceso de inmediato, no solo en el futuro.
        if (!$usuario->status) {
            $usuario->tokens()->delete();
        }

        return response()->json([
            'message' => $usuario->status
                ? 'Usuario activado'
                : 'Usuario desactivado',
            'usuario' => $usuario
        ]);
    }


    public function destroy(Request $request, int $id)
    {
        $usuario = User::findOrFail($id);

        $this->authorize('delete', $usuario);

        // Soft delete: el registro se conserva para que el médico siga viendo
        // todo lo que esta secretaria creó/editó, aunque ya no pueda ingresar.
        $usuario->update(['status' => false]);
        $usuario->delete();

        return response()->json([
            'message' => 'Usuario eliminado'
        ]);
    }
}
