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
        Schema::table('match_players', function (Blueprint $table) {
            // Altera campos de unsignedTinyInteger para unsignedInteger
            // para remover o limite de 255
            $table->unsignedInteger('goals')->default(0)->change();
            $table->unsignedInteger('assists')->default(0)->change();
            $table->unsignedInteger('goals_conceded')->nullable()->change();
        });
    }

    /**
     * Reverte as migrações.
     */
    public function down(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            // Reverte para unsignedTinyInteger
            $table->unsignedTinyInteger('goals')->default(0)->change();
            $table->unsignedTinyInteger('assists')->default(0)->change();
            $table->unsignedTinyInteger('goals_conceded')->nullable()->change();
        });
    }
};
