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
    // create_patients_table.php ya crea first_name/last_name (se agregaron
    // ahí después de escribir esta migración, sin quitar esta). Sin el
    // hasColumn(), correr las migraciones desde cero (BD nueva, CI, tests
    // con RefreshDatabase) fallaba con "duplicate column name" y nunca
    // llegaba a crear el resto de las tablas. No afecta a una base de datos
    // que ya tenía esta migración corrida: Laravel no vuelve a ejecutar
    // up() ahí, solo cambia el comportamiento en instalaciones nuevas.
    Schema::table('patients', function (Blueprint $table) {

        if (!Schema::hasColumn('patients', 'first_name')) {
            $table->string('first_name')
                ->nullable()
                ->after('created_by');
        }

        if (!Schema::hasColumn('patients', 'last_name')) {
            $table->string('last_name')
                ->nullable()
                ->after('first_name');
        }

    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('patients', function (Blueprint $table) {

        $table->dropColumn([
            'first_name',
            'last_name'
        ]);

    });
}
};
