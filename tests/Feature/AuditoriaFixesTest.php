<?php

namespace Tests\Feature;

use App\Models\HistorialMedico;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditoriaFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_no_puede_sondear_email_ajeno_editando_un_usuario_que_no_es_suyo(): void
    {
        // authorize() antes que validate() en UserController::update(): un
        // admin que intenta editar un usuario de OTRO tenant debe recibir
        // 403 (autorización), nunca 422 (que revelaría si el email/cédula
        // ya existe en el sistema, cruzando tenants).
        $medicoA = User::factory()->create(['role' => 'admin', 'status' => true]);
        $medicoB = User::factory()->create(['role' => 'admin', 'status' => true]);
        $secretariaDeB = User::factory()->create([
            'role' => 'secretaria',
            'admin_id' => $medicoB->id,
            'status' => true,
        ]);

        Sanctum::actingAs($medicoA, ['*']);

        $response = $this->putJson("/api/v1/users/{$secretariaDeB->id}", [
            'name' => 'Intento',
            'email' => $medicoB->email, // email que sabemos que ya existe
            'cedula' => 'X-0000000-0',
            'role' => 'secretaria',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_no_puede_crear_paciente_con_admin_id_de_alguien_que_no_es_medico(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin', 'status' => true]);
        $secretaria = User::factory()->create(['role' => 'secretaria', 'status' => true]);

        Sanctum::actingAs($superadmin, ['*']);

        $response = $this->postJson('/api/v1/patients', [
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'admin_id' => $secretaria->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('patients', ['first_name' => 'Juan']);
    }

    public function test_estudio_no_puede_enlazar_historial_de_otro_paciente(): void
    {
        $medico = User::factory()->create(['role' => 'admin', 'status' => true]);
        $pacienteA = Patient::create([
            'admin_id' => $medico->id,
            'created_by' => $medico->id,
            'first_name' => 'A',
            'last_name' => 'A',
        ]);
        $pacienteB = Patient::create([
            'admin_id' => $medico->id,
            'created_by' => $medico->id,
            'first_name' => 'B',
            'last_name' => 'B',
        ]);
        $historialDeB = HistorialMedico::create([
            'patient_id' => $pacienteB->id,
            'admin_id' => $medico->id,
            'created_by' => $medico->id,
            'fecha_consulta' => now(),
            'motivo_consulta' => 'x',
            'diagnostico' => 'x',
        ]);

        Sanctum::actingAs($medico, ['*']);

        $response = $this->postJson('/api/v1/estudios', [
            'patient_id' => $pacienteA->id,
            'historial_medico_id' => $historialDeB->id,
            'tipo' => 'otro',
            'fecha_estudio' => now()->toDateString(),
            'archivo' => \Illuminate\Http\UploadedFile::fake()->create('estudio.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422);
    }
}
