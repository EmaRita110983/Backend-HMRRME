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
        Schema::create('patients', function (Blueprint $table) {

            $table->id();

            // Doctor dueño del paciente
            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Usuario que registró el paciente
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();


            // Datos personales
            $table->string('first_name');

            $table->string('last_name');

            $table->string('cedula')
                ->nullable();

            $table->date('birth_date')
                ->nullable();


            // Contacto
            $table->string('phone')
                ->nullable();

            $table->string('email')
                ->nullable();

            $table->text('address')
                ->nullable();


            // Seguro médico
            $table->string('insurance')
                ->nullable();


            // Familiar responsable
            $table->string('emergency_contact')
                ->nullable();

            $table->string('emergency_phone')
                ->nullable();


            // Enfermedades actuales
            $table->text('medical_conditions')
                ->nullable();


            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
