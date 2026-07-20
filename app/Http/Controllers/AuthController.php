<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;



class AuthController extends Controller
{
    public function funRegister(Request $request)
    {
        // Validaciones
        $request->validate([
            "name" => "required|string",
            "email" => "required|email|unique:users",
            "password" => "required|string|min:6",
            "cedula" => "required|string|unique:users,cedula",
            "role" => "nullable|in:superadmin,admin,secretaria",
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
        $request->validate([
            "email" => "required|email",
            "password" => "required|string"
        ]);


        $key = Str::lower($request->email) . '|' . $request->ip();


        // Máximo de intentos permitidos
        $maxIntentos = 3;


        // Verificar si ya excedió los intentos
        if (RateLimiter::tooManyAttempts($key, $maxIntentos)) {

            return response()->json([
                "message" => "Su usuario ha sido bloqueado por exceder el número de intentos permitidos."
            ], 429);
        }


        // Intentar autenticar
        if (!Auth::attempt($request->only("email", "password"))) {

            // Registrar intento fallido
            RateLimiter::hit($key, 300); // 5 minutos


            $intentosRestantes = $maxIntentos - RateLimiter::attempts($key);


            if ($intentosRestantes > 0) {

                return response()->json([
                    "message" => "Usuario o contraseña incorrectos. Le queda " .
                        $intentosRestantes .
                        ($intentosRestantes == 1 ? " intento." : " intentos.")
                ], 401);
            } else {

                return response()->json([
                    "message" => "Su usuario ha sido bloqueado por exceder el número de intentos permitidos."
                ], 429);
            }
        }


        // Login correcto: limpiar intentos
        RateLimiter::clear($key);


        $user = Auth::user();

        $token = $user->createToken("api-token")->plainTextToken;


        return response()->json([
            "message" => "Login correcto",
            "token" => $token,
            "user" => $user
        ]);
    }

    public function funProfile(Request $request)
    {
        return response()->json($request->user());
    }
    public function funLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            "message" => "Sesión cerrada exitosamente"
        ]);
    }
}
