<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Superadmin puede borrar (solo desarrollo)
    |--------------------------------------------------------------------------
    |
    | Mientras el proyecto está en etapa de desarrollo, el superadmin puede
    | eliminar pacientes, historial médico, recetas, citas y usuarios —
    | algo que normalmente está prohibido (ver PatientPolicy, UserPolicy, etc.).
    | Debe quedar en false antes de pasar a producción.
    |
    */

    'superadmin_can_delete' => (bool) env('SUPERADMIN_CAN_DELETE', false),

];
