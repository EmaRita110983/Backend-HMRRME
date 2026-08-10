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
            // Segundo color por médico (además de brand_color), para detalles
            // como el indicador del ítem activo en el menú. Igual que
            // brand_color: solo lo edita el Superadmin.
            $table->string('brand_color_secondary', 7)->nullable()->after('brand_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('brand_color_secondary');
        });
    }
};
