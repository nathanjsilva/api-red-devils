<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use App\Services\TeamOrganizerService;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private readonly TeamOrganizerService $teamOrganizer) {}

    /**
     * Organização manual: o cliente informa explicitamente quais jogadores
     * vão em cada time.
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
        ]);

        $assignedPlayerIds = collect($request->team_assignments)->pluck('player_ids')->flatten()->unique()->values();
        $existingPlayerIds = Player::whereIn('id', $assignedPlayerIds)->pluck('id');
        $invalidPlayers = $assignedPlayerIds->diff($existingPlayerIds)->values();

        if ($invalidPlayers->isNotEmpty()) {
            return $this->errorResponse(
                'Alguns jogadores não existem no sistema.',
                400,
                'Foram informados IDs de jogadores inválidos.',
                ['invalid_players' => $invalidPlayers]
            );
        }

        $teams = $this->teamOrganizer->organizeManual($pelada, $request->team_assignments);

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
            'player_ids' => 'required|array|min:'.($pelada->qtd_times * $pelada->qtd_jogadores_por_time),
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
        ]);
    }
}
