<?php

namespace App\Policies;

use App\Models\LicenciaMedica;
use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;

class LicenciaMedicaPolicy
{
    use AllowsSuperAdminDevDelete;

    protected function belongsToTenant(User $user, LicenciaMedica $licencia): bool
    {
        return $user->isSuperAdmin() || $licencia->admin_id === $user->id;
    }

    /**
     * Solo médico y superadmin acceden a las licencias; la secretaria no.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function view(User $user, LicenciaMedica $licencia): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $licencia);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function update(User $user, LicenciaMedica $licencia): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $licencia);
    }

    /**
     * Solo el médico dueño puede eliminar (siempre soft delete); ni la
     * secretaria ni el superadmin pueden borrar datos que el médico generó.
     */
    public function delete(User $user, LicenciaMedica $licencia): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $licencia->admin_id === $user->id;
    }
}
