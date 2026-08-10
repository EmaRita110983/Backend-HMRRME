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
        Schema::table('dietas', function (Blueprint $table) {
            // La dieta no se relaciona con una consulta puntual: la diagnóstico
            // de esa consulta no pertenece a este documento.
            $table->dropConstrainedForeignId('historial_medico_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dietas', function (Blueprint $table) {
            $table->foreignId('historial_medico_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('historial_medico')
                ->nullOnDelete();
        });
    }
};
