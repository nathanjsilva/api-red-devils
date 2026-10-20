<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('championships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['draft', 'active', 'playoffs', 'finished'])->default('draft');
            $table->enum('phase', ['setup', 'group', 'playoffs', 'finished'])->default('setup');
            $table->boolean('goalkeeper_rule_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('championship_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('championship_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('crest_path')->nullable();
            $table->timestamps();
            $table->unique(['championship_id', 'name']);
        });

        Schema::create('championship_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('championship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->enum('player_type', ['field', 'goalkeeper']);
            $table->unsignedTinyInteger('stone_group');
            $table->decimal('stone_level', 2, 1)->nullable();
            $table->string('sport_position')->nullable();
            $table->timestamps();
            $table->unique(['championship_id', 'player_id']);
            $table->unique(['championship_id', 'stone_level']);
        });

        Schema::create('championship_team_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('championship_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('championship_player_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique('championship_player_id');
            $table->unique(['championship_team_id', 'championship_player_id'], 'ct_team_player_unique');
        });

        Schema::create('championship_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('championship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_id')->constrained('championship_teams')->restrictOnDelete();
            $table->foreignId('away_team_id')->constrained('championship_teams')->restrictOnDelete();
            $table->enum('phase', ['group', 'semi_final', 'challenger_final', 'final']);
            $table->unsignedTinyInteger('round')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'finished', 'cancelled'])->default('scheduled');
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->unsignedSmallInteger('home_penalty_score')->nullable();
            $table->unsignedSmallInteger('away_penalty_score')->nullable();
            $table->foreignId('qualified_team_id')->nullable()->constrained('championship_teams')->nullOnDelete();
            $table->timestamps();
            $table->unique(['championship_id', 'home_team_id', 'away_team_id', 'phase'], 'championship_match_fixture_unique');
        });

        Schema::create('championship_match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('championship_matches')->cascadeOnDelete();
            $table->foreignId('scorer_id')->nullable()->constrained('championship_players')->nullOnDelete();
            $table->foreignId('assist_id')->nullable()->constrained('championship_players')->nullOnDelete();
            $table->foreignId('team_id')->constrained('championship_teams')->restrictOnDelete();
            $table->boolean('own_goal')->default(false);
            $table->unsignedSmallInteger('minute')->nullable();
            $table->timestamps();
        });

        Schema::create('championship_match_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('championship_matches')->cascadeOnDelete();
            $table->foreignId('championship_player_id')->constrained()->cascadeOnDelete();
            $table->boolean('mvp')->default(false);
            $table->timestamps();
            $table->unique(['match_id', 'championship_player_id'], 'match_participant_unique');
        });

        Schema::create('championship_odds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('championship_matches')->cascadeOnDelete();
            $table->decimal('home_probability', 6, 4);
            $table->decimal('draw_probability', 6, 4);
            $table->decimal('away_probability', 6, 4);
            $table->decimal('home_odd', 8, 3);
            $table->decimal('draw_odd', 8, 3);
            $table->decimal('away_odd', 8, 3);
            $table->json('parameters');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('championship_odds');
        Schema::dropIfExists('championship_match_participations');
        Schema::dropIfExists('championship_match_events');
        Schema::dropIfExists('championship_matches');
        Schema::dropIfExists('championship_team_players');
        Schema::dropIfExists('championship_players');
        Schema::dropIfExists('championship_teams');
        Schema::dropIfExists('championships');
    }
};
