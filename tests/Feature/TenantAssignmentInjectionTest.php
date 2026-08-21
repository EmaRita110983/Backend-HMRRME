<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * Tanto Patient como Cita (y el resto de los modelos clínicos) declaran
 * "admin_id" en su $fillable, porque el propio controlador lo necesita
 * asignar por código al crear. Ese mismo $fillable es la superficie de un
 * mass-assignment/IDOR si algún día un controlador pasa $request->all() en
 * vez de la lista explícita de campos. Este test fija el contrato: el
 * admin_id que llega en el body de la petición se ignora siempre, el
 * tenant real lo decide el servidor a partir del usuario autenticado (o
 * del paciente referenciado).
 */
class TenantAssignmentInjectionTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_admin_id_in_the_request_body_is_ignored_when_creating_a_patient(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();

        Sanctum::actingAs($doctorA, ['*']);

        $response = $this->postJson('/api/v1/patients', [
            'first_name' => 'Test',
            'last_name' => 'Injection',
            'admin_id' => $doctorB->id, // intento de inyección
        ])->assertStatus(201);

        $this->assertDatabaseHas('patients', [
            'id' => $response->json('patient.id'),
            'admin_id' => $doctorA->id,
        ]);

        $this->assertDatabaseMissing('patients', [
            'id' => $response->json('patient.id'),
            'admin_id' => $doctorB->id,
        ]);
    }

    public function test_admin_id_in_the_request_body_is_ignored_when_creating_a_cita(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();
        $patient = $this->createPatientFor($doctorA);

        Sanctum::actingAs($doctorA, ['*']);

        $response = $this->postJson('/api/v1/citas', [
            'patient_id' => $patient->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
            'admin_id' => $doctorB->id, // intento de inyección
        ])->assertStatus(201);

        // El admin_id de la cita se deriva SIEMPRE del paciente, nunca del
        // body, así que sigue siendo el de doctorA aunque el body pida otra cosa.
        $this->assertDatabaseHas('citas', [
            'id' => $response->json('cita.id'),
            'admin_id' => $doctorA->id,
        ]);
    }
}
