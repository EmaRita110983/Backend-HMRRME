<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->role === 'superadmin') {
            return response()->json(User::all());
        }

        // admin_id (no created_by): un médico debe ver todas sus secretarias
        // sin importar quién las haya creado (él mismo o el superadmin en su
        // nombre). Ver mismo ajuste en buscarEliminado().
        return response()->json(
            User::where('admin_id', $usuario->id)->get()
        );
    }

    /**
     * Conteos livianos para los tiles del Dashboard (ver Dashboard.vue):
     * antes pedía el listado completo de usuarios solo para contar con
     * .filter().length en el navegador, igual que el hallazgo de
     * PatientController::index — un SELECT COUNT no necesita traer las filas.
     */
    public function stats(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->isSuperAdmin()) {
            return response()->json([
                'medicos' => User::where('role', 'admin')->count(),
            ]);
        }

        return response()->json([
            'secretarias' => User::where('admin_id', $usuario->id)->where('role', 'secretaria')->count(),
        ]);
    }

    /**
     * Busca, por cédula, un usuario (médico o secretaria) eliminado (soft
     * delete). Se usa cuando la búsqueda normal (solo usuarios activos) no
     * encuentra nada, para poder consultar sus datos aunque ya no aparezca en
     * el listado, y desde ahí reactivarlo si corresponde (ver restore()).
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

        // Se acota por admin_id (no created_by): esa es la relación real de
        // pertenencia de una secretaria a su médico (ver User::secretaries()).
        // created_by queda con el id de quien la creó, que puede ser el
        // superadmin cuando la creó en nombre del médico, y en ese caso no
        // coincide con el médico que después busca la cédula.
        if (!$creador->isSuperAdmin()) {
            $query->where('admin_id', $creador->id);
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

        // Un médico (role=admin) se crea con una password aleatoria generada
        // acá mismo (ver abajo), no con una que escriba el superadmin: por
        // eso acá no es obligatoria. Antes se usaba un valor fijo
        // (DEFAULT_ADMIN_PASSWORD) igual para todos los médicos nuevos y
        // versionado en .env.example: cualquiera con acceso al repo podía
        // adivinarla. Ahora cada médico recibe una contraseña propia e
        // impredecible.
        $creandoMedico = $request->role === 'admin';
        $passwordGenerica = $creandoMedico ? Str::password(12) : null;

        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => $creandoMedico ? 'nullable' : 'required|min:8',
            // Antes sin unique: una cédula repetida chocaba contra la
            // restricción única de la tabla en el INSERT, lo que lanzaba una
            // QueryException no controlada y Laravel volcaba el SQL completo
            // (con el hash de la password incluido) a storage/logs/laravel.log.
            // update() ya validaba esto correctamente, solo faltaba acá.
            'cedula' => 'required|unique:users,cedula',
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
            'password' => Hash::make($creandoMedico ? $passwordGenerica : $request->password),
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
            // generada al médico en el momento (ver Usuarios.vue): solo va en
            // texto plano acá, nunca se guarda así en la base de datos, y es
            // única por médico (antes era un valor fijo, ver comentario en
            // $passwordGenerica más arriba).
            'password_generica' => $passwordGenerica

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
        // authorize() antes que validate() a propósito: si no, un admin
        // podía sondear si un email/cédula ya existe en cualquier tenant
        // (422 si existe, 403 si no) intentando editar un usuario que ni
        // siquiera es suyo — mismo patrón que ya usan PatientController y
        // el resto de los controladores de historial/recetas/etc.
        $usuario = User::findOrFail($id);

        $this->authorize('update', $usuario);

        $request->validate([

            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'cedula' => 'required|unique:users,cedula,' . $id,
            'role' => 'required|in:superadmin,admin,secretaria'

        ]);

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

    /**
     * Restablece manualmente la contraseña de un usuario que la olvidó (no
     * hay flujo de "olvidé mi contraseña" por email: quien la olvida avisa a
     * su médico/superadmin, y este la resetea desde acá). Genera una nueva
     * contraseña aleatoria (mismo criterio que al crear un médico, ver
     * store()) y obliga a cambiarla en el próximo login. Mismo permiso que
     * update(): el superadmin puede resetear a cualquiera, un médico solo a
     * sus propias secretarias.
     */
    public function resetPassword(Request $request, int $id)
    {
        $usuario = User::findOrFail($id);

        $this->authorize('update', $usuario);

        $passwordGenerica = Str::password(12);

        $usuario->password = Hash::make($passwordGenerica);
        $usuario->must_change_password = true;
        $usuario->save();

        return response()->json([
            'message' => 'Contraseña restablecida correctamente',
            'usuario' => $usuario,
            'password_generica' => $passwordGenerica,
        ]);
    }

    /**
     * Reactiva (deshace el soft delete) un usuario eliminado, encontrado
     * antes con buscarEliminado(). Vuelve a dejarlo con status=true (activo),
     * igual que un usuario recién creado: destroy() lo había forzado a false
     * junto con el soft delete, así que restaurar el registro sin esto lo
     * dejaría reactivado pero bloqueado para iniciar sesión.
     */
    public function restore(Request $request, int $id)
    {
        $usuario = User::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $usuario);

        $usuario->restore();
        $usuario->update(['status' => true]);

        return response()->json([
            'message' => 'Usuario reactivado',
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
