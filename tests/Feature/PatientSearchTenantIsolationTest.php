<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * PatientController::index() acepta un parámetro "q" (usado por el
 * autocomplete de "Nueva cita" en el Dashboard, ver AUDITORIA.md — hallazgo
 * "Ningún listado pagina") que busca por nombre/cédula. Este test verifica
 * que esa búsqueda respeta el mismo aislamiento por tenant que el resto de
 * la API: un médico nunca debe poder encontrar, ni por nombre ni por
 * cédula, un paciente de otro médico, aunque conozca el dato exacto que
 * está buscando.
 */
class PatientSearchTenantIsolationTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_la_busqueda_de_pacientes_no_devuelve_pacientes_de_otro_medico(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();

        $this->createPatientFor($doctorA, [
            'first_name' => 'Marisol',
            'last_name' => 'Gomez',
            'cedula' => '001-1111111-1',
        ]);

        $this->createPatientFor($doctorB, [
            'first_name' => 'Zoraida',
            'last_name' => 'Ferreira',
            'cedula' => '002-2222222-2',
        ]);

        Sanctum::actingAs($doctorA, ['*']);

        // Por nombre del paciente ajeno.
        $this->getJson('/api/v1/patients?q=Zoraida')
            ->assertOk()
            ->assertJsonCount(0);

        // Por apellido del paciente ajeno.
        $this->getJson('/api/v1/patients?q=Ferreira')
            ->assertOk()
            ->assertJsonCount(0);

        // Por cédula exacta del paciente ajeno.
        $this->getJson('/api/v1/patients?q=002-2222222-2')
            ->assertOk()
            ->assertJsonCount(0);

        // La búsqueda sí funciona para el propio paciente (no es que el
        // parámetro "q" esté roto en general).
        $this->getJson('/api/v1/patients?q=Marisol')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['first_name' => 'Marisol']);
    }

    public function test_la_secretaria_tampoco_encuentra_pacientes_de_otro_medico_al_buscar(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();
        $secretariaDeA = $this->createSecretary($doctorA);

        $this->createPatientFor($doctorB, [
            'first_name' => 'Zoraida',
            'last_name' => 'Ferreira',
            'cedula' => '002-2222222-2',
        ]);

        Sanctum::actingAs($secretariaDeA, ['*']);

        $this->getJson('/api/v1/patients?q=Zoraida')
            ->assertOk()
            ->assertJsonCount(0);
    }
}
