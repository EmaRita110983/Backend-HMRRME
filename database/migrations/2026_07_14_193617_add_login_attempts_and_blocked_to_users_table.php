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

        $table->unsignedTinyInteger('login_attempts')->default(0);

        $table->boolean('blocked')->default(false);

    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {

        $table->dropColumn('login_attempts');

        $table->dropColumn('blocked');

    });
}
};