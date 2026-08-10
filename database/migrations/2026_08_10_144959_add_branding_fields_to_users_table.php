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
            // Solo se usan cuando role = admin (el médico dueño del tenant).
            $table->string('brand_name')->nullable()->after('role');
            $table->string('brand_color', 7)->nullable()->after('brand_name');
            $table->string('logo_path')->nullable()->after('brand_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'brand_color', 'logo_path']);
        });
    }
};
