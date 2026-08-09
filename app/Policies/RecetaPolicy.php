<?php

namespace App\Policies;

use App\Models\Receta;
use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;

class RecetaPolicy
{
    use AllowsSuperAdminDevDelete;

    protected function belongsToTenant(User $user, Receta $receta): bool
    {
        return $user->isSuperAdmin() || $receta->admin_id === $user->id;
    }

    /**
     * Solo médico y superadmin acceden a las recetas; la secretaria no.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function view(User $user, Receta $receta): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $receta);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function update(User $user, Receta $receta): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $receta);
    }

    /**
     * Solo el médico dueño puede eliminar (siempre soft delete); ni la
     * secretaria ni el superadmin pueden borrar datos que el médico generó.
     */
    public function delete(User $user, Receta $receta): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $receta->admin_id === $user->id;
    }
}
