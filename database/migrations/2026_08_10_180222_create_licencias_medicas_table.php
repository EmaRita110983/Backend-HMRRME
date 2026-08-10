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
        Schema::create('licencias_medicas', function (Blueprint $table) {
            $table->id();

            // Paciente al que pertenece esta licencia
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            // Médico dueño del tenant
            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Quién la registró (médico o superadmin)
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('fecha');
            $table->text('constatado');
            $table->text('recomendacion');

            // Línea de cierre ("Expido la presente certificación en ... a
            // partir de hoy día ..."), la escribe el médico libremente en
            // cada documento (ciudad y fecha en palabras incluidas).
            $table->text('certificacion_cierre');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licencias_medicas');
    }
};
