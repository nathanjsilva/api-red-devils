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
        Schema::create('goal_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelada_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->foreignId('goleiro_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pelada_id', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_positions');
    }
};
