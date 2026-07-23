<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Rutas Auth
Route::prefix('v1/auth')->group(function () {

    Route::post("register", [AuthController::class, "funRegister"]);
    Route::post("login", [AuthController::class, "funLogin"]);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('profile', [AuthController::class, "funProfile"]);
        Route::post('logout', [AuthController::class, "funLogout"]);
    });

});


// Rutas administrativas protegidas
Route::middleware([
    'auth:sanctum'
])->prefix('v1')->group(function () {

    // Usuarios: Superadmin y Admin
    Route::middleware('role:superadmin,admin')->group(function () {

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);

    });

    // Solo Superadmin
    Route::middleware('role:superadmin,admin')->group(function () {

        Route::delete('users/{user}', [UserController::class, 'destroy']);

        Route::apiResource('role', RoleController::class);

    });

});

    Route::apiResource('role', RoleController::class);

