<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use AllowsSuperAdminDevDelete;

    /**
     * Un admin (médico) solo gestiona a sus propias secretarias. Se usa
     * admin_id (no created_by): una secretaria puede haber sido creada por
     * el superadmin en nombre del médico, y en ese caso created_by queda con
     * el id del superadmin, no del médico dueño real del tenant.
     */
    protected function owns(User $user, User $model): bool
    {
        return $model->admin_id === $user->id;
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
     * Mismo criterio que delete(): un médico reactiva a sus propias
     * secretarias; el superadmin no gestiona el ciclo de vida de cuentas que
     * no le pertenecen, salvo con el flag de desarrollo.
     */
    public function restore(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $this->owns($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
