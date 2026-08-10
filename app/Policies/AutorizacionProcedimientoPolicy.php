<?php

namespace App\Policies;

use App\Models\AutorizacionProcedimiento;
use App\Models\User;
use App\Policies\Concerns\AllowsSuperAdminDevDelete;

class AutorizacionProcedimientoPolicy
{
    use AllowsSuperAdminDevDelete;

    protected function belongsToTenant(User $user, AutorizacionProcedimiento $autorizacion): bool
    {
        return $user->isSuperAdmin() || $autorizacion->admin_id === $user->id;
    }

    /**
     * Solo médico y superadmin acceden a las autorizaciones; la secretaria no.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function view(User $user, AutorizacionProcedimiento $autorizacion): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $autorizacion);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isDoctor();
    }

    public function update(User $user, AutorizacionProcedimiento $autorizacion): bool
    {
        return ($user->isSuperAdmin() || $user->isDoctor()) && $this->belongsToTenant($user, $autorizacion);
    }

    /**
     * Solo el médico dueño puede eliminar (siempre soft delete); ni la
     * secretaria ni el superadmin pueden borrar datos que el médico generó.
     */
    public function delete(User $user, AutorizacionProcedimiento $autorizacion): bool
    {
        if ($user->isSuperAdmin()) {
            return $this->superAdminDevDeleteEnabled();
        }

        return $user->isDoctor() && $autorizacion->admin_id === $user->id;
    }
}
