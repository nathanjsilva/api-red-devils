<?php

namespace App\Http\Controllers;

use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function getTeamFields($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $teamFields = [];
        for ($i = 1; $i <= $pelada->qtd_times; $i++) {
            $teamFields[] = [
                'field_name' => "time_{$i}",
                'label' => "Time {$i}",
                'team_number' => $i,
            ];
        }

        return response()->json([
            'pelada' => [
                'id' => $pelada->id,
                'date' => $pelada->date,
                'location' => $pelada->location,
                'qtd_times' => $pelada->qtd_times,
                'qtd_jogadores_por_time' => $pelada->qtd_jogadores_por_time,
                'qtd_goleiros' => $pelada->qtd_goleiros,
            ],
            'team_fields' => $teamFields,
        ]);
    }

    public function getPeladaPlayers($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $peladaPlayerIds = MatchPlayer::where('pelada_id', $peladaId)->pluck('player_id');

        $players = Player::when($peladaPlayerIds->isNotEmpty(), function ($query) use ($peladaPlayerIds) {
            $query->whereNotIn('id', $peladaPlayerIds);
        })->get()->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->name,
                'nickname' => $player->nickname,
                'position' => $player->position,
                'is_goalkeeper' => $player->position === 'goleiro',
            ];
        });

        return response()->json([
            'pelada' => [
                'id' => $pelada->id,
                'date' => $pelada->date,
                'location' => $pelada->location,
            ],
            'players' => $players,
        ]);
    }

    public function getPeladaTeams($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $teams = Team::where('pelada_id', $peladaId)
            ->with('players')
            ->get()
            ->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'players' => $team->players->map(function ($player) {
                        return [
                            'id' => $player->id,
                            'name' => $player->name,
                            'nickname' => $player->nickname,
                            'position' => $player->position,
                        ];
                    }),
                ];
            });

        return response()->json([
            'pelada' => [
                'id' => $pelada->id,
                'date' => $pelada->date,
                'location' => $pelada->location,
            ],
            'teams' => $teams,
        ]);
    }

    public function getPeladaPlayersWithStatistics($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $matchPlayers = MatchPlayer::where('pelada_id', $peladaId)
            ->with('player')
            ->get()
            ->keyBy('player_id');

        $teams = Team::where('pelada_id', $peladaId)
            ->with('players')
            ->get();

        $playerIds = $matchPlayers->keys()->toArray();

        $teamPlayersData = DB::table('team_players')
            ->leftJoin('teams', 'team_players.team_id', '=', 'teams.id')
            ->whereIn('team_players.player_id', $playerIds)
            ->select(
                'team_players.player_id',
                'team_players.team_id',
                'teams.name as team_name',
                'teams.pelada_id'
            )
            ->get();

        $teamPlayers = collect();
        foreach ($teamPlayersData as $item) {
            $playerId = $item->player_id;

            if (! $teamPlayers->has($playerId) || $item->pelada_id == $peladaId) {
                $teamPlayers->put($playerId, (object) [
                    'player_id' => $playerId,
                    'team_id' => $item->team_id,
                    'team_name' => $item->team_name ?? ($item->team_id ? "Time {$item->team_id}" : null),
                ]);
            }
        }

        $mapStatistics = function (MatchPlayer $matchPlayer) {
            return [
                'goals' => $matchPlayer->goals,
                'assists' => $matchPlayer->assists,
                'goals_conceded' => $matchPlayer->goals_conceded,
                'result' => $matchPlayer->result,
                'goal_participation' => $matchPlayer->goals + $matchPlayer->assists,
            ];
        };

        if ($teams->isEmpty()) {
            $playersWithStats = $matchPlayers->map(function (MatchPlayer $matchPlayer) use ($teamPlayers, $mapStatistics) {
                $player = $matchPlayer->player;
                $teamPlayer = $teamPlayers->get($player->id);

                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'nickname' => $player->nickname,
                    'position' => $player->position,
                    'statistics' => $mapStatistics($matchPlayer),
                    'team' => $teamPlayer ? [
                        'id' => $teamPlayer->team_id,
                        'name' => $teamPlayer->team_name,
                    ] : null,
                ];
            })->values();

            return response()->json([
                'pelada' => [
                    'id' => $pelada->id,
                    'date' => $pelada->date,
                    'location' => $pelada->location,
                    'qtd_times' => $pelada->qtd_times,
                    'qtd_jogadores_por_time' => $pelada->qtd_jogadores_por_time,
                    'qtd_goleiros' => $pelada->qtd_goleiros,
                ],
                'teams' => [],
                'players' => $playersWithStats,
            ]);
        }

        $teamsWithPlayers = $teams->map(function (Team $team) use ($matchPlayers, $mapStatistics) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'players' => $team->players->map(function (Player $player) use ($matchPlayers, $mapStatistics) {
                    $matchPlayer = $matchPlayers->get($player->id);

                    return [
                        'id' => $player->id,
                        'name' => $player->name,
                        'nickname' => $player->nickname,
                        'position' => $player->position,
                        'statistics' => $matchPlayer ? $mapStatistics($matchPlayer) : null,
                    ];
                }),
            ];
        });

        return response()->json([
            'pelada' => [
                'id' => $pelada->id,
                'date' => $pelada->date,
                'location' => $pelada->location,
                'qtd_times' => $pelada->qtd_times,
                'qtd_jogadores_por_time' => $pelada->qtd_jogadores_por_time,
                'qtd_goleiros' => $pelada->qtd_goleiros,
            ],
            'teams' => $teamsWithPlayers,
        ]);
    }
}
