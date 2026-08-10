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
            // Íconos en los extremos del header de los documentos (ej. ilustración
            // y sello del colegio médico), y el párrafo de credenciales
            // (especialidad, teléfonos, email, dirección) que va debajo del
            // nombre (brand_name) en el centro del header. Solo el Superadmin
            // los edita, igual que el resto del branding.
            $table->string('header_icon_left_path')->nullable()->after('logo_path');
            $table->string('header_icon_right_path')->nullable()->after('header_icon_left_path');
            $table->text('header_credentials')->nullable()->after('header_icon_right_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['header_icon_left_path', 'header_icon_right_path', 'header_credentials']);
        });
    }
};
