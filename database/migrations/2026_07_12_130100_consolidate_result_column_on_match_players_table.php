<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill legado: preenche "result" a partir de "is_winner" nos poucos
        // registros antigos que ainda não tinham o campo novo preenchido.
        DB::table('match_players')->whereNull('result')->where('is_winner', true)->update(['result' => 'win']);
        DB::table('match_players')->whereNull('result')->where(function ($query) {
            $query->where('is_winner', false)->orWhereNull('is_winner');
        })->update(['result' => 'loss']);

        DB::statement("ALTER TABLE match_players MODIFY result ENUM('win','loss','draw') NOT NULL DEFAULT 'loss'");
        DB::statement('ALTER TABLE match_players DROP COLUMN is_winner');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE match_players ADD COLUMN is_winner TINYINT(1) NULL AFTER goals_conceded');

        DB::table('match_players')->where('result', 'win')->update(['is_winner' => 1]);
        DB::table('match_players')->whereIn('result', ['loss', 'draw'])->update(['is_winner' => 0]);

        DB::statement("ALTER TABLE match_players MODIFY result ENUM('win','loss','draw') NULL");
    }
};
