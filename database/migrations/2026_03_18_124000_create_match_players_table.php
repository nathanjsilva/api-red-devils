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
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pelada_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('goals')->nullable();
            $table->unsignedInteger('assists')->nullable();
            $table->unsignedInteger('goals_conceded')->nullable();
            $table->boolean('is_winner')->nullable();
            $table->enum('result', ['win', 'loss', 'draw'])->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'pelada_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
