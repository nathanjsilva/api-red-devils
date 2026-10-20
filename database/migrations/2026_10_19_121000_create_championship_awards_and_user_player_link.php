<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->unsignedBigInteger('player_id')->nullable()->after('id'); $table->foreign('player_id', 'users_player_fk')->references('id')->on('players')->nullOnDelete(); });
        Schema::create('championship_award_categories', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('championship_id'); $table->foreign('championship_id', 'award_category_championship_fk')->references('id')->on('championships')->cascadeOnDelete(); $table->string('name'); $table->text('description'); $table->enum('type', ['vote','statistic']); $table->boolean('goalkeepers_only')->default(false); $table->boolean('published')->default(false); $table->timestamps(); $table->unique(['championship_id','name'], 'championship_award_name_unique');
        });
        Schema::create('championship_votes', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('championship_award_category_id'); $table->unsignedBigInteger('voter_id'); $table->unsignedBigInteger('candidate_id'); $table->foreign('championship_award_category_id', 'award_vote_category_fk')->references('id')->on('championship_award_categories')->cascadeOnDelete(); $table->foreign('voter_id', 'award_vote_voter_fk')->references('id')->on('users')->cascadeOnDelete(); $table->foreign('candidate_id', 'award_vote_candidate_fk')->references('id')->on('championship_players')->cascadeOnDelete(); $table->timestamps(); $table->unique(['championship_award_category_id','voter_id'], 'award_voter_unique');
        });
        Schema::create('championship_award_results', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('championship_award_category_id'); $table->unsignedBigInteger('winner_id')->nullable(); $table->foreign('championship_award_category_id', 'award_result_category_fk')->references('id')->on('championship_award_categories')->cascadeOnDelete(); $table->foreign('winner_id', 'award_result_winner_fk')->references('id')->on('championship_players')->nullOnDelete(); $table->unsignedInteger('votes')->default(0); $table->decimal('percentage', 6, 2)->default(0); $table->timestamps(); $table->unique(['championship_award_category_id','winner_id'], 'award_result_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('championship_award_results'); Schema::dropIfExists('championship_votes'); Schema::dropIfExists('championship_award_categories'); Schema::table('users', function (Blueprint $table) { $table->dropForeign('users_player_fk'); $table->dropColumn('player_id'); });
    }
};
