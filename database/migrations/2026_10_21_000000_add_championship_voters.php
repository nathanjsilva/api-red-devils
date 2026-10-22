<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('championship_voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('championship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('championship_player_id')->constrained()->cascadeOnDelete();
            $table->string('pin_hash');
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->char('session_token_hash', 64)->nullable()->unique();
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['championship_id', 'championship_player_id'], 'championship_voter_player_unique');
        });

        Schema::table('championship_votes', function (Blueprint $table) {
            $table->unsignedBigInteger('championship_voter_id')->nullable()->after('voter_id');
            $table->unsignedBigInteger('voter_championship_player_id')->nullable()->after('championship_voter_id');
            $table->foreign('championship_voter_id', 'award_vote_championship_voter_fk')
                ->references('id')->on('championship_voters')->cascadeOnDelete();
            $table->foreign('voter_championship_player_id', 'award_vote_participant_fk')
                ->references('id')->on('championship_players')->cascadeOnDelete();
            $table->unique(['championship_award_category_id', 'championship_voter_id'], 'award_championship_voter_unique');
            $table->unique(['championship_award_category_id', 'voter_championship_player_id'], 'award_voter_participant_unique');
        });

        DB::statement('ALTER TABLE championship_votes MODIFY voter_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (DB::table('championship_votes')->whereNull('voter_id')->exists()) {
            throw new \RuntimeException('Não é possível reverter o cadastro de votantes enquanto existirem votos sem usuário legado.');
        }

        Schema::table('championship_votes', function (Blueprint $table) {
            $table->dropUnique('award_championship_voter_unique');
            $table->dropUnique('award_voter_participant_unique');
            $table->dropForeign('award_vote_championship_voter_fk');
            $table->dropForeign('award_vote_participant_fk');
            $table->dropColumn(['championship_voter_id', 'voter_championship_player_id']);
        });

        DB::statement('ALTER TABLE championship_votes MODIFY voter_id BIGINT UNSIGNED NOT NULL');
        Schema::dropIfExists('championship_voters');
    }
};
