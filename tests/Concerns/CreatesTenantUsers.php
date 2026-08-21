<?php

namespace Tests\Concerns;

use App\Models\Patient;
use App\Models\User;

/**
 * Fixtures mínimas para los tests de aislamiento multitenant: un médico es
 * un tenant, una secretaria pertenece a un médico vía admin_id, un paciente
 * pertenece a un médico vía admin_id (ver CLAUDE.md del backend).
 */
trait CreatesTenantUsers
{
    protected function createDoctor(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'admin',
            'admin_id' => null,
            'status' => true,
        ], $attributes));
    }

    protected function createSecretary(User $doctor, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'secretaria',
            'admin_id' => $doctor->id,
            'created_by' => $doctor->id,
            'status' => true,
        ], $attributes));
    }

    protected function createSuperAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'superadmin',
            'admin_id' => null,
            'status' => true,
        ], $attributes));
    }

    protected function createPatientFor(User $doctor, array $attributes = []): Patient
    {
        return Patient::create(array_merge([
            'admin_id' => $doctor->id,
            'created_by' => $doctor->id,
            'first_name' => 'Paciente',
            'last_name' => 'De Prueba',
        ], $attributes));
    }
}
