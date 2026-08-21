<?php

namespace Tests\Feature;

use App\Models\HistorialMedico;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * La paginación agregada a los listados (ver AUDITORIA.md, "Ningún listado
 * pagina") es aditiva a propósito: sin "page" en la request, el
 * comportamiento debe ser idéntico al de siempre (array plano, sin
 * envoltorio). Este test cubre ambas puntas: que no paginar sigue
 * funcionando igual, y que paginar de verdad respeta el aislamiento por
 * tenant (mismo criterio que PatientSearchTenantIsolationTest).
 */
class PaginationTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_sin_page_el_listado_de_pacientes_sigue_siendo_un_array_plano(): void
    {
        $doctor = $this->createDoctor();
        $this->createPatientFor($doctor);
        $this->createPatientFor($doctor);

        Sanctum::actingAs($doctor, ['*']);

        $response = $this->getJson('/api/v1/patients');

        $response->assertOk();
        $this->assertIsArray($response->json());
        $this->assertCount(2, $response->json());
        // No debe tener forma de paginador (sin "data"/"current_page").
        $this->assertArrayNotHasKey('current_page', $response->json());
    }

    public function test_con_page_el_listado_de_pacientes_pagina_de_verdad_y_respeta_el_tenant(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();

        for ($i = 0; $i < 3; $i++) {
            $this->createPatientFor($doctorA);
        }
        $this->createPatientFor($doctorB);

        Sanctum::actingAs($doctorA, ['*']);

        $response = $this->getJson('/api/v1/patients?page=1&per_page=2');

        $response->assertOk();
        $response->assertJsonPath('total', 3); // solo los 3 de doctorA, no el de doctorB
        $response->assertJsonPath('per_page', 2);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_con_page_el_listado_de_historial_pagina_y_respeta_el_tenant(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();
        $patientA = $this->createPatientFor($doctorA);
        $patientB = $this->createPatientFor($doctorB);

        HistorialMedico::create([
            'patient_id' => $patientA->id,
            'admin_id' => $doctorA->id,
            'created_by' => $doctorA->id,
            'fecha_consulta' => '2026-09-01',
            'motivo_consulta' => 'Control',
            'diagnostico' => 'Sano',
        ]);

        HistorialMedico::create([
            'patient_id' => $patientB->id,
            'admin_id' => $doctorB->id,
            'created_by' => $doctorB->id,
            'fecha_consulta' => '2026-09-01',
            'motivo_consulta' => 'Control',
            'diagnostico' => 'Sano',
        ]);

        Sanctum::actingAs($doctorA, ['*']);

        $response = $this->getJson('/api/v1/historial?page=1');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
    }

    public function test_el_selector_de_medicos_por_role_no_filtra_por_tenant_para_el_superadmin_y_pagina_omite_secretarias(): void
    {
        $superadmin = $this->createSuperAdmin();
        $doctorA = $this->createDoctor();
        $this->createSecretary($doctorA);

        Sanctum::actingAs($superadmin, ['*']);

        $response = $this->getJson('/api/v1/users/stats/conteo');
        $response->assertOk();
        $this->assertSame(1, $response->json('medicos'));

        $response = $this->getJson('/api/v1/users?role=admin');
        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($doctorA->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_una_secretaria_recibe_403_al_llamar_users_con_role_admin(): void
    {
        $doctor = $this->createDoctor();
        $secretaria = $this->createSecretary($doctor);

        Sanctum::actingAs($secretaria, ['*']);

        $this->getJson('/api/v1/users?role=admin')->assertStatus(403);
    }

    public function test_un_medico_llamando_users_con_role_admin_no_recibe_medicos_de_otros_tenants(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor(); // otro tenant, no debe filtrarse

        Sanctum::actingAs($doctorA, ['*']);

        $response = $this->getJson('/api/v1/users?role=admin');

        // Pasa el middleware (admin sí está permitido en la ruta), pero la
        // query queda scopeada a admin_id = doctorA->id antes de aplicar
        // role=admin — ningún usuario propio tiene esa combinación, así que
        // el resultado correcto es vacío, nunca los médicos de otro tenant.
        $response->assertOk();
        $this->assertSame([], $response->json());
    }
}
