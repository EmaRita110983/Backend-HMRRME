<?php

namespace Tests\Feature;

use App\Models\Cita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * Regla de negocio 5: al "borrar" una secretaria, debe dejar de aparecer
 * entre los asistentes del médico, pero el registro se conserva (soft
 * delete) junto con la autoría de lo que ella creó. Cubre el ciclo
 * completo: crear → aparece en el listado → eliminar → desaparece del
 * listado pero sigue en la base → se puede reactivar.
 */
class SecretaryLifecycleTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_deleting_a_secretary_hides_her_without_losing_her_authorship_and_allows_restore(): void
    {
        $doctor = $this->createDoctor();
        $secretary = $this->createSecretary($doctor);
        $patient = $this->createPatientFor($doctor);

        $cita = Cita::create([
            'patient_id' => $patient->id,
            'admin_id' => $doctor->id,
            'created_by' => $secretary->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
        ]);

        Sanctum::actingAs($doctor, ['*']);

        // Antes de borrarla, aparece en el listado de asistentes del médico.
        $this->getJson('/api/v1/users')
            ->assertJsonFragment(['id' => $secretary->id]);

        $this->deleteJson("/api/v1/users/{$secretary->id}")->assertOk();

        // Ya no aparece en el listado normal...
        $this->getJson('/api/v1/users')
            ->assertJsonMissing(['id' => $secretary->id]);

        // ...pero el registro sigue existiendo, marcado inactivo, no borrado
        // físicamente.
        $this->assertSoftDeleted('users', ['id' => $secretary->id]);
        $this->assertDatabaseHas('users', ['id' => $secretary->id, 'status' => false]);

        // Y la cita que ella agendó conserva su autoría intacta.
        $this->assertDatabaseHas('citas', [
            'id' => $cita->id,
            'created_by' => $secretary->id,
            'deleted_at' => null,
        ]);

        // El médico puede reactivarla, y vuelve a quedar activa.
        $this->putJson("/api/v1/users/{$secretary->id}/restore")->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $secretary->id,
            'status' => true,
            'deleted_at' => null,
        ]);

        $this->getJson('/api/v1/users')
            ->assertJsonFragment(['id' => $secretary->id]);
    }
}
