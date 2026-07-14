<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
  public function index()
  {
    return response()->json(
      User::all()
    );
  }

  public function store(Request $request)
  {

    $request->validate([

      'name' => 'required',
      'email' => 'required|email|unique:users',
      'password' => 'required|min:5',
      'cedula' => 'required'

    ]);

    $usuario = User::create([

      'name' => $request->name,
      'email' => $request->email,
      'password' => Hash::make($request->password),
      'cedula' => $request->cedula

    ]);

    return response()->json([

      'message' => 'Usuario creado correctamente',
      'usuario' => $usuario

    ], 201);
  }

  public function show($id)
  {
    $usuario = User::findOrFail($id);

    return response()->json($usuario);
  }

  public function update(Request $request, $id)
  {

    $request->validate([

      'name' => 'required',
      'email' => 'required|email',
      'cedula' => 'required'

    ]);

    $usuario = User::findOrFail($id);

    $usuario->update([

      'name' => $request->name,
      'email' => $request->email,
      'cedula' => $request->cedula

    ]);

    return response()->json([

      'message' => 'Usuario actualizado',
      'usuario' => $usuario

    ]);
  }

  public function destroy($id)
  {

    $usuario = User::findOrFail($id);

    $usuario->delete();

    return response()->json([

      'message' => 'Usuario eliminado'

    ]);
  }
}
