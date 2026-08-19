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
            // Subtítulo corto debajo del nombre del médico en el encabezado
            // de los documentos, ej. "Cirujano General", "Ginecólogo Obstetra".
            $table->string('professional_title')->nullable()->after('brand_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('professional_title');
        });
    }
};
