<?php

namespace App\Http\Controllers;

use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function getTeamFields($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
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
        if (!$pelada) {
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

    public function organizePlayers(Request $request, $peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $request->validate([
            'team_assignments' => 'required|array',
            'team_assignments.*' => 'required|array',
            'team_assignments.*.team_number' => 'required|integer|min:1|max:' . $pelada->qtd_times,
            'team_assignments.*.player_ids' => 'required|array',
            'team_assignments.*.player_ids.*' => 'exists:players,id',
        ]);

        $existingTeams = Team::where('pelada_id', $peladaId)->get();
        foreach ($existingTeams as $team) {
            $team->players()->detach();
            $team->delete();
        }

        $assignedPlayerIds = [];
        foreach ($request->team_assignments as $assignment) {
            $assignedPlayerIds = array_merge($assignedPlayerIds, $assignment['player_ids']);
        }

        $existingPlayerIds = Player::whereIn('id', $assignedPlayerIds)->pluck('id')->toArray();
        $invalidPlayers = array_diff($assignedPlayerIds, $existingPlayerIds);
        if (!empty($invalidPlayers)) {
            return $this->errorResponse(
                'Alguns jogadores não existem no sistema.',
                400,
                'Foram informados IDs de jogadores inválidos.',
                ['invalid_players' => $invalidPlayers]
            );
        }

        DB::beginTransaction();

        try {
            $teams = [];
            foreach ($request->team_assignments as $assignment) {
                $team = Team::create([
                    'pelada_id' => $peladaId,
                    'name' => "Time {$assignment['team_number']}",
                ]);

                $team->players()->attach($assignment['player_ids']);

                $teams[] = [
                    'id' => $team->id,
                    'name' => $team->name,
                    'team_number' => $assignment['team_number'],
                    'players' => $team->players()->get()->map(function ($player) {
                        return [
                            'id' => $player->id,
                            'name' => $player->name,
                            'nickname' => $player->nickname,
                            'position' => $player->position,
                        ];
                    }),
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Times organizados com sucesso.',
                'teams' => $teams,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Erro ao organizar times.', 500, $e->getMessage());
        }
    }

    public function getPeladaTeams($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
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
        if (!$pelada) {
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

            if (!$teamPlayers->has($playerId) || $item->pelada_id == $peladaId) {
                $teamPlayers->put($playerId, (object) [
                    'player_id' => $playerId,
                    'team_id' => $item->team_id,
                    'team_name' => $item->team_name ?? ($item->team_id ? "Time {$item->team_id}" : null),
                ]);
            }
        }

        if ($teams->isEmpty()) {
            $playersWithStats = $matchPlayers->map(function ($matchPlayer) use ($teamPlayers) {
                $player = $matchPlayer->player;
                $teamPlayer = $teamPlayers->get($player->id);

                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'nickname' => $player->nickname,
                    'position' => $player->position,
                    'statistics' => [
                        'goals' => $matchPlayer->goals,
                        'assists' => $matchPlayer->assists,
                        'goals_conceded' => $matchPlayer->goals_conceded,
                        'is_winner' => $matchPlayer->is_winner,
                        'result' => $matchPlayer->result ?? ($matchPlayer->is_winner ? 'win' : 'loss'),
                        'goal_participation' => $matchPlayer->goals + $matchPlayer->assists,
                    ],
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

        $teamsWithPlayers = $teams->map(function ($team) use ($matchPlayers) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'players' => $team->players->map(function ($player) use ($matchPlayers) {
                    $matchPlayer = $matchPlayers->get($player->id);

                    return [
                        'id' => $player->id,
                        'name' => $player->name,
                        'nickname' => $player->nickname,
                        'position' => $player->position,
                        'statistics' => $matchPlayer ? [
                            'goals' => $matchPlayer->goals,
                            'assists' => $matchPlayer->assists,
                            'goals_conceded' => $matchPlayer->goals_conceded,
                            'is_winner' => $matchPlayer->is_winner,
                            'result' => $matchPlayer->result ?? ($matchPlayer->is_winner ? 'win' : 'loss'),
                            'goal_participation' => $matchPlayer->goals + $matchPlayer->assists,
                        ] : null,
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
