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
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('pelada_id')->constrained()->onDelete('cascade');

            $table->unsignedInteger('goals')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('goals_conceded')->nullable(); // só para goleiros
            $table->boolean('is_winner')->default(false);

            $table->timestamps();
        });
    }


    /**
     * Reverte as migrações.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
