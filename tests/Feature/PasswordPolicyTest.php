<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * El mínimo de contraseña se subió de 8 a 12 caracteres (ver AUDITORIA.md,
 * sección 6) — mismo piso que las contraseñas generadas al azar
 * (Str::password(12)) para médicos nuevos y resets. Longitud, no
 * complejidad forzada, siguiendo NIST SP 800-63B.
 */
class PasswordPolicyTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_una_contrasena_de_11_caracteres_es_rechazada_al_crear_una_secretaria(): void
    {
        $doctor = $this->createDoctor();
        Sanctum::actingAs($doctor, ['*']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Nueva Secretaria',
            'email' => 'secretaria.nueva@mail.com',
            'cedula' => '001-0000001-1',
            'role' => 'secretaria',
            'password' => '12345678901', // 11 caracteres
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_una_contrasena_de_12_caracteres_es_aceptada_al_crear_una_secretaria(): void
    {
        $doctor = $this->createDoctor();
        Sanctum::actingAs($doctor, ['*']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Nueva Secretaria',
            'email' => 'secretaria.nueva@mail.com',
            'cedula' => '001-0000001-1',
            'role' => 'secretaria',
            'password' => '123456789012', // 12 caracteres
        ]);

        $response->assertStatus(201);
    }

    public function test_cambiar_password_rechaza_menos_de_12_caracteres(): void
    {
        $doctor = $this->createDoctor();
        Sanctum::actingAs($doctor, ['*']);

        $response = $this->putJson('/api/v1/auth/change-password', [
            'password' => '12345678901', // 11 caracteres
            'password_confirmation' => '12345678901',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }
}
