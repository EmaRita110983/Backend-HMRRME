<?php

namespace Tests\Feature;

use App\Models\Cita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * Regla de negocio 2: la secretaria crea y edita pacientes y citas, pero
 * nunca las borra, y no puede editar un paciente ya creado (solo el
 * médico). Para citas, sí puede corregir fecha/hora/motivo de una ya
 * creada (para arreglar errores de carga, ver AUDITORIA.md) — pero
 * "estado" (marcar atendida/cancelada) es una decisión clínica que se le
 * ignora aunque lo mande, y sigue sin poder borrar citas. Verifica
 * CitaPolicy::update()/delete() y PatientPolicy::update()/delete() sobre
 * datos de SU PROPIO tenant (el aislamiento cross-tenant ya lo cubre
 * CrossTenantIsolationTest) — este test es sobre el nivel de permiso
 * dentro del mismo tenant, no sobre a quién pertenece el dato.
 */
class SecretaryScopeTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_secretary_can_create_but_not_update_or_delete_patients(): void
    {
        $doctor = $this->createDoctor();
        $secretary = $this->createSecretary($doctor);
        $patient = $this->createPatientFor($doctor);

        Sanctum::actingAs($secretary, ['*']);

        $this->postJson('/api/v1/patients', [
            'first_name' => 'Nuevo',
            'last_name' => 'Paciente',
        ])->assertStatus(201);

        $this->putJson("/api/v1/patients/{$patient->id}", [
            'first_name' => 'X',
            'last_name' => 'Y',
        ])->assertStatus(403);

        $this->deleteJson("/api/v1/patients/{$patient->id}")->assertStatus(403);

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'deleted_at' => null]);
    }

    public function test_secretary_can_correct_a_citas_fecha_hora_y_motivo_but_not_its_estado_ni_borrarla(): void
    {
        $doctor = $this->createDoctor();
        $secretary = $this->createSecretary($doctor);
        $patient = $this->createPatientFor($doctor);

        Sanctum::actingAs($secretary, ['*']);

        $this->postJson('/api/v1/citas', [
            'patient_id' => $patient->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
        ])->assertStatus(201);

        $cita = Cita::where('patient_id', $patient->id)->firstOrFail();

        // Corrige un error de carga (hora/motivo) — esto es lo nuevo.
        $this->putJson("/api/v1/citas/{$cita->id}", [
            'fecha' => '2026-09-01',
            'hora' => '10:00',
            'motivo' => 'Motivo corregido',
            'estado' => 'completada', // intenta marcarla atendida igual
        ])->assertOk();

        $cita->refresh();
        $this->assertSame('10:00:00', $cita->hora);
        $this->assertSame('Motivo corregido', $cita->motivo);
        // "estado" se ignora del lado del servidor aunque lo haya mandado.
        $this->assertSame('pendiente', $cita->estado);

        $this->deleteJson("/api/v1/citas/{$cita->id}")->assertStatus(403);
        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'deleted_at' => null]);
    }

    public function test_secretaries_cannot_list_or_view_users_at_all(): void
    {
        $doctor = $this->createDoctor();
        $secretaryA = $this->createSecretary($doctor);
        $secretaryB = $this->createSecretary($doctor);

        // Regla 3: las secretarias no pueden verse entre ellas. La ruta de
        // usuarios está cerrada por completo a role=secretaria (ver
        // routes/api.php: role:superadmin,admin), así que ni siquiera puede
        // listar para intentar encontrar a otra secretaria del mismo médico.
        Sanctum::actingAs($secretaryA, ['*']);

        $this->getJson('/api/v1/users')->assertStatus(403);
        $this->getJson("/api/v1/users/{$secretaryB->id}")->assertStatus(403);
    }
}
