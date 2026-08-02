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


    public function store(Request $request)
    {
        if ($request->user()->role === 'admin' && $request->role !== 'secretaria') {
            return response()->json([
                'message' => 'Un administrador solo puede crear secretarias.'
            ], 403);
        }

        $request->validate([

            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:5',
            'cedula' => 'required',
            'role' => 'required|in:superadmin,admin,secretaria'
        ]);


        $usuario = User::create([

            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'cedula' => $request->cedula,
            'role' => $request->role,
            'created_by' => auth()->id(),
            'admin_id' => $request->role === 'secretaria' ? auth()->id() : null,

        ]);


        return response()->json([

            'message' => 'Usuario creado correctamente',
            'usuario' => $usuario

        ], 201);
    }


    public function show(int $id)
    {
        $usuario = User::findOrFail($id);

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
        if (
            $request->user()->role === 'admin' &&
            $request->role !== 'secretaria'
        ) {
            return response()->json([
                'message' => 'Un administrador solo puede asignar el rol de secretaria.'
            ], 403);
        }

        // Admin solo puede editar usuarios creados por él
        if (
            $request->user()->role === 'admin' &&
            $usuario->created_by != auth()->id()
        ) {
            return response()->json([
                'message' => 'No tiene permiso para editar este usuario.'
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

        // Admin solo puede modificar usuarios creados por él
        if (
            $request->user()->role === 'admin' &&
            $usuario->created_by != $request->user()->id
        ) {
            return response()->json([
                'message' => 'No tiene permiso para modificar este usuario.'
            ], 403);
        }

        $usuario->update([
            'status' => !$usuario->status
        ]);

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


        // Admin solo puede eliminar usuarios creados por él
        if (
            $request->user()->role === 'admin' &&
            $usuario->created_by != $request->user()->id
        ) {
            return response()->json([
                'message' => 'No tiene permiso para editar este usuario.'
            ], 403);
        }

        $usuario->update([
            'status' => false
        ]);


        return response()->json([

            'message' => 'Usuario eliminado'

        ]);
    }
}
