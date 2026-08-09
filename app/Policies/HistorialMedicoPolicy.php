<?php

namespace App\Policies;

use App\Models\HistorialMedico;
use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;

class HistorialMedicoPolicy
{
    use AllowsSuperAdminDevDelete;

    protected function belongsToTenant(User $user, HistorialMedico $historial): bool
    {
        return $user->isSuperAdmin() || $historial->admin_id === $user->id;
    }

    /**
     * Solo médico y superadmin acceden al historial médico; la secretaria no.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function view(User $user, HistorialMedico $historial): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $historial);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function update(User $user, HistorialMedico $historial): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $historial);
    }

    /**
     * Solo el médico dueño puede eliminar (siempre soft delete); ni la
     * secretaria ni el superadmin pueden borrar datos que el médico generó.
     */
    public function delete(User $user, HistorialMedico $historial): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $historial->admin_id === $user->id;
    }
}
