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
        Schema::table('peladas', function (Blueprint $table) {
            $table->string('location')->after('date');
            $table->integer('qtd_times')->after('location');
            $table->integer('qtd_jogadores_por_time')->after('qtd_times');
            $table->integer('qtd_goleiros')->after('qtd_jogadores_por_time');
        });
    }

    /**
     * Reverte as migrações.
     */
    public function down(): void
    {
        Schema::table('peladas', function (Blueprint $table) {
            $table->dropColumn(['location', 'qtd_times', 'qtd_jogadores_por_time', 'qtd_goleiros']);
        });
    }
};