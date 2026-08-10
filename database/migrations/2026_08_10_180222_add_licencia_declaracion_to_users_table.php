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
        Schema::table('users', function (Blueprint $table) {
            // Párrafo fijo del médico para la Licencia Médica ("Yo: [nombre]
            // Médico provisto del correspondiente exequátur No. [X] y Cédula
            // de identidad y electoral No. [Y] CERTIFICO, haber examinado
            // a:"). La app le agrega el nombre/cédula del paciente y ", TITULAR."
            $table->text('licencia_declaracion')->nullable()->after('header_credentials');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('licencia_declaracion');
        });
    }
};
