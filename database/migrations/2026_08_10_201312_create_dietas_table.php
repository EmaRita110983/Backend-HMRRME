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
        Schema::create('dietas', function (Blueprint $table) {
            $table->id();

            // Paciente al que pertenece este plan de dieta
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            // Entrada de historial médico de la que se desprende (opcional)
            $table->foreignId('historial_medico_id')
                ->nullable()
                ->constrained('historial_medico')
                ->nullOnDelete();

            // Médico dueño del tenant
            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Quién la registró (médico o superadmin)
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('fecha');
            $table->text('dieta');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dietas');
    }
};
