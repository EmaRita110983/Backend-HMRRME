<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;



class AuthController extends Controller
{
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
                    "message" => "Usuario y/o contraseña errados. Le queda " .
                        $intentosRestantes .
                        ($intentosRestantes == 1 ? " intento." : " intentos.")
                ], 401);
            } else {

                return response()->json([
                    "message" => "Su usuario ha sido bloqueado por exceder el número de intentos permitidos."
                ], 429);
            }
        }


        $user = Auth::user();

        // Credenciales correctas pero cuenta desactivada (ver toggleStatus en
        // UserController): sin este chequeo, "Desactivar" no tenía ningún
        // efecto real y el usuario podía seguir iniciando sesión con normalidad.
        if (!$user->status) {
            Auth::logout();

            return response()->json([
                "message" => "Usuario inactivo"
            ], 403);
        }

        // Login correcto: limpiar intentos
        RateLimiter::clear($key);

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

    // Cambio de password obligatorio en el primer login de un médico creado
    // con la password genérica (ver UserController::store y middleware
    // EnsureCredentialsChanged). También puede usarse fuera de ese flujo:
    // no se limita a must_change_password=true.
    public function funCambiarPassword(Request $request)
    {
        $request->validate([
            "password" => "required|string|min:8|confirmed",
        ]);

        $user = $request->user();
        $user->password = bcrypt($request->password);
        $user->must_change_password = false;
        $user->save();

        return response()->json([
            "message" => "Contraseña actualizada correctamente",
            "user" => $user
        ]);
    }

    public function funLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            "message" => "Sesión cerrada exitosamente"
        ]);
    }
}
