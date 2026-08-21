<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ninguna de estas 8 tablas tenía índice más allá de la FK (que en SQLite,
// a diferencia de MySQL/InnoDB, no crea automáticamente un índice
// secundario). Cada controlador filtra sistemáticamente por admin_id
// (aislamiento por tenant) y a veces por patient_id; sin índice, cada una
// de esas queries hace un table scan completo. Con pocos registros no se
// nota — con cientos de médicos y miles de pacientes/citas, sí.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('cedula');
            $table->index('pasaporte');
        });

        Schema::table('citas', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('patient_id');
        });

        Schema::table('historial_medico', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('patient_id');
        });

        Schema::table('recetas', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('patient_id');
        });

        Schema::table('autorizacion_procedimientos', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('patient_id');
        });

        Schema::table('licencias_medicas', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('patient_id');
        });

        Schema::table('dietas', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('patient_id');
        });

        Schema::table('estudios_medicos', function (Blueprint $table) {
            $table->index('admin_id');
            $table->index('patient_id');
        });

        // users.cedula ya es unique() (cuenta como índice); admin_id (relación
        // secretaria -> médico dueño, ver User::secretaries()) no tenía ninguno.
        Schema::table('users', function (Blueprint $table) {
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['cedula']);
            $table->dropIndex(['pasaporte']);
        });

        Schema::table('citas', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['patient_id']);
        });

        Schema::table('historial_medico', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['patient_id']);
        });

        Schema::table('recetas', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['patient_id']);
        });

        Schema::table('autorizacion_procedimientos', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['patient_id']);
        });

        Schema::table('licencias_medicas', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['patient_id']);
        });

        Schema::table('dietas', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['patient_id']);
        });

        Schema::table('estudios_medicos', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['patient_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['admin_id']);
        });
    }
};
