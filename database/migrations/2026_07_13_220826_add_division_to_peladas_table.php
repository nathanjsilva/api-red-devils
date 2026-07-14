<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('peladas', function (Blueprint $table) {
            $table->enum('division', ['quinta', 'sabado'])->default('quinta')->after('date');
        });

        // Backfill: peladas já cadastradas num sábado viram divisão "sabado";
        // as demais (hoje, todas numa quinta-feira) já ficam corretas pelo default.
        DB::table('peladas')->whereRaw('DAYOFWEEK(date) = 7')->update(['division' => 'sabado']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peladas', function (Blueprint $table) {
            $table->dropColumn('division');
        });
    }
};
