<?php

namespace App\Services;

use App\Models\Championship;
use App\Models\ChampionshipMatch;
use Illuminate\Support\Facades\DB;

class ChampionshipFixtureService
{
    public function generate(Championship $championship, ChampionshipValidationService $validator): void
    {
        DB::transaction(function () use ($championship, $validator) {
            $teams = $championship->teams()->orderBy('id')->get();
            abort_if($teams->count() !== 5, 422, 'O campeonato precisa ter exatamente cinco equipes.');
            if ($championship->matches()->where('phase', 'group')->exists()) {
                return;
            }
            $isDraft = $championship->status === 'draft' && $championship->phase === 'setup';
            $isActive = $championship->status === 'active' && $championship->phase === 'group';
            abort_if(! $isDraft && ! $isActive, 422, 'Os confrontos só podem ser gerados durante a configuração ou a fase classificatória.');
            if ($isDraft) {
                $validator->validateReady($championship);
            }
            $rotation = $teams->pluck('id')->push(null)->values()->all();
            for ($round = 1; $round <= 5; $round++) {
                for ($i = 0; $i < 3; $i++) {
                    $home = $rotation[$i];
                    $away = $rotation[count($rotation) - 1 - $i];
                    if ($home && $away) {
                        ChampionshipMatch::create(['championship_id' => $championship->id, 'home_team_id' => $home, 'away_team_id' => $away, 'phase' => 'group', 'round' => $round]);
                    }
                }
                $fixed = array_shift($rotation);
                $last = array_pop($rotation);
                array_unshift($rotation, $fixed);
                array_splice($rotation, 1, 0, [$last]);
            }
            if ($isActive) {
                $championship->update(['status' => 'active', 'phase' => 'group']);
            }
        });
    }
}
