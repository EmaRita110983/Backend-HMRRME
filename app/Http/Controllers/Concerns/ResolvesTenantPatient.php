<?php

namespace App\Http\Controllers\Concerns;

use App\Models\HistorialMedico;
use App\Models\Patient;
use Illuminate\Http\Request;

// Patrón repetido en los 6 controladores de documentos clínicos (Historial,
// Receta, Autorización, Licencia, Dieta, Estudio): resolver el paciente
// referenciado y verificar que pertenece al tenant del usuario autenticado
// antes de crear cualquier documento sobre él. Estaba copiado a mano en
// cada uno — la falta de un solo punto compartido fue justo lo que dejó a
// EstudioMedicoController sin el chequeo de historial_medico_id que
// RecetaController sí tenía (ver AUDITORIA.md). Extraerlo acá no evita que
// alguien olvide *llamarlo*, pero sí evita que la lógica en sí quede
// desincronizada entre controladores.
trait ResolvesTenantPatient
{
    /**
     * Devuelve el paciente si pertenece al tenant del usuario autenticado
     * (o si es superadmin); si no, corta la request con 403. El superadmin
     * no tiene tenant propio, así que puede crear documentos sobre
     * cualquier paciente.
     */
    protected function resolveTenantPatient(Request $request, int $patientId): Patient
    {
        $patient = Patient::findOrFail($patientId);
        $user = $request->user();

        if (!$user->isSuperAdmin() && $patient->admin_id !== $user->id) {
            abort(403, 'No tiene permiso sobre este paciente.');
        }

        return $patient;
    }

    /**
     * Corta la request con 422 si el historial_medico_id indicado no
     * pertenece al mismo paciente del documento que se está creando/editando
     * — sin esto, un id de historial de OTRO paciente quedaba enlazado tal
     * cual, dejando una referencia cruzada inconsistente en la base.
     */
    protected function assertHistorialBelongsToPatient(int $historialMedicoId, int $patientId): void
    {
        $historial = HistorialMedico::findOrFail($historialMedicoId);

        if ($historial->patient_id !== $patientId) {
            abort(422, 'El historial indicado no pertenece a este paciente.');
        }
    }
}
