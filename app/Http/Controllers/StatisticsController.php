<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComparePlayersRequest;
use App\Models\Pelada;
use App\Models\Player;
use App\Services\StatisticsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
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
        $filters = $this->filtersFromRequest($request);
        $minimumOverride = $this->minimumMatchesOverride($request);
        $paginator = $this->statistics->winsRankingFull($filters, $this->perPage($request), $minimumOverride);

        return $this->rankingEnvelope($paginator, $filters, $this->statistics->minimumMatchesForScope($filters, $minimumOverride), 'Vitórias');
    }

    public function goalsRanking(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $minimumOverride = $this->minimumMatchesOverride($request);
        $paginator = $this->statistics->goalsRankingFull($filters, $this->perPage($request), $minimumOverride);

        return $this->rankingEnvelope($paginator, $filters, $this->statistics->minimumMatchesForScope($filters, $minimumOverride), 'Gols');
    }

    public function assistsRanking(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $minimumOverride = $this->minimumMatchesOverride($request);
        $paginator = $this->statistics->assistsRankingFull($filters, $this->perPage($request), $minimumOverride);

        return $this->rankingEnvelope($paginator, $filters, $this->statistics->minimumMatchesForScope($filters, $minimumOverride), 'Assistências');
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

    public function goalParticipationsRanking(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $minimumOverride = $this->minimumMatchesOverride($request);
        $paginator = $this->statistics->goalParticipationsRankingFull($filters, $this->perPage($request), $minimumOverride);

        return $this->rankingEnvelope($paginator, $filters, $this->statistics->minimumMatchesForScope($filters, $minimumOverride), 'Participação em Gols');
    }

    public function winRateRanking(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $minimumOverride = $this->minimumMatchesOverride($request);
        $paginator = $this->statistics->winRateRankingFull($filters, $this->perPage($request), $minimumOverride);

        return $this->rankingEnvelope($paginator, $filters, $this->statistics->minimumMatchesForScope($filters, $minimumOverride), 'Aproveitamento');
    }

    public function appearancesRanking(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $minimumOverride = $this->minimumMatchesOverride($request);
        $paginator = $this->statistics->appearancesRankingFull($filters, $this->perPage($request), $minimumOverride);

        return $this->rankingEnvelope($paginator, $filters, $minimumOverride ?? 0, 'Presenças');
    }

    public function goalkeepersRanking(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $minimumOverride = $this->minimumMatchesOverride($request);
        $paginator = $this->statistics->goalkeepersRankingFull($filters, $this->perPage($request), $minimumOverride);

        return $this->rankingEnvelope($paginator, $filters, $this->statistics->minimumMatchesForScope($filters, $minimumOverride), 'Goleiros (Gols Sofridos)');
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

    /*
    |--------------------------------------------------------------------------
    | Novos endpoints (dashboard, jogador individual, comparação, goleiros,
    | pelada enriquecida, evolução e forma recente).
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);

        return response()->json([
            'data' => $this->statistics->dashboardOverview($filters),
            'meta' => [
                'filters' => $filters,
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function playerStatistics(Request $request, $playerId): JsonResponse
    {
        $player = Player::find($playerId);
        if (! $player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $filters = $this->filtersFromRequest($request);

        return response()->json([
            'data' => array_merge([
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'nickname' => $player->nickname,
                    'position' => $player->position,
                ],
            ], $this->statistics->playerStatistics($player, $filters)),
            'meta' => [
                'filters' => $filters,
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function comparePlayers(ComparePlayersRequest $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);
        $playerIds = $request->validated('player_ids');

        return response()->json([
            'data' => $this->statistics->comparePlayers($playerIds, $filters),
            'meta' => [
                'filters' => $filters,
                'player_ids' => $playerIds,
            ],
        ]);
    }

    public function goalkeepers(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);
        $paginator = $this->statistics->goalkeepersOverview($filters, $this->perPage($request));

        return $this->rankingEnvelope($paginator, $filters, $this->statistics->minimumMatchesForScope($filters), 'Goleiros');
    }

    public function goalkeeperStatistics(Request $request, $playerId): JsonResponse
    {
        $player = Player::find($playerId);
        if (! $player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        if ($player->position !== 'goleiro') {
            return $this->errorResponse('Jogador não é goleiro.', 422);
        }

        $filters = $this->filtersFromRequest($request);

        return response()->json([
            'data' => array_merge([
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'nickname' => $player->nickname,
                ],
            ], $this->statistics->goalkeeperStatistics($player, $filters)),
            'meta' => [
                'filters' => $filters,
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function matchStatistics($peladaId): JsonResponse
    {
        $pelada = Pelada::find($peladaId);
        if (! $pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        return response()->json([
            'data' => $this->statistics->matchStatistics($pelada),
            'meta' => [
                'pelada_id' => $pelada->id,
                'date' => $pelada->date,
                'division' => $pelada->division,
            ],
        ]);
    }

    public function evolution(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);
        $groupBy = in_array($request->query('group_by'), ['match', 'month', 'year'], true) ? $request->query('group_by') : 'match';
        $limit = $request->query('limit') !== null ? (int) $request->query('limit') : null;

        return response()->json([
            'data' => $this->statistics->evolution($groupBy, $filters, $limit),
            'meta' => [
                'group_by' => $groupBy,
                'filters' => $filters,
            ],
        ]);
    }

    public function peladasPerMonth(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);
        $limit = $request->query('limit') !== null ? (int) $request->query('limit') : null;

        return response()->json([
            'data' => $this->statistics->peladasPerMonth($filters, $limit),
            'meta' => [
                'filters' => $filters,
            ],
        ]);
    }

    public function recentForm(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);
        $lastMatches = in_array((int) $request->query('last_matches', 5), [5, 10], true) ? (int) $request->query('last_matches', 5) : 5;
        $limit = (int) $request->query('limit', 10);
        $minimumOverride = $this->minimumMatchesOverride($request);

        return response()->json([
            'data' => $this->statistics->recentForm($lastMatches, $filters, $limit, $minimumOverride),
            'meta' => [
                'last_matches' => $lastMatches,
                'limit' => $limit,
                'filters' => $filters,
            ],
        ]);
    }

    /**
     * Extrai os filtros de período e divisão aceitos pelos endpoints de
     * estatísticas (start_date, end_date, year, month, division) a partir da
     * query string. `division` inválida (fora de quinta/sabado) é ignorada.
     *
     * @return array<string, string>
     */
    private function filtersFromRequest(Request $request): array
    {
        $division = $request->query('division');

        return array_filter([
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'year' => $request->query('year'),
            'month' => $request->query('month'),
            'division' => in_array($division, ['quinta', 'sabado'], true) ? $division : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function minimumMatchesOverride(Request $request): ?int
    {
        return $request->query('minimum_matches') !== null ? max(0, (int) $request->query('minimum_matches')) : null;
    }

    /**
     * Envelope padrão (`data`/`meta`) dos rankings novos, preservando as
     * informações de paginação do Laravel dentro de `meta`.
     */
    private function rankingEnvelope(LengthAwarePaginator $paginator, array $filters, int $minimumMatches, string $label): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'ranking_type' => $label,
                'filters' => $filters,
                'minimum_matches' => $minimumMatches,
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
