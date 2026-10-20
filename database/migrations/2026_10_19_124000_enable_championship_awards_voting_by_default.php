<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('championship_award_categories')
            ->where('type', 'vote')
            ->where('published', false)
            ->update(['voting_open' => true]);
    }

    public function down(): void
    {
        // As categorias existentes não devem ser reabertas ou encerradas automaticamente em rollback.
    }
};
