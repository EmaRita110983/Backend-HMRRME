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
    'auth:sanctum',
    'role:superadmin'
])->prefix('v1')->group(function () {

    Route::apiResource('users', UserController::class);

    Route::apiResource('role', RoleController::class);

});