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
        Schema::create('autorizacion_procedimientos', function (Blueprint $table) {
            $table->id();

            // Paciente al que pertenece esta autorización
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            // Médico dueño del tenant (igual que en historial_medico.admin_id)
            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Quién la registró (médico o superadmin)
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('fecha');
            $table->text('historia_enfermedad');
            $table->text('estudios_realizados')->nullable();
            $table->string('tiempo_evolucion')->nullable();
            $table->text('tratamiento_previo')->nullable();
            $table->text('diagnostico_presuntivo');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('autorizacion_procedimientos');
    }
};
