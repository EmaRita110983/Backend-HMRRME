<?php

namespace Tests\Feature;

use App\Models\Cita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * El aviso de "horas ocupadas" en Dashboard.vue es solo informativo — esto
 * verifica el bloqueo real, del lado del servidor, que impide guardar dos
 * citas pendientes a la misma fecha+hora del mismo médico (ver Howard: "me
 * está permitiendo dos citas a la misma hora, eso no se puede").
 */
class CitaDobleReservaTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_no_se_puede_crear_una_cita_a_la_misma_fecha_y_hora_de_otra_pendiente(): void
    {
        $doctor = $this->createDoctor();
        $pacienteA = $this->createPatientFor($doctor);
        $pacienteB = $this->createPatientFor($doctor);

        Cita::create([
            'patient_id' => $pacienteA->id,
            'admin_id' => $doctor->id,
            'created_by' => $doctor->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
            'estado' => 'pendiente',
        ]);

        Sanctum::actingAs($doctor, ['*']);

        $response = $this->postJson('/api/v1/citas', [
            'patient_id' => $pacienteB->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, Cita::where('fecha', '2026-09-01')->where('hora', '09:00')->count());
    }

    public function test_no_se_puede_editar_una_cita_para_que_choque_con_otra_pendiente(): void
    {
        $doctor = $this->createDoctor();
        $pacienteA = $this->createPatientFor($doctor);
        $pacienteB = $this->createPatientFor($doctor);

        Cita::create([
            'patient_id' => $pacienteA->id,
            'admin_id' => $doctor->id,
            'created_by' => $doctor->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
            'estado' => 'pendiente',
        ]);

        $citaB = Cita::create([
            'patient_id' => $pacienteB->id,
            'admin_id' => $doctor->id,
            'created_by' => $doctor->id,
            'fecha' => '2026-09-01',
            'hora' => '10:00',
            'estado' => 'pendiente',
        ]);

        Sanctum::actingAs($doctor, ['*']);

        $response = $this->putJson("/api/v1/citas/{$citaB->id}", [
            'fecha' => '2026-09-01',
            'hora' => '09:00',
        ]);

        $response->assertStatus(422);
        $citaB->refresh();
        $this->assertSame('10:00:00', $citaB->hora);
    }

    public function test_guardar_una_cita_sin_cambiar_su_propia_hora_no_choca_contra_si_misma(): void
    {
        $doctor = $this->createDoctor();
        $paciente = $this->createPatientFor($doctor);

        $cita = Cita::create([
            'patient_id' => $paciente->id,
            'admin_id' => $doctor->id,
            'created_by' => $doctor->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
            'estado' => 'pendiente',
        ]);

        Sanctum::actingAs($doctor, ['*']);

        $this->putJson("/api/v1/citas/{$cita->id}", [
            'fecha' => '2026-09-01',
            'hora' => '09:00',
            'motivo' => 'Sin cambio de hora, solo el motivo',
        ])->assertOk();
    }

    public function test_una_hora_ocupada_por_una_cita_cancelada_queda_libre(): void
    {
        $doctor = $this->createDoctor();
        $pacienteA = $this->createPatientFor($doctor);
        $pacienteB = $this->createPatientFor($doctor);

        Cita::create([
            'patient_id' => $pacienteA->id,
            'admin_id' => $doctor->id,
            'created_by' => $doctor->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
            'estado' => 'cancelada',
        ]);

        Sanctum::actingAs($doctor, ['*']);

        $this->postJson('/api/v1/citas', [
            'patient_id' => $pacienteB->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
        ])->assertStatus(201);
    }

    public function test_medicos_de_distinto_tenant_pueden_compartir_la_misma_fecha_y_hora(): void
    {
        $doctorA = $this->createDoctor();
        $doctorB = $this->createDoctor();
        $pacienteA = $this->createPatientFor($doctorA);
        $pacienteB = $this->createPatientFor($doctorB);

        Cita::create([
            'patient_id' => $pacienteA->id,
            'admin_id' => $doctorA->id,
            'created_by' => $doctorA->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
            'estado' => 'pendiente',
        ]);

        Sanctum::actingAs($doctorB, ['*']);

        $this->postJson('/api/v1/citas', [
            'patient_id' => $pacienteB->id,
            'fecha' => '2026-09-01',
            'hora' => '09:00',
        ])->assertStatus(201);
    }
}
