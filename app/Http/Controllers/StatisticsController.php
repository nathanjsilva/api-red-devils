<?php

namespace App\Http\Controllers;

use App\Models\Pelada;
use App\Models\Player;
use App\Services\StatisticsService;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function __construct(private readonly StatisticsService $statistics) {}

    public function playerInPelada($playerId, $peladaId)
    {
        $player = Player::find($playerId);
        if (! $player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $matchPlayer = $this->statistics->playerInPelada((int) $playerId, (int) $peladaId);

        if (! $matchPlayer) {
            return $this->errorResponse('Jogador não participou desta pelada.', 404);
        }

        return response()->json([
            'player' => $player,
            'pelada' => $pelada,
            'statistics' => [
                'goals' => $matchPlayer->goals,
                'assists' => $matchPlayer->assists,
                'goals_conceded' => $matchPlayer->goals_conceded,
                'result' => $matchPlayer->result,
                'goal_participation' => $matchPlayer->goals + $matchPlayer->assists,
            ],
        ]);
    }

    public function playerTotalStatistics($playerId)
    {
        $player = Player::find($playerId);
        if (! $player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        return response()->json([
            'player' => $player,
            'total_statistics' => $this->statistics->playerTotalStatistics($player),
        ]);
    }

    public function playersOverview(Request $request)
    {
        return response()->json([
            'reference_year' => $this->statistics->currentYear(),
            'total_peladas_in_year' => $this->statistics->totalPeladasInCurrentYear(),
            'minimum_matches_for_ranking' => $this->statistics->minimumMatchesForRanking(),
            'players' => $this->statistics->playersOverview($this->perPage($request)),
        ]);
    }

    public function winsRanking(Request $request)
    {
        return response()->json([
            'ranking_type' => 'Vitórias',
            'reference_year' => $this->statistics->currentYear(),
            'minimum_matches_for_ranking' => $this->statistics->minimumMatchesForRanking(),
            'ranking' => $this->statistics->winsRanking($this->perPage($request)),
        ]);
    }

    public function goalsRanking(Request $request)
    {
        return response()->json([
            'ranking_type' => 'Gols',
            'reference_year' => $this->statistics->currentYear(),
            'minimum_matches_for_ranking' => $this->statistics->minimumMatchesForRanking(),
            'ranking' => $this->statistics->goalsRanking($this->perPage($request)),
        ]);
    }

    public function assistsRanking(Request $request)
    {
        return response()->json([
            'ranking_type' => 'Assistências',
            'reference_year' => $this->statistics->currentYear(),
            'minimum_matches_for_ranking' => $this->statistics->minimumMatchesForRanking(),
            'ranking' => $this->statistics->assistsRanking($this->perPage($request)),
        ]);
    }

    public function goalParticipationRanking(Request $request)
    {
        return response()->json([
            'ranking_type' => 'Participação em Gols',
            'reference_year' => $this->statistics->currentYear(),
            'minimum_matches_for_ranking' => $this->statistics->minimumMatchesForRanking(),
            'ranking' => $this->statistics->goalParticipationRanking($this->perPage($request)),
        ]);
    }

    public function goalkeepersRanking(Request $request)
    {
        return response()->json([
            'ranking_type' => 'Goleiros (Gols Sofridos)',
            'reference_year' => $this->statistics->currentYear(),
            'minimum_matches_for_ranking' => $this->statistics->minimumMatchesForRanking(),
            'ranking' => $this->statistics->goalkeepersRanking($this->perPage($request)),
        ]);
    }

    public function peladaStatistics($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
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
            'statistics' => $this->statistics->peladaStatistics($pelada),
        ]);
    }
}
