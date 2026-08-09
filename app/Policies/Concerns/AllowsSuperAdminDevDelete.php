<?php

namespace App\Policies\Concerns;

trait AllowsSuperAdminDevDelete
{
    /**
     * Mientras estemos en etapa de desarrollo (config/dev.php), el superadmin
     * puede borrar. Debe quedar deshabilitado antes de producción.
     */
    protected function superAdminDevDeleteEnabled(): bool
    {
        return (bool) config('dev.superadmin_can_delete');
    }
}
