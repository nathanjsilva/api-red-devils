<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('championships', function(Blueprint $table){$table->json('standings_snapshot')->nullable()->after('phase');});
        Schema::create('championship_tie_breakers', function(Blueprint $table){$table->id();$table->foreignId('championship_id')->constrained()->cascadeOnDelete();$table->foreignId('team_a_id')->constrained('championship_teams')->cascadeOnDelete();$table->foreignId('team_b_id')->constrained('championship_teams')->cascadeOnDelete();$table->foreignId('winner_id')->constrained('championship_teams')->cascadeOnDelete();$table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();$table->string('reason')->default('administrative_draw');$table->timestamps();$table->unique(['championship_id','team_a_id','team_b_id'],'championship_tie_pair_unique');});
    }
    public function down(): void {Schema::dropIfExists('championship_tie_breakers');Schema::table('championships',function(Blueprint $table){$table->dropColumn('standings_snapshot');});}
};
