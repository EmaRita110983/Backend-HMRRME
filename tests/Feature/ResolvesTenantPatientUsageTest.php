<?php

namespace Tests\Feature;

use App\Http\Controllers\AutorizacionProcedimientoController;
use App\Http\Controllers\Concerns\ResolvesTenantPatient;
use App\Http\Controllers\DietaController;
use App\Http\Controllers\EstudioMedicoController;
use App\Http\Controllers\HistorialMedicoController;
use App\Http\Controllers\LicenciaMedicaController;
use App\Http\Controllers\RecetaController;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// No prueba comportamiento (eso ya lo cubren AuditoriaFixesTest y
// CrossTenantIsolationTest): solo deja constancia estructural de que los 6
// controladores de documentos clínicos comparten el mismo punto de
// verificación de tenant, para notar si un controlador nuevo se agrega
// copiando el patrón a mano en vez de usar el trait (ver AUDITORIA.md,
// "Ausencia total de Form Requests y API Resources").
class ResolvesTenantPatientUsageTest extends TestCase
{
    public static function controladoresClinicos(): array
    {
        return [
            [HistorialMedicoController::class],
            [RecetaController::class],
            [AutorizacionProcedimientoController::class],
            [LicenciaMedicaController::class],
            [DietaController::class],
            [EstudioMedicoController::class],
        ];
    }

    #[DataProvider('controladoresClinicos')]
    public function test_controlador_usa_el_trait_compartido(string $controlador): void
    {
        $this->assertContains(
            ResolvesTenantPatient::class,
            class_uses_recursive($controlador),
            "{$controlador} debería usar ResolvesTenantPatient en vez de repetir la verificación de tenant a mano."
        );
    }
}
