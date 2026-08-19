<?php

namespace App\Services;

use App\Exceptions\InsufficientFieldPlayersException;
use App\Exceptions\InsufficientGoalkeepersException;
use App\Models\GoalPosition;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamOrganizerService
{
    /**
     * Organiza os times a partir de uma distribuição explícita de jogadores de linha
     * por time e, opcionalmente, dos goleiros.
     *
     * @param  array<int, array{team_number: int, player_ids: array<int, int>}>  $teamAssignments
     * @param  array<int, array{position: int, player_id: int}>  $goalkeeperAssignments
     */
    public function organizeManual(Pelada $pelada, array $teamAssignments, array $goalkeeperAssignments = []): Collection
    {
        return DB::transaction(function () use ($pelada, $teamAssignments, $goalkeeperAssignments) {
            $this->clearExistingTeams($pelada);

            $teamsByNumber = collect();

            foreach ($teamAssignments as $assignment) {
                $team = Team::create([
                    'pelada_id' => $pelada->id,
                    'name' => "Time {$assignment['team_number']}",
                ]);

                $team->players()->attach($assignment['player_ids']);

                $teamsByNumber->put($assignment['team_number'], $team);
            }

            $this->assignGoalkeepersManually($pelada, $teamsByNumber, $goalkeeperAssignments);

            return $teamsByNumber->values()->map(fn (Team $team) => $team->load('players'));
        });
    }

    /**
     * Organiza os times automaticamente, distribuindo jogadores de linha entre os
     * times e goleiros conforme a configuração da pelada — como goleiro fixo por
     * time (quando `qtd_goleiros >= qtd_times`) ou como posição de gol independente
     * do time de linha (quando `qtd_goleiros < qtd_times`).
     *
     * @param  array<int, int>  $playerIds
     *
     * @throws InsufficientGoalkeepersException
     * @throws InsufficientFieldPlayersException
     */
    public function organizeAutomatic(Pelada $pelada, array $playerIds): Collection
    {
        $players = Player::whereIn('id', $playerIds)->get();
        $goalkeepers = $players->where('position', 'goleiro')->values();
        $fieldPlayers = $players->where('position', 'linha')->values();

        if ($goalkeepers->count() < $pelada->qtd_goleiros) {
            throw new InsufficientGoalkeepersException;
        }

        if ($fieldPlayers->count() < $pelada->qtd_times * $pelada->qtd_jogadores_por_time) {
            throw new InsufficientFieldPlayersException;
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

            $this->distributeGoalkeepersAutomatically($pelada, $goalkeepers, $teams);

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

    /**
     * Distribui goleiros na organização automática.
     *
     * - `qtd_goleiros >= qtd_times`: um goleiro fixo por time, anexado em `team_players`
     *   (amarração permanente time↔goleiro; excedente vira reserva, não alocada).
     * - `qtd_goleiros < qtd_times`: cada goleiro vira uma `GoalPosition` independente
     *   do time — os times de linha se revezam nessas posições durante a pelada.
     */
    private function distributeGoalkeepersAutomatically(Pelada $pelada, Collection $goalkeepers, Collection $teams): void
    {
        $goalkeepers = $goalkeepers->take($pelada->qtd_goleiros)->values();

        if ($pelada->qtd_goleiros >= $pelada->qtd_times) {
            foreach ($teams as $index => $team) {
                if (isset($goalkeepers[$index])) {
                    $team->players()->attach($goalkeepers[$index]->id);
                }
            }

            return;
        }

        for ($numero = 1; $numero <= $pelada->qtd_goleiros; $numero++) {
            GoalPosition::create([
                'pelada_id' => $pelada->id,
                'numero' => $numero,
                'goleiro_id' => $goalkeepers[$numero - 1]->id,
            ]);
        }
    }

    /**
     * Atribui goleiros na organização manual, conforme o `position` informado pelo
     * cliente para cada entrada de `goalkeeperAssignments`:
     * - `qtd_goleiros >= qtd_times`: `position` é o `team_number`, goleiro anexado em
     *   `team_players` daquele time.
     * - `qtd_goleiros < qtd_times`: `position` é o número da posição de gol (1..qtd_goleiros).
     */
    private function assignGoalkeepersManually(Pelada $pelada, Collection $teamsByNumber, array $goalkeeperAssignments): void
    {
        if (empty($goalkeeperAssignments)) {
            return;
        }

        if ($pelada->qtd_goleiros >= $pelada->qtd_times) {
            foreach ($goalkeeperAssignments as $assignment) {
                $team = $teamsByNumber->get($assignment['position']);
                if ($team) {
                    $team->players()->attach($assignment['player_id']);
                }
            }

            return;
        }

        foreach ($goalkeeperAssignments as $assignment) {
            GoalPosition::create([
                'pelada_id' => $pelada->id,
                'numero' => $assignment['position'],
                'goleiro_id' => $assignment['player_id'],
            ]);
        }
    }

    private function clearExistingTeams(Pelada $pelada): void
    {
        $existingTeams = Team::where('pelada_id', $pelada->id)->get();

        foreach ($existingTeams as $team) {
            $team->players()->detach();
            $team->delete();
        }

        GoalPosition::where('pelada_id', $pelada->id)->delete();
    }
}
