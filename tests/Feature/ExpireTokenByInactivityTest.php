<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireTokenByInactivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_usado_hace_menos_de_30_minutos_sigue_valido(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => true]);
        $token = $user->createToken('api-token');
        $token->accessToken->forceFill(['last_used_at' => now()->subMinutes(10)])->save();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/v1/auth/profile');

        $response->assertOk();
    }

    public function test_token_inactivo_por_mas_de_30_minutos_se_rechaza_y_se_borra(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => true]);
        $token = $user->createToken('api-token');
        $token->accessToken->forceFill(['last_used_at' => now()->subMinutes(31)])->save();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/v1/auth/profile');

        $response->assertStatus(401);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_token_nunca_usado_no_expira_por_inactividad(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => true]);
        $token = $user->createToken('api-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/v1/auth/profile');

        $response->assertOk();
    }
}
