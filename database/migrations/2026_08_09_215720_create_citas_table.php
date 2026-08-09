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
        Schema::create('citas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            // Médico dueño del tenant
            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Quién agendó la cita (médico, secretaria o superadmin)
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('fecha');
            $table->time('hora');
            $table->string('motivo')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente | completada | cancelada

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
