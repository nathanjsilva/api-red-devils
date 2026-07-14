<?php

namespace App\Services;

use App\Exceptions\InsufficientGoalkeepersException;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamOrganizerService
{
    /**
     * Organiza os times a partir de uma distribuição explícita de jogadores por time.
     *
     * @param  array<int, array{team_number: int, player_ids: array<int, int>}>  $teamAssignments
     */
    public function organizeManual(Pelada $pelada, array $teamAssignments): Collection
    {
        return DB::transaction(function () use ($pelada, $teamAssignments) {
            $this->clearExistingTeams($pelada);

            return collect($teamAssignments)->map(function (array $assignment) use ($pelada) {
                $team = Team::create([
                    'pelada_id' => $pelada->id,
                    'name' => "Time {$assignment['team_number']}",
                ]);

                $team->players()->attach($assignment['player_ids']);

                return $team->load('players');
            });
        });
    }

    /**
     * Organiza os times automaticamente, distribuindo goleiros e jogadores de linha
     * de forma equilibrada entre os times conforme a configuração da pelada.
     *
     * @param  array<int, int>  $playerIds
     *
     * @throws InsufficientGoalkeepersException
     */
    public function organizeAutomatic(Pelada $pelada, array $playerIds): Collection
    {
        $players = Player::whereIn('id', $playerIds)->get();
        $goalkeepers = $players->where('position', 'goleiro')->values();
        $fieldPlayers = $players->where('position', 'linha')->values();

        if ($goalkeepers->count() < $pelada->qtd_goleiros) {
            throw new InsufficientGoalkeepersException;
        }

        return DB::transaction(function () use ($pelada, $goalkeepers, $fieldPlayers) {
            $this->clearExistingTeams($pelada);

            $teams = collect();
            for ($i = 1; $i <= $pelada->qtd_times; $i++) {
                $teams->push(Team::create([
                    'pelada_id' => $pelada->id,
                    'name' => "Time {$i}",
                ]));
            }

            // Distribui os goleiros proporcionalmente à quantidade configurada na
            // pelada (qtd_goleiros / qtd_times), em vez de um número fixo por time.
            $goalkeepersPerTeam = (int) ceil($pelada->qtd_goleiros / $pelada->qtd_times);
            $goalkeeperIndex = 0;
            foreach ($teams as $team) {
                $needed = min($goalkeepersPerTeam, $goalkeepers->count() - $goalkeeperIndex);
                for ($g = 0; $g < $needed && $goalkeeperIndex < $goalkeepers->count(); $g++) {
                    $team->players()->attach($goalkeepers[$goalkeeperIndex]->id);
                    $goalkeeperIndex++;
                }
            }

            $playersPerTeam = (int) floor($fieldPlayers->count() / $pelada->qtd_times);
            $fieldPlayerIndex = 0;
            foreach ($teams as $team) {
                for ($p = 0; $p < $playersPerTeam && $fieldPlayerIndex < $fieldPlayers->count(); $p++) {
                    $team->players()->attach($fieldPlayers[$fieldPlayerIndex]->id);
                    $fieldPlayerIndex++;
                }
            }

            return $teams->map(fn (Team $team) => $team->load('players'));
        });
    }

    private function clearExistingTeams(Pelada $pelada): void
    {
        $existingTeams = Team::where('pelada_id', $pelada->id)->get();

        foreach ($existingTeams as $team) {
            $team->players()->detach();
            $team->delete();
        }
    }
}
