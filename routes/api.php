<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\HistorialMedicoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\AutorizacionProcedimientoController;
use App\Http\Controllers\LicenciaMedicaController;
use App\Http\Controllers\DietaController;
use App\Http\Controllers\EstudioMedicoController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Rutas Auth
Route::prefix('v1/auth')->group(function () {

    

    // Públicas
    Route::post('login', [AuthController::class, 'funLogin']);

    // Protegidas
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('profile', [AuthController::class, 'funProfile']);
        Route::post('logout', [AuthController::class, 'funLogout']);
        Route::put('change-password', [AuthController::class, 'funCambiarPassword']);

        // funRegister/POST register se eliminó: el frontend no lo usa (los
        // usuarios se crean desde Gestión de Usuarios, ver
        // UserController::store) y el controlador nunca quedó alineado con
        // ese flujo real — no asignaba role/admin_id/created_by, así que
        // cualquier alta por acá quedaba como "secretaria fantasma" sin
        // médico asignado (admin_id NULL), rompiendo en el primer intento
        // de crear un paciente (patients.admin_id es NOT NULL).

    });

});





// Rutas administrativas protegidas
Route::middleware([
    'auth:sanctum',
    'credentials.changed'
])->prefix('v1')->group(function () {


    // Branding del tenant: cualquiera autenticado puede consultar el suyo
    // (para pintar logo/nombre/color en el topbar).
    Route::get('branding', [BrandingController::class, 'show']);

    // El médico (admin) fija su propio color principal desde la paleta de
    // colores de la app — es la única vía para asignar brand_color; el
    // Superadmin ya no lo hace por él (ver updateForUser más abajo).
    Route::middleware('role:admin')->put('branding/color', [BrandingController::class, 'updateOwnColor']);


    // Usuarios: solo Superadmin y Admin
    Route::middleware('role:superadmin,admin')->group(function () {

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        // Antes de users/{user}: si no, "eliminados"/"stats" se interpretarían como un id.
        Route::get('users/eliminados/buscar', [UserController::class, 'buscarEliminado']);
        Route::get('users/stats/conteo', [UserController::class, 'stats']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::put('users/{user}/status', [UserController::class, 'toggleStatus']);
        Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::put('users/{id}/restore', [UserController::class, 'restore']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

    });


    // Branding de un médico puntual: solo el Superadmin lo gestiona, desde
    // la pantalla de Usuarios, relacionando la cuenta del médico por id.
    // El admin (médico) ya no puede editar su propio branding.
    Route::middleware('role:superadmin')->group(function () {

        Route::get('users/{user}/branding', [BrandingController::class, 'showForUser']);
        Route::put('users/{user}/branding', [BrandingController::class, 'updateForUser']);
        Route::post('users/{user}/branding/logo', [BrandingController::class, 'uploadLogoForUser']);
        Route::post('users/{user}/branding/header-icon-left', [BrandingController::class, 'uploadHeaderIconLeftForUser']);
        Route::post('users/{user}/branding/header-icon-right', [BrandingController::class, 'uploadHeaderIconRightForUser']);

    });


    // Pacientes
    // Antes del apiResource: si no, "eliminados" se interpretaría como un id
    // de paciente (GET patients/{patient}).
    Route::get('patients/eliminados/buscar', [PatientController::class, 'buscarEliminado']);
    Route::apiResource('patients', PatientController::class);

    // Citas: médico, secretaria y superadmin (misma disponibilidad que pacientes)
    Route::apiResource('citas', CitaController::class);

    // Historial médico, recetas y autorizaciones: solo Superadmin y Admin (la secretaria no accede)
    Route::middleware('role:superadmin,admin')->group(function () {

        Route::apiResource('historial', HistorialMedicoController::class);
        Route::apiResource('recetas', RecetaController::class);
        // Laravel singulariza "autorizaciones" mal por defecto (da "autorizacione",
        // no "autorizacion"), lo que rompe el route-model-binding: el parámetro de
        // ruta no coincide con el nombre del argumento del controlador y el modelo
        // nunca se resuelve desde la BD, causando un 403 falso al editar/eliminar.
        Route::apiResource('autorizaciones', AutorizacionProcedimientoController::class)
            ->parameters(['autorizaciones' => 'autorizacion']);
        Route::apiResource('licencias', LicenciaMedicaController::class);
        Route::apiResource('dietas', DietaController::class);
        Route::apiResource('estudios', EstudioMedicoController::class);

    });

});

// Descarga de estudios médicos: por URL firmada de corta duración (ver
// EstudioMedico::getArchivoUrlAttribute), no por auth:sanctum. El archivo
// vive en el disco privado y el frontend abre esta URL directamente en una
// pestaña nueva (window.open), que no puede enviar el header Authorization
// con el Bearer token — por eso la propia firma, generada solo después de
// pasar por EstudioMedicoPolicy::view al serializar el estudio, es la
// autorización real. Antes el archivo se servía sin ningún control desde el
// disco público y la URL, una vez conocida, quedaba accesible para siempre.
Route::get('v1/estudios/{estudio}/archivo', [EstudioMedicoController::class, 'archivo'])
    ->middleware('signed')
    ->name('estudios.archivo');
