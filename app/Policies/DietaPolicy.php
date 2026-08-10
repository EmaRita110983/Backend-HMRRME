<?php

namespace App\Policies;

use App\Models\Dieta;
use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;

class DietaPolicy
{
    use AllowsSuperAdminDevDelete;

    protected function belongsToTenant(User $user, Dieta $dieta): bool
    {
        return $user->isSuperAdmin() || $dieta->admin_id === $user->id;
    }

    /**
     * Solo médico y superadmin acceden a los planes de dieta; la secretaria no.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function view(User $user, Dieta $dieta): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $dieta);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function update(User $user, Dieta $dieta): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $dieta);
    }

    /**
     * Solo el médico dueño puede eliminar (siempre soft delete); ni la
     * secretaria ni el superadmin pueden borrar datos que el médico generó.
     */
    public function delete(User $user, Dieta $dieta): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $dieta->admin_id === $user->id;
    }
}
