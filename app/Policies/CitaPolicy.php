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
     * Médico y superadmin pueden editar cualquier campo, incluido el estado
     * (marcar completada/cancelada). La secretaria también puede entrar acá
     * ahora, para corregir errores de carga (hora/motivo) — pero
     * CitaController::update() le ignora "estado" si lo manda: marcar una
     * cita como atendida es una decisión clínica, no un dato de agenda, y
     * sigue siendo solo del médico/superadmin aunque ambos compartan este
     * mismo authorize().
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
