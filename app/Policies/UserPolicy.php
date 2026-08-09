<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use AllowsSuperAdminDevDelete;

    /**
     * Un admin (médico) solo gestiona a las secretarias que él mismo creó.
     */
    protected function owns(User $user, User $model): bool
    {
        return $model->created_by === $user->id;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || ($user->isDoctor() && $this->owns($user, $model));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || ($user->isDoctor() && $this->owns($user, $model));
    }

    /**
     * Determine whether the user can delete the model.
     * Siempre soft delete: la secretaria eliminada debe seguir apareciendo
     * como autora/editora de los pacientes que gestionó. El superadmin puede
     * hacer todo lo demás con médicos y secretarias, pero no borrar sus cuentas:
     * esos registros les pertenecen a ellos, no al superadmin.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $this->owns($user, $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
