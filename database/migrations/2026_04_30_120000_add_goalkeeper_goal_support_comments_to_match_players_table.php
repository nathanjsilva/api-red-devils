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
        DB::statement("
            ALTER TABLE match_players
            MODIFY goals INT UNSIGNED NULL COMMENT 'Goals scored by the player, including goalkeepers'
        ");

        DB::statement("
            ALTER TABLE match_players
            MODIFY assists INT UNSIGNED NULL COMMENT 'Assists made by the player, including goalkeepers'
        ");

        DB::statement("
            ALTER TABLE match_players
            MODIFY goals_conceded INT UNSIGNED NULL COMMENT 'Goals conceded, mainly for goalkeepers'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE match_players
            MODIFY goals INT UNSIGNED NULL
        ');

        DB::statement('
            ALTER TABLE match_players
            MODIFY assists INT UNSIGNED NULL
        ');

        DB::statement('
            ALTER TABLE match_players
            MODIFY goals_conceded INT UNSIGNED NULL
        ');
    }
};
