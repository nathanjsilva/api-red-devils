<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoalPosition;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use App\Services\TeamOrganizerService;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private readonly TeamOrganizerService $teamOrganizer) {}

    /**
     * Organização manual: o cliente informa explicitamente quais jogadores de
     * linha vão em cada time e, opcionalmente, os goleiros (por time, quando
     * `qtd_goleiros >= qtd_times`, ou por posição de gol, quando `qtd_goleiros < qtd_times`).
     */
    public function organizeManual(Request $request, $peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $request->validate([
            'team_assignments' => 'required|array',
            'team_assignments.*' => 'required|array',
            'team_assignments.*.team_number' => 'required|integer|min:1|max:'.$pelada->qtd_times,
            'team_assignments.*.player_ids' => 'required|array',
            'team_assignments.*.player_ids.*' => 'exists:players,id',
            'goalkeeper_assignments' => 'sometimes|array',
            'goalkeeper_assignments.*.position' => 'required_with:goalkeeper_assignments|integer|min:1',
            'goalkeeper_assignments.*.player_id' => 'required_with:goalkeeper_assignments|exists:players,id',
        ]);

        $goalkeeperAssignments = $request->input('goalkeeper_assignments', []);

        $assignedPlayerIds = collect($request->team_assignments)->pluck('player_ids')->flatten()
            ->merge(collect($goalkeeperAssignments)->pluck('player_id'))
            ->unique()->values();
        $existingPlayers = Player::whereIn('id', $assignedPlayerIds)->get()->keyBy('id');
        $invalidPlayers = $assignedPlayerIds->diff($existingPlayers->keys())->values();

        if ($invalidPlayers->isNotEmpty()) {
            return $this->errorResponse(
                'Alguns jogadores não existem no sistema.',
                400,
                'Foram informados IDs de jogadores inválidos.',
                ['invalid_players' => $invalidPlayers]
            );
        }

        $lineSlotGoalkeepers = collect($request->team_assignments)->pluck('player_ids')->flatten()
            ->filter(fn ($id) => $existingPlayers->get($id)?->position === 'goleiro')->values();
        $goalkeeperSlotFieldPlayers = collect($goalkeeperAssignments)->pluck('player_id')
            ->filter(fn ($id) => $existingPlayers->get($id)?->position !== 'goleiro')->values();

        if ($lineSlotGoalkeepers->isNotEmpty() || $goalkeeperSlotFieldPlayers->isNotEmpty()) {
            return $this->errorResponse(
                'Goleiros e jogadores de linha não podem ser misturados entre os slots.',
                400,
                'Verifique se cada jogador foi escalado na seção correta (linha ou goleiro).',
                [
                    'goalkeepers_in_line_slots' => $lineSlotGoalkeepers,
                    'field_players_in_goalkeeper_slots' => $goalkeeperSlotFieldPlayers,
                ]
            );
        }

        $teams = $this->teamOrganizer->organizeManual($pelada, $request->team_assignments, $goalkeeperAssignments);

        return response()->json([
            'message' => 'Times organizados com sucesso.',
            'teams' => $teams->values()->map(function (Team $team, int $index) use ($request) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'team_number' => $request->team_assignments[$index]['team_number'],
                    'players' => $team->players->map(fn (Player $player) => [
                        'id' => $player->id,
                        'name' => $player->name,
                        'nickname' => $player->nickname,
                        'position' => $player->position,
                    ]),
                ];
            }),
            'goal_positions' => $this->goalPositionsResponse($pelada),
        ]);
    }

    /**
     * Organização automática: o cliente informa apenas quem vai jogar e o
     * sistema distribui goleiros e jogadores de linha entre os times.
     */
    public function organizeAutomatic(Request $request, $peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $request->validate([
            'player_ids' => 'required|array|min:'.($pelada->qtd_times * $pelada->qtd_jogadores_por_time + $pelada->qtd_goleiros),
            'player_ids.*' => 'exists:players,id',
        ]);

        $teams = $this->teamOrganizer->organizeAutomatic($pelada, $request->player_ids);

        return response()->json([
            'message' => 'Times organizados com sucesso.',
            'teams' => $teams->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'players' => $team->players,
            ])->values(),
            'goal_positions' => $this->goalPositionsResponse($pelada),
        ]);
    }

    /**
     * Posições de gol da pelada (usadas apenas quando `qtd_goleiros < qtd_times`),
     * cada uma com o goleiro que a defende, independente do time de linha.
     */
    private function goalPositionsResponse(Pelada $pelada)
    {
        return GoalPosition::where('pelada_id', $pelada->id)
            ->with('goleiro')
            ->orderBy('numero')
            ->get()
            ->map(fn (GoalPosition $goalPosition) => [
                'numero' => $goalPosition->numero,
                'goleiro' => $goalPosition->goleiro ? [
                    'id' => $goalPosition->goleiro->id,
                    'name' => $goalPosition->goleiro->name,
                    'nickname' => $goalPosition->goleiro->nickname,
                    'position' => $goalPosition->goleiro->position,
                ] : null,
            ])->values();
    }
}
