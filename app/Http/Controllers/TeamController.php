<?php

namespace App\Http\Controllers;

use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    /**
     * Retorna os campos dos times baseado na quantidade de times da pelada.
     */
    public function getTeamFields($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $teamFields = [];
        for ($i = 1; $i <= $pelada->qtd_times; $i++) {
            $teamFields[] = [
                'field_name' => "time_{$i}",
                'label' => "Time {$i}",
                'team_number' => $i
            ];
        }

        return response()->json([
            'pelada' => [
                'id' => $pelada->id,
                'date' => $pelada->date,
                'location' => $pelada->location,
                'qtd_times' => $pelada->qtd_times,
                'qtd_jogadores_por_time' => $pelada->qtd_jogadores_por_time,
                'qtd_goleiros' => $pelada->qtd_goleiros
            ],
            'team_fields' => $teamFields
        ]);
    }

    /**
     * Retorna os jogadores que participaram de uma pelada específica.
     */
    public function getPeladaPlayers($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $players = MatchPlayer::where('pelada_id', $peladaId)
            ->with('player')
            ->get()
            ->map(function ($matchPlayer) {
                return [
                    'id' => $matchPlayer->player->id,
                    'name' => $matchPlayer->player->name,
                    'nickname' => $matchPlayer->player->nickname,
                    'position' => $matchPlayer->player->position,
                    'phone' => $matchPlayer->player->phone,
                    'is_goalkeeper' => $matchPlayer->player->position === 'goleiro'
                ];
            });

        return response()->json([
            'pelada' => [
                'id' => $pelada->id,
                'date' => $pelada->date,
                'location' => $pelada->location
            ],
            'players' => $players
        ]);
    }

    /**
     * Organiza jogadores nos times da pelada.
     */
    public function organizePlayers(Request $request, $peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $request->validate([
            'team_assignments' => 'required|array',
            'team_assignments.*' => 'required|array',
            'team_assignments.*.team_number' => 'required|integer|min:1|max:' . $pelada->qtd_times,
            'team_assignments.*.player_ids' => 'required|array',
            'team_assignments.*.player_ids.*' => 'exists:players,id'
        ]);

        // Verifica se os times já foram organizados
        $existingTeams = Team::where('pelada_id', $peladaId)->count();
        if ($existingTeams > 0) {
            return response()->json(['message' => 'Times já foram organizados para esta pelada.'], 400);
        }

        // Verifica se todos os jogadores participaram da pelada
        $peladaPlayerIds = MatchPlayer::where('pelada_id', $peladaId)->pluck('player_id')->toArray();
        $assignedPlayerIds = [];
        
        foreach ($request->team_assignments as $assignment) {
            $assignedPlayerIds = array_merge($assignedPlayerIds, $assignment['player_ids']);
        }

        $invalidPlayers = array_diff($assignedPlayerIds, $peladaPlayerIds);
        if (!empty($invalidPlayers)) {
            return response()->json([
                'message' => 'Alguns jogadores não participaram desta pelada.',
                'invalid_players' => $invalidPlayers
            ], 400);
        }

        DB::beginTransaction();
        
        try {
            // Cria os times
            $teams = [];
            foreach ($request->team_assignments as $assignment) {
                $team = Team::create([
                    'pelada_id' => $peladaId,
                    'name' => "Time {$assignment['team_number']}"
                ]);
                
                // Adiciona jogadores ao time
                $team->players()->attach($assignment['player_ids']);
                
                $teams[] = [
                    'id' => $team->id,
                    'name' => $team->name,
                    'team_number' => $assignment['team_number'],
                    'players' => $team->players()->with('player')->get()->map(function ($teamPlayer) {
                        return [
                            'id' => $teamPlayer->player->id,
                            'name' => $teamPlayer->player->name,
                            'nickname' => $teamPlayer->player->nickname,
                            'position' => $teamPlayer->player->position
                        ];
                    })
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Times organizados com sucesso.',
                'teams' => $teams
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Erro ao organizar times.'], 500);
        }
    }

    /**
     * Retorna times organizados de uma pelada.
     */
    public function getPeladaTeams($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $teams = Team::where('pelada_id', $peladaId)
            ->with(['players' => function ($query) {
                $query->with('player');
            }])
            ->get()
            ->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'players' => $team->players->map(function ($teamPlayer) {
                        return [
                            'id' => $teamPlayer->player->id,
                            'name' => $teamPlayer->player->name,
                            'nickname' => $teamPlayer->player->nickname,
                            'position' => $teamPlayer->player->position
                        ];
                    })
                ];
            });

        return response()->json([
            'pelada' => [
                'id' => $pelada->id,
                'date' => $pelada->date,
                'location' => $pelada->location
            ],
            'teams' => $teams
        ]);
    }
}