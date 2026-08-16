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
        Schema::create('estudios_medicos', function (Blueprint $table) {
            $table->id();

            // Paciente al que pertenece este estudio
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            // Consulta de la que se desprende (opcional): a diferencia de
            // Dieta, un estudio sí suele nacer de una consulta puntual.
            $table->foreignId('historial_medico_id')
                ->nullable()
                ->constrained('historial_medico')
                ->nullOnDelete();

            // Médico dueño del tenant
            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Quién lo subió (médico o superadmin)
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            // sonografia | rayos_x | tomografia | resonancia | laboratorio | otro
            // Como string (no enum de BD) para poder agregar tipos nuevos sin migración.
            $table->string('tipo');

            $table->date('fecha_estudio');
            $table->text('descripcion')->nullable();

            $table->string('archivo_path');
            $table->string('archivo_nombre_original');
            $table->string('archivo_mime');
            $table->unsignedInteger('archivo_tamano');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estudios_medicos');
    }
};
