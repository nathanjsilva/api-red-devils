<?php
namespace App\Services;
use App\Models\Championship;
class ChampionshipValidationService
{
    public function validateReady(Championship $championship): void
    {
        abort_if($championship->players()->where('player_type','field')->count() !== 25, 422, 'O campeonato precisa ter 25 jogadores de linha.');
        abort_if($championship->players()->where('player_type','goalkeeper')->count() !== 5, 422, 'O campeonato precisa ter cinco goleiros.');
        foreach ($championship->teams()->with('players')->get() as $team) {
            abort_if($team->players->where('player_type','field')->count() !== 5, 422, "A equipe {$team->name} precisa de cinco jogadores de linha.");
            abort_if($team->players->where('player_type','field')->pluck('stone_group')->unique()->count() !== 5, 422, "A equipe {$team->name} precisa de uma Pedra de cada grupo.");
            abort_if($team->players->where('player_type','goalkeeper')->count() !== 1, 422, "A equipe {$team->name} precisa de um goleiro.");
        }
    }
}
