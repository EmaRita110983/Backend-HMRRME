<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historial_medico', function (Blueprint $table) {
            $table->id();

            // Paciente al que pertenece esta entrada de historial
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            // Médico dueño del tenant (igual que en patients.admin_id)
            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Quién registró la entrada (médico o superadmin)
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('fecha_consulta');
            $table->text('motivo_consulta');
            $table->text('diagnostico');
            $table->text('tratamiento')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_medico');
    }
};
