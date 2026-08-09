<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\HistorialMedicoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\CitaController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Rutas Auth
Route::prefix('v1/auth')->group(function () {

    

    // Públicas
    Route::post('register', [AuthController::class, 'funRegister']);
    Route::post('login', [AuthController::class, 'funLogin']);

    // Protegidas
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('profile', [AuthController::class, 'funProfile']);
        Route::post('logout', [AuthController::class, 'funLogout']);

    });

});





// Rutas administrativas protegidas
Route::middleware([
    'auth:sanctum'
])->prefix('v1')->group(function () {


    // Usuarios: solo Superadmin y Admin
    Route::middleware('role:superadmin,admin')->group(function () {

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::put('users/{user}/status', [UserController::class, 'toggleStatus']);

    });


    // Pacientes
    Route::apiResource('patients', PatientController::class);

    // Citas: médico, secretaria y superadmin (misma disponibilidad que pacientes)
    Route::apiResource('citas', CitaController::class);

    // Historial médico y recetas: solo Superadmin y Admin (la secretaria no accede)
    Route::middleware('role:superadmin,admin')->group(function () {

        Route::apiResource('historial', HistorialMedicoController::class);
        Route::apiResource('recetas', RecetaController::class);

    });

});
