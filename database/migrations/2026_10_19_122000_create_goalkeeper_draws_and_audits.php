<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('championship_goalkeeper_draws', function (Blueprint $table) {
            $table->id(); $table->foreignId('championship_id')->constrained()->cascadeOnDelete(); $table->foreignId('team_id')->constrained('championship_teams')->cascadeOnDelete(); $table->foreignId('championship_player_id')->constrained()->restrictOnDelete(); $table->unsignedInteger('draw_number'); $table->foreignId('drawn_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->index(['championship_id','draw_number'], 'goalkeeper_draw_history_idx');
        });
        Schema::create('championship_audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('championship_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('action'); $table->nullableMorphs('auditable'); $table->json('payload')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('championship_audit_logs'); Schema::dropIfExists('championship_goalkeeper_draws'); }
};
