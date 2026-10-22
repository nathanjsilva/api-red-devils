<?php

namespace App\Services;

use App\Models\Championship;
use App\Models\ChampionshipMatch;
use Illuminate\Support\Facades\DB;

class ChampionshipPlayoffService
{
    public function generate(Championship $championship): void
    {
        DB::transaction(function () use ($championship) {
            if ($championship->matches()->where('phase', 'semi_final')->exists()) {
                return;
            }

            abort_if($championship->status !== 'active' || $championship->phase !== 'group', 422, 'O campeonato precisa estar na fase de grupos para gerar os playoffs.');
            $standings = app(ChampionshipStandingsService::class)->calculate($championship);
            $groupMatches = $championship->matches()->where('phase', 'group');
            abort_if(count($standings) !== 5 || $groupMatches->count() !== 10 || $groupMatches->where('status', '!=', 'finished')->exists(), 422, 'Conclua os dez jogos da primeira fase.');
            $championship->update(['standings_snapshot' => $standings]);
            $team = fn ($position) => $standings[$position - 1]['team_id'];
            ChampionshipMatch::create(['championship_id' => $championship->id, 'home_team_id' => $team(2), 'away_team_id' => $team(5), 'phase' => 'semi_final']);
            ChampionshipMatch::create(['championship_id' => $championship->id, 'home_team_id' => $team(3), 'away_team_id' => $team(4), 'phase' => 'semi_final']);
            $championship->update(['status' => 'playoffs', 'phase' => 'playoffs']);
        });
    }
}
