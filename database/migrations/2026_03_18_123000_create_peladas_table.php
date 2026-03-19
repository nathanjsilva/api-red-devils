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
        Schema::create('peladas', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('location');
            $table->unsignedInteger('qtd_times');
            $table->unsignedInteger('qtd_jogadores_por_time');
            $table->unsignedInteger('qtd_goleiros');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peladas');
    }
};
