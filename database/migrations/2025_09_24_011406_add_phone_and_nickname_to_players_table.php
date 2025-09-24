<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa as migrações.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('phone')->unique()->after('email');
            $table->string('nickname')->after('position');
        });
    }

    /**
     * Reverte as migrações.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['phone', 'nickname']);
        });
    }
};