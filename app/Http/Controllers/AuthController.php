<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class AuthController extends Controller
{
    public function funRegister(Request $request){
        // Validaciones
        $request->validate([
            "name" => "required|string",
            "email" => "required|email|unique:users",
            "password" => "required|string|min:6",
            "cedula" =>"required|string|unique:users,cedula",
        ]);
        // Guardar en la base de datos
        $usuario = new User();
        $usuario->name = $request->name;
        $usuario->email = $request->email;
        $usuario->password = bcrypt($request->password);
        $usuario->cedula = $request->cedula;
        $usuario->save();

        return response()->json([
            "message" => "Usuario registrado exitosamente",
            "user" => $usuario
        ]);
    }

   public function funLogin(Request $request)
{
    $credenciales = $request->validate([
        "email" => "required|email",
        "password" => "required|string",
    ]);

    if (!Auth::attempt($credenciales)) {
        return response()->json([
            "message" => "Usuario o contraseña incorrectos."
        ], 401);
    }

    $token = $request->user()->createToken("TokenAuth")->plainTextToken;

    return response()->json([
        "access_token" => $token,
        "usuario" => $request->user()
    ]);
}

    public function funProfile(Request $request){
        return response()->json($request->user());
    }
    public function funLogout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            "message" => "Sesión cerrada exitosamente"
        ]);
    }

}