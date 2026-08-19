<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;
use Illuminate\Auth\Access\Response;

class PatientPolicy
{
    use AllowsSuperAdminDevDelete;

    /**
     * El tenant (médico dueño de los datos) del usuario autenticado:
     * el propio id si es médico, o el admin_id de su médico si es secretaria.
     */
    protected function tenantIdFor(User $user): ?int
    {
        return $user->isDoctor() ? $user->id : $user->admin_id;
    }

    protected function belongsToTenant(User $user, Patient $patient): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $patient->admin_id === $this->tenantIdFor($user);
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
    public function view(User $user, Patient $patient): bool
    {
        return $this->belongsToTenant($user, $patient);
    }

    /**
     * Determine whether the user can create models.
     * Médico y secretaria crean pacientes de su propio tenant; superadmin puede
     * crear en nombre de cualquier médico (indicando admin_id en la petición).
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor() || $user->isSecretary();
    }

    /**
     * Determine whether the user can update the model.
     * Médico y superadmin pueden editar, siempre que el paciente pertenezca a
     * su tenant (superadmin ve/edita todos los tenants). La secretaria puede
     * crear pacientes (create(), para el flujo de Nueva cita) pero no
     * editarlos: no tiene acceso a la pantalla de Pacientes.
     */
    public function update(User $user, Patient $patient): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $patient);
    }

    /**
     * Determine whether the user can delete the model.
     * Solo el médico (dueño) puede eliminar pacientes. Ni la secretaria ni el
     * superadmin pueden borrar: son datos que médico/secretaria generaron y
     * deben permanecer siempre disponibles, incluso para el superadmin (solo lectura/edición).
     * El controlador se encarga de que esto sea siempre un soft delete.
     */
    public function delete(User $user, Patient $patient): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $this->belongsToTenant($user, $patient);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Patient $patient): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        return false;
    }
}
