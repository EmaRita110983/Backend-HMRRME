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
        Schema::table('autorizacion_procedimientos', function (Blueprint $table) {
            // Editable por documento (no fijo): el paciente puede tener una
            // ARS distinta a la registrada en su ficha, o cambiarla con el tiempo.
            $table->string('ars')->nullable()->after('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('autorizacion_procedimientos', function (Blueprint $table) {
            $table->dropColumn('ars');
        });
    }
};
