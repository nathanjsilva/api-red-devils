<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adiciona campo result como nullable primeiro
        DB::statement("ALTER TABLE match_players ADD COLUMN result ENUM('win', 'loss', 'draw') NULL AFTER is_winner");

        // Migra dados existentes de is_winner para result
        DB::statement("UPDATE match_players SET result = CASE WHEN is_winner = 1 THEN 'win' ELSE 'loss' END WHERE result IS NULL");

        // Torna o campo result obrigatório
        DB::statement("ALTER TABLE match_players MODIFY COLUMN result ENUM('win', 'loss', 'draw') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('result');
        });
    }
};
