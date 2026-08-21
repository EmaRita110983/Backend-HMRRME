<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * AuthController::funLogin bloquea el login tras 3 intentos fallidos
 * (mismo email + IP) por 5 minutos, devolviendo 429. Es la única defensa
 * contra fuerza bruta que tenía la API antes de esta auditoría (ver
 * AppServiceProvider::boot() para el límite general que ahora cubre el
 * resto de los endpoints).
 */
class LoginRateLimitTest extends TestCase
{
    use CreatesTenantUsers;
    use RefreshDatabase;

    public function test_login_is_locked_out_after_three_failed_attempts(): void
    {
        $this->createDoctor([
            'email' => 'medico@test.com',
            'password' => 'secreta123',
        ]);

        // Intentos 1 y 2: credenciales rechazadas, pero todavía se puede seguir intentando.
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'medico@test.com',
                'password' => 'incorrecta',
            ])->assertStatus(401);
        }

        // Intento 3: agota el límite y ya responde bloqueado.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'medico@test.com',
            'password' => 'incorrecta',
        ])->assertStatus(429);

        // Bloqueado incluso con la contraseña correcta, mientras dure la ventana.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'medico@test.com',
            'password' => 'secreta123',
        ])->assertStatus(429);
    }

    public function test_login_succeeds_with_correct_credentials_and_returns_a_token(): void
    {
        $this->createDoctor([
            'email' => 'medico2@test.com',
            'password' => 'secreta123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'medico2@test.com',
            'password' => 'secreta123',
        ])
            ->assertOk()
            ->assertJsonStructure(['message', 'token', 'user']);
    }
}
