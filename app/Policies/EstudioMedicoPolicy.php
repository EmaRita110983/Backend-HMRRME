<?php

namespace App\Policies;

use App\Models\EstudioMedico;
use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;

class EstudioMedicoPolicy
{
    use AllowsSuperAdminDevDelete;

    protected function belongsToTenant(User $user, EstudioMedico $estudio): bool
    {
        return $user->isSuperAdmin() || $estudio->admin_id === $user->id;
    }

    /**
     * Solo médico y superadmin acceden a los estudios médicos; la secretaria no.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function view(User $user, EstudioMedico $estudio): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $estudio);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function update(User $user, EstudioMedico $estudio): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $estudio);
    }

    /**
     * Solo el médico dueño puede eliminar (siempre soft delete); ni la
     * secretaria ni el superadmin pueden borrar datos que el médico generó.
     */
    public function delete(User $user, EstudioMedico $estudio): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $estudio->admin_id === $user->id;
    }
}
