<?php

namespace Tests\Feature;

use App\Models\AutorizacionProcedimiento;
use App\Models\Cita;
use App\Models\Dieta;
use App\Models\EstudioMedico;
use App\Models\HistorialMedico;
use App\Models\LicenciaMedica;
use App\Models\Patient;
use App\Models\Receta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * El hallazgo #1 de la auditoría (multitenant): un médico jamás debe poder
 * ver, editar ni borrar un registro de OTRO médico, aunque conozca (o
 * adivine) su id. Este test recorre los 8 recursos clínicos que dependen de
 * PatientPolicy/CitaPolicy/HistorialMedicoPolicy/RecetaPolicy/
 * AutorizacionProcedimientoPolicy/LicenciaMedicaPolicy/DietaPolicy/
 * EstudioMedicoPolicy y verifica la regla de negocio 1 en los tres verbos
 * de escritura/lectura a la vez, contra la API real (no contra la policy
 * en aislado), para detectar automáticamente el tipo de omisión que la
 * auditoría manual encontró (ver EstudioMedicoController::store).
 */
class CrossTenantIsolationTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_a_doctor_cannot_view_update_or_delete_another_doctors_clinical_records(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();
        $patientB = $this->createPatientFor($doctorB);

        foreach ($this->clinicalResources($doctorB, $patientB) as $name => $spec) {
            Sanctum::actingAs($doctorA, ['*']);

            $this->getJson($spec['uri'])
                ->assertStatus(403, "GET {$spec['uri']} ($name) debería ser 403 para un médico ajeno");

            $this->putJson($spec['uri'], $spec['update'])
                ->assertStatus(403, "PUT {$spec['uri']} ($name) debería ser 403 para un médico ajeno");

            $this->deleteJson($spec['uri'])
                ->assertStatus(403, "DELETE {$spec['uri']} ($name) debería ser 403 para un médico ajeno");

            // No solo la respuesta HTTP: el registro debe seguir intacto y
            // sin borrar en la base, por si algún día la policy bloquea la
            // respuesta pero el controlador ya mutó el dato antes de chequear.
            $record = $spec['record'];
            $this->assertDatabaseHas($record->getTable(), [
                'id' => $record->id,
                'deleted_at' => null,
            ]);
        }
    }

    private function clinicalResources(User $doctorB, Patient $patientB): array
    {
        $base = [
            'patient_id' => $patientB->id,
            'admin_id' => $doctorB->id,
            'created_by' => $doctorB->id,
        ];

        $cita = Cita::create($base + ['fecha' => '2026-09-01', 'hora' => '09:00']);

        $historial = HistorialMedico::create($base + [
            'fecha_consulta' => '2026-09-01',
            'motivo_consulta' => 'Control',
            'diagnostico' => 'Sano',
        ]);

        $receta = Receta::create($base + [
            'fecha_emision' => '2026-09-01',
            'medicamentos' => 'Paracetamol',
        ]);

        $autorizacion = AutorizacionProcedimiento::create($base + [
            'fecha' => '2026-09-01',
            'historia_enfermedad' => 'N/A',
            'diagnostico_presuntivo' => 'N/A',
        ]);

        $licencia = LicenciaMedica::create($base + [
            'fecha' => '2026-09-01',
            'constatado' => 'N/A',
            'recomendacion' => 'N/A',
            'certificacion_cierre' => 'N/A',
        ]);

        $dieta = Dieta::create($base + ['fecha' => '2026-09-01', 'dieta' => 'N/A']);

        $estudio = EstudioMedico::create($base + [
            'tipo' => 'laboratorio',
            'fecha_estudio' => '2026-09-01',
            'archivo_path' => 'estudios/fake.pdf',
            'archivo_nombre_original' => 'fake.pdf',
            'archivo_mime' => 'application/pdf',
            'archivo_tamano' => 100,
        ]);

        return [
            'patients' => [
                'record' => $patientB,
                'uri' => "/api/v1/patients/{$patientB->id}",
                'update' => ['first_name' => 'X', 'last_name' => 'Y'],
            ],
            'citas' => [
                'record' => $cita,
                'uri' => "/api/v1/citas/{$cita->id}",
                'update' => ['fecha' => '2026-09-02', 'hora' => '10:00'],
            ],
            'historial' => [
                'record' => $historial,
                'uri' => "/api/v1/historial/{$historial->id}",
                'update' => ['fecha_consulta' => '2026-09-02', 'motivo_consulta' => 'X', 'diagnostico' => 'Y'],
            ],
            'recetas' => [
                'record' => $receta,
                'uri' => "/api/v1/recetas/{$receta->id}",
                'update' => ['fecha_emision' => '2026-09-02', 'medicamentos' => 'X'],
            ],
            'autorizaciones' => [
                'record' => $autorizacion,
                'uri' => "/api/v1/autorizaciones/{$autorizacion->id}",
                'update' => ['fecha' => '2026-09-02', 'historia_enfermedad' => 'X', 'diagnostico_presuntivo' => 'Y'],
            ],
            'licencias' => [
                'record' => $licencia,
                'uri' => "/api/v1/licencias/{$licencia->id}",
                'update' => ['fecha' => '2026-09-02', 'constatado' => 'X', 'recomendacion' => 'Y', 'certificacion_cierre' => 'Z'],
            ],
            'dietas' => [
                'record' => $dieta,
                'uri' => "/api/v1/dietas/{$dieta->id}",
                'update' => ['fecha' => '2026-09-02', 'dieta' => 'X'],
            ],
            'estudios' => [
                'record' => $estudio,
                'uri' => "/api/v1/estudios/{$estudio->id}",
                'update' => ['tipo' => 'laboratorio', 'fecha_estudio' => '2026-09-02'],
            ],
        ];
    }
}
