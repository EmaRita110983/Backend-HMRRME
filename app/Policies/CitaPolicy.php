<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;

class CitaPolicy
{
    use AllowsSuperAdminDevDelete;

    protected function belongsToTenant(User $user, Cita $cita): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenantId = $user->isDoctor() ? $user->id : $user->admin_id;

        return $cita->admin_id === $tenantId;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor() || $user->isSecretary();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cita $cita): bool
    {
        return $this->belongsToTenant($user, $cita);
    }

    /**
     * Determine whether the user can create models.
     * Médico y secretaria agendan citas de su propio tenant; superadmin puede
     * agendar en nombre de cualquier médico (el paciente ya define el tenant).
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor() || $user->isSecretary();
    }

    /**
     * Determine whether the user can update the model.
     * Médico, secretaria y superadmin pueden editar (reprogramar, marcar
     * completada/cancelada), siempre que la cita pertenezca a su tenant.
     */
    public function update(User $user, Cita $cita): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor() || $user->isSecretary()) && $this->belongsToTenant($user, $cita);
    }

    /**
     * Determine whether the user can delete the model.
     * Solo el médico (dueño) puede eliminar citas; la secretaria nunca borra.
     * El superadmin solo puede mientras el modo desarrollo esté activo.
     */
    public function delete(User $user, Cita $cita): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $this->belongsToTenant($user, $cita);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Cita $cita): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Cita $cita): bool
    {
        return false;
    }
}
