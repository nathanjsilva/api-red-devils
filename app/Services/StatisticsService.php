<?php

namespace App\Services;

use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /**
     * TTL do cache das consultas agregadas mais pesadas (dashboard, rankings, evolução, forma recente).
     */
    private const CACHE_TTL_SECONDS = 300;

    public function currentYear(): int
    {
        return now()->year;
    }

    public function totalPeladasInCurrentYear(): int
    {
        return Pelada::whereYear('date', $this->currentYear())->count();
    }

    /**
     * Quantidade mínima de partidas que um jogador precisa ter disputado no ano
     * corrente para aparecer nos rankings: 20% do total de peladas do ano.
     */
    public function minimumMatchesForRanking(): int
    {
        $totalPeladas = $this->totalPeladasInCurrentYear();

        if ($totalPeladas === 0) {
            return 0;
        }

        return (int) ceil($totalPeladas * 0.2);
    }

    private function rankingBaseQuery(string $selectRaw): Builder
    {
        $currentYear = $this->currentYear();

        return MatchPlayer::selectRaw($selectRaw)
            ->whereHas('pelada', function ($query) use ($currentYear) {
                $query->whereYear('date', $currentYear);
            });
    }

    public function playerInPelada(int $playerId, int $peladaId): ?MatchPlayer
    {
        return MatchPlayer::where('player_id', $playerId)
            ->where('pelada_id', $peladaId)
            ->with(['player', 'pelada'])
            ->first();
    }

    public function playerTotalStatistics(Player $player): array
    {
        $statistics = MatchPlayer::where('player_id', $player->id)
            ->selectRaw('
                SUM(goals) as total_goals,
                SUM(assists) as total_assists,
                SUM(goals_conceded) as total_goals_conceded,
                COUNT(*) as total_matches,
                SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) as total_wins,
                SUM(CASE WHEN result = "loss" THEN 1 ELSE 0 END) as total_losses,
                SUM(CASE WHEN result = "draw" THEN 1 ELSE 0 END) as total_draws,
                AVG(goals + assists) as avg_goal_participation
            ')
            ->first();

        $totalMatches = (int) ($statistics->total_matches ?? 0);
        $totalWins = (int) ($statistics->total_wins ?? 0);

        return [
            'total_goals' => (int) ($statistics->total_goals ?? 0),
            'total_assists' => (int) ($statistics->total_assists ?? 0),
            'total_goals_conceded' => (int) ($statistics->total_goals_conceded ?? 0),
            'total_matches' => $totalMatches,
            'total_wins' => $totalWins,
            'total_losses' => (int) ($statistics->total_losses ?? 0),
            'total_draws' => (int) ($statistics->total_draws ?? 0),
            'win_rate' => $totalMatches > 0 ? round(($totalWins / $totalMatches) * 100, 2) : 0,
            'avg_goal_participation' => round($statistics->avg_goal_participation ?? 0, 2),
        ];
    }

    public function playersOverview(int $perPage): LengthAwarePaginator
    {
        $minimumMatches = $this->minimumMatchesForRanking();

        $groupedStatistics = $this->rankingBaseQuery('
                player_id,
                COUNT(*) as total_matches,
                SUM(goals) as total_goals,
                SUM(assists) as total_assists,
                SUM(goals_conceded) as total_goals_conceded,
                SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) as total_wins,
                ROUND(AVG(goals + assists), 2) as avg_goal_participation,
                ROUND(AVG(goals), 2) as avg_goals_per_match,
                ROUND(AVG(assists), 2) as avg_assists_per_match
            ')
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        return Player::orderBy('name')
            ->paginate($perPage)
            ->through(function (Player $player) use ($groupedStatistics, $minimumMatches) {
                $stats = $groupedStatistics->get($player->id);
                $totalMatches = (int) ($stats->total_matches ?? 0);

                return [
                    'player' => [
                        'id' => $player->id,
                        'name' => $player->name,
                        'nickname' => $player->nickname,
                        'position' => $player->position,
                    ],
                    'statistics' => [
                        'total_matches' => $totalMatches,
                        'total_wins' => (int) ($stats->total_wins ?? 0),
                        'total_goals' => (int) ($stats->total_goals ?? 0),
                        'total_assists' => (int) ($stats->total_assists ?? 0),
                        'avg_goal_participation' => (float) ($stats->avg_goal_participation ?? 0),
                        'avg_goals_per_match' => (float) ($stats->avg_goals_per_match ?? 0),
                        'avg_assists_per_match' => (float) ($stats->avg_assists_per_match ?? 0),
                        'total_goals_conceded' => $player->position === 'goleiro' ? (int) ($stats->total_goals_conceded ?? 0) : null,
                        'eligible_for_ranking' => $totalMatches >= $minimumMatches,
                    ],
                ];
            });
    }

    public function winsRanking(int $perPage): LengthAwarePaginator
    {
        $minimumMatches = $this->minimumMatchesForRanking();

        return $this->rankingBaseQuery('
                player_id,
                COUNT(*) as total_matches,
                SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) as total_wins,
                SUM(CASE WHEN result = "loss" THEN 1 ELSE 0 END) as total_losses,
                SUM(CASE WHEN result = "draw" THEN 1 ELSE 0 END) as total_draws,
                ROUND((SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as win_rate
            ')
            ->groupBy('player_id')
            ->having('total_matches', '>=', $minimumMatches)
            ->orderBy('total_wins', 'desc')
            ->orderBy('win_rate', 'desc')
            ->with('player')
            ->paginate($perPage)
            ->through(fn (MatchPlayer $item) => [
                'player' => $item->player,
                'total_wins' => $item->total_wins,
                'total_losses' => $item->total_losses,
                'total_draws' => $item->total_draws,
                'total_matches' => $item->total_matches,
                'win_rate' => $item->win_rate.'%',
            ]);
    }

    public function goalsRanking(int $perPage): LengthAwarePaginator
    {
        $minimumMatches = $this->minimumMatchesForRanking();

        return $this->rankingBaseQuery('
                player_id,
                SUM(goals) as total_goals,
                COUNT(*) as total_matches,
                ROUND(AVG(goals), 2) as avg_goals_per_match
            ')
            ->groupBy('player_id')
            ->having('total_matches', '>=', $minimumMatches)
            ->having('total_goals', '>', 0)
            ->orderBy('total_goals', 'desc')
            ->orderBy('avg_goals_per_match', 'desc')
            ->with('player')
            ->paginate($perPage)
            ->through(fn (MatchPlayer $item) => [
                'player' => $item->player,
                'total_goals' => $item->total_goals,
                'total_matches' => $item->total_matches,
                'avg_goals_per_match' => $item->avg_goals_per_match,
            ]);
    }

    public function assistsRanking(int $perPage): LengthAwarePaginator
    {
        $minimumMatches = $this->minimumMatchesForRanking();

        return $this->rankingBaseQuery('
                player_id,
                SUM(assists) as total_assists,
                COUNT(*) as total_matches,
                ROUND(AVG(assists), 2) as avg_assists_per_match
            ')
            ->groupBy('player_id')
            ->having('total_matches', '>=', $minimumMatches)
            ->having('total_assists', '>', 0)
            ->orderBy('total_assists', 'desc')
            ->orderBy('avg_assists_per_match', 'desc')
            ->with('player')
            ->paginate($perPage)
            ->through(fn (MatchPlayer $item) => [
                'player' => $item->player,
                'total_assists' => $item->total_assists,
                'total_matches' => $item->total_matches,
                'avg_assists_per_match' => $item->avg_assists_per_match,
            ]);
    }

    public function goalParticipationRanking(int $perPage): LengthAwarePaginator
    {
        $minimumMatches = $this->minimumMatchesForRanking();

        return $this->rankingBaseQuery('
                player_id,
                SUM(goals + assists) as total_goal_participation,
                COUNT(*) as total_matches,
                ROUND(AVG(goals + assists), 2) as avg_goal_participation
            ')
            ->groupBy('player_id')
            ->having('total_matches', '>=', $minimumMatches)
            ->having('total_goal_participation', '>', 0)
            ->orderBy('total_goal_participation', 'desc')
            ->orderBy('avg_goal_participation', 'desc')
            ->with('player')
            ->paginate($perPage)
            ->through(fn (MatchPlayer $item) => [
                'player' => $item->player,
                'total_goal_participation' => $item->total_goal_participation,
                'total_matches' => $item->total_matches,
                'avg_goal_participation' => $item->avg_goal_participation,
            ]);
    }

    public function peladaStatistics(Pelada $pelada): array
    {
        $statistics = MatchPlayer::where('pelada_id', $pelada->id)
            ->with('player')
            ->get()
            ->map(function (MatchPlayer $matchPlayer) {
                $player = $matchPlayer->player;

                $stats = [
                    'player' => [
                        'id' => $player->id,
                        'name' => $player->name,
                        'nickname' => $player->nickname,
                        'position' => $player->position,
                    ],
                    'statistics' => [
                        'goals' => $matchPlayer->goals,
                        'assists' => $matchPlayer->assists,
                        'result' => $matchPlayer->result,
                        'goal_participation' => $matchPlayer->goals + $matchPlayer->assists,
                    ],
                ];

                if ($player->position === 'goleiro') {
                    $stats['statistics']['goals_conceded'] = $matchPlayer->goals_conceded;
                }

                return $stats;
            });

        $fieldPlayers = $statistics->filter(fn ($stat) => $stat['player']['position'] === 'linha')->values();
        $goalkeepers = $statistics->filter(fn ($stat) => $stat['player']['position'] === 'goleiro')->values();

        return [
            'field_players' => $fieldPlayers,
            'goalkeepers' => $goalkeepers,
            'total_players' => $statistics->count(),
            'total_goals' => $statistics->sum('statistics.goals'),
            'total_assists' => $statistics->sum('statistics.assists'),
            'winners_count' => $statistics->filter(fn ($stat) => $stat['statistics']['result'] === 'win')->count(),
            'draws_count' => $statistics->filter(fn ($stat) => $stat['statistics']['result'] === 'draw')->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Novo módulo de estatísticas (dashboard, rankings completos, jogador
    | individual, comparação, goleiros, pelada enriquecida, evolução e forma
    | recente). Os métodos acima (rankings antigos, playersOverview, etc.)
    | continuam intocados e em uso pelos endpoints já existentes.
    |--------------------------------------------------------------------------
    */

    private function cacheKey(string $prefix, array $payload): string
    {
        ksort($payload);

        return 'statistics:'.$prefix.':'.md5(json_encode($payload));
    }

    /**
     * Aplica os filtros de período (start_date/end_date/year/month) numa query
     * cuja tabela alvo tenha uma coluna de data (por padrão, `date`).
     */
    private function applyDateFilters($query, array $filters, string $column = 'date')
    {
        if (! empty($filters['start_date'])) {
            $query->whereDate($column, '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate($column, '<=', $filters['end_date']);
        }

        if (! empty($filters['year'])) {
            $query->whereYear($column, $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->whereMonth($column, $filters['month']);
        }

        return $query;
    }

    /**
     * Mínimo de partidas para elegibilidade num escopo arbitrário de filtros:
     * 20% do total de peladas do escopo (mesma regra de `minimumMatchesForRanking`,
     * generalizada para aceitar filtros e um valor de override vindo da query string.
     */
    public function minimumMatchesForScope(array $filters, ?int $override = null): int
    {
        if ($override !== null) {
            return max(0, $override);
        }

        $totalPeladas = tap(Pelada::query(), fn ($q) => $this->applyDateFilters($q, $filters))->count();

        if ($totalPeladas === 0) {
            return 0;
        }

        return (int) ceil($totalPeladas * 0.2);
    }

    /**
     * Query agregada única, reaproveitada por todos os rankings "completos" e
     * pelo dashboard: uma linha por jogador com todas as métricas possíveis,
     * já filtrada pelo período informado. Cada consumidor só decide
     * order/having/paginate em cima dela.
     */
    private function fullRankingBaseQuery(array $filters): Builder
    {
        return MatchPlayer::selectRaw('
                player_id,
                COUNT(*) as total_matches,
                SUM(COALESCE(goals, 0)) as total_goals,
                SUM(COALESCE(assists, 0)) as total_assists,
                SUM(COALESCE(goals, 0) + COALESCE(assists, 0)) as total_goal_participations,
                SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) as total_wins,
                SUM(CASE WHEN result = "loss" THEN 1 ELSE 0 END) as total_losses,
                SUM(CASE WHEN result = "draw" THEN 1 ELSE 0 END) as total_draws,
                ROUND(SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_rate,
                ROUND(SUM(COALESCE(goals, 0)) / COUNT(*), 2) as avg_goals_per_match,
                ROUND(SUM(COALESCE(assists, 0)) / COUNT(*), 2) as avg_assists_per_match,
                ROUND(SUM(COALESCE(goals, 0) + COALESCE(assists, 0)) / COUNT(*), 2) as avg_goal_participations_per_match,
                SUM(COALESCE(goals_conceded, 0)) as total_goals_conceded,
                ROUND(SUM(COALESCE(goals_conceded, 0)) / COUNT(*), 2) as avg_goals_conceded_per_match
            ')
            ->whereHas('pelada', fn ($query) => $this->applyDateFilters($query, $filters))
            ->groupBy('player_id');
    }

    /**
     * Pagina e padroniza o formato de item de um ranking "completo" construído
     * a partir de `fullRankingBaseQuery`: posição, jogador e todas as métricas
     * cruzadas exigidas pelo endpoint de rankings.
     */
    private function paginateFullRanking(Builder $query, int $perPage, string $valueColumn, ?string $averageColumn): LengthAwarePaginator
    {
        $paginator = $query->with('player')->paginate($perPage);
        $offset = ($paginator->currentPage() - 1) * $paginator->perPage();

        $items = $paginator->getCollection()->values()->map(function ($item, $index) use ($offset, $valueColumn, $averageColumn) {
            return [
                'position' => $offset + $index + 1,
                'player' => [
                    'id' => $item->player->id,
                    'name' => $item->player->name,
                    'nickname' => $item->player->nickname,
                    'position' => $item->player->position,
                ],
                'matches' => (int) $item->total_matches,
                'goals' => (int) $item->total_goals,
                'assists' => (int) $item->total_assists,
                'goal_participations' => (int) $item->total_goal_participations,
                'wins' => (int) $item->total_wins,
                'win_rate' => (float) $item->win_rate,
                'average_per_match' => $averageColumn !== null ? (float) $item->{$averageColumn} : null,
                'value' => (float) $item->{$valueColumn},
            ];
        });

        $paginator->setCollection($items);

        return $paginator;
    }

    public function goalsRankingFull(array $filters, int $perPage, ?int $minimumMatchesOverride = null): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheKey('rankings.goals', $filters + ['per_page' => $perPage, 'page' => request()->query('page', 1), 'min' => $minimumMatchesOverride]),
            self::CACHE_TTL_SECONDS,
            function () use ($filters, $perPage, $minimumMatchesOverride) {
                $minimum = $this->minimumMatchesForScope($filters, $minimumMatchesOverride);

                $query = $this->fullRankingBaseQuery($filters)
                    ->having('total_matches', '>=', $minimum)
                    ->having('total_goals', '>', 0)
                    ->orderBy('total_goals', 'desc')
                    ->orderBy('avg_goals_per_match', 'desc');

                return $this->paginateFullRanking($query, $perPage, 'total_goals', 'avg_goals_per_match');
            }
        );
    }

    public function assistsRankingFull(array $filters, int $perPage, ?int $minimumMatchesOverride = null): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheKey('rankings.assists', $filters + ['per_page' => $perPage, 'page' => request()->query('page', 1), 'min' => $minimumMatchesOverride]),
            self::CACHE_TTL_SECONDS,
            function () use ($filters, $perPage, $minimumMatchesOverride) {
                $minimum = $this->minimumMatchesForScope($filters, $minimumMatchesOverride);

                $query = $this->fullRankingBaseQuery($filters)
                    ->having('total_matches', '>=', $minimum)
                    ->having('total_assists', '>', 0)
                    ->orderBy('total_assists', 'desc')
                    ->orderBy('avg_assists_per_match', 'desc');

                return $this->paginateFullRanking($query, $perPage, 'total_assists', 'avg_assists_per_match');
            }
        );
    }

    public function goalParticipationsRankingFull(array $filters, int $perPage, ?int $minimumMatchesOverride = null): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheKey('rankings.goal_participations', $filters + ['per_page' => $perPage, 'page' => request()->query('page', 1), 'min' => $minimumMatchesOverride]),
            self::CACHE_TTL_SECONDS,
            function () use ($filters, $perPage, $minimumMatchesOverride) {
                $minimum = $this->minimumMatchesForScope($filters, $minimumMatchesOverride);

                $query = $this->fullRankingBaseQuery($filters)
                    ->having('total_matches', '>=', $minimum)
                    ->having('total_goal_participations', '>', 0)
                    ->orderBy('total_goal_participations', 'desc')
                    ->orderBy('avg_goal_participations_per_match', 'desc');

                return $this->paginateFullRanking($query, $perPage, 'total_goal_participations', 'avg_goal_participations_per_match');
            }
        );
    }

    public function winsRankingFull(array $filters, int $perPage, ?int $minimumMatchesOverride = null): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheKey('rankings.wins', $filters + ['per_page' => $perPage, 'page' => request()->query('page', 1), 'min' => $minimumMatchesOverride]),
            self::CACHE_TTL_SECONDS,
            function () use ($filters, $perPage, $minimumMatchesOverride) {
                $minimum = $this->minimumMatchesForScope($filters, $minimumMatchesOverride);

                $query = $this->fullRankingBaseQuery($filters)
                    ->having('total_matches', '>=', $minimum)
                    ->orderBy('total_wins', 'desc')
                    ->orderBy('win_rate', 'desc');

                return $this->paginateFullRanking($query, $perPage, 'total_wins', 'win_rate');
            }
        );
    }

    public function winRateRankingFull(array $filters, int $perPage, ?int $minimumMatchesOverride = null): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheKey('rankings.win_rate', $filters + ['per_page' => $perPage, 'page' => request()->query('page', 1), 'min' => $minimumMatchesOverride]),
            self::CACHE_TTL_SECONDS,
            function () use ($filters, $perPage, $minimumMatchesOverride) {
                $minimum = $this->minimumMatchesForScope($filters, $minimumMatchesOverride);

                $query = $this->fullRankingBaseQuery($filters)
                    ->having('total_matches', '>=', $minimum)
                    ->orderBy('win_rate', 'desc')
                    ->orderBy('total_wins', 'desc');

                return $this->paginateFullRanking($query, $perPage, 'win_rate', 'win_rate');
            }
        );
    }

    public function appearancesRankingFull(array $filters, int $perPage, ?int $minimumMatchesOverride = null): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheKey('rankings.appearances', $filters + ['per_page' => $perPage, 'page' => request()->query('page', 1), 'min' => $minimumMatchesOverride]),
            self::CACHE_TTL_SECONDS,
            function () use ($filters, $perPage, $minimumMatchesOverride) {
                // Ranking de presenças não aplica o piso de 20% por padrão (o próprio
                // critério é a quantidade de jogos); só filtra se `minimum_matches` vier explícito.
                $minimum = $minimumMatchesOverride !== null ? max(0, $minimumMatchesOverride) : 0;

                $query = $this->fullRankingBaseQuery($filters)
                    ->having('total_matches', '>=', $minimum)
                    ->orderBy('total_matches', 'desc');

                return $this->paginateFullRanking($query, $perPage, 'total_matches', null);
            }
        );
    }

    public function goalkeepersRankingFull(array $filters, int $perPage, ?int $minimumMatchesOverride = null): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheKey('rankings.goalkeepers', $filters + ['per_page' => $perPage, 'page' => request()->query('page', 1), 'min' => $minimumMatchesOverride]),
            self::CACHE_TTL_SECONDS,
            function () use ($filters, $perPage, $minimumMatchesOverride) {
                $minimum = $this->minimumMatchesForScope($filters, $minimumMatchesOverride);

                $query = $this->fullRankingBaseQuery($filters)
                    ->whereHas('player', fn ($q) => $q->where('position', 'goleiro'))
                    ->having('total_matches', '>=', $minimum)
                    ->orderBy('avg_goals_conceded_per_match', 'asc')
                    ->orderBy('total_goals_conceded', 'asc');

                return $this->paginateFullRanking($query, $perPage, 'avg_goals_conceded_per_match', 'avg_goals_conceded_per_match');
            }
        );
    }

    /**
     * Melhor (ou pior, via $direction) jogador segundo uma métrica da
     * `fullRankingBaseQuery`, respeitando o mínimo de partidas do escopo.
     * Usado pelos "líderes" do dashboard.
     */
    private function leaderByMetric(
        array $filters,
        int $minimum,
        string $metricColumn,
        string $averageColumn,
        string $direction = 'desc',
        bool $onlyGoalkeepers = false,
        bool $excludeZero = true
    ): ?array {
        $query = $this->fullRankingBaseQuery($filters)->having('total_matches', '>=', $minimum);

        if ($onlyGoalkeepers) {
            $query->whereHas('player', fn ($q) => $q->where('position', 'goleiro'));
        }

        if ($excludeZero) {
            $query->having($metricColumn, '>', 0);
        }

        $item = $query->orderBy($metricColumn, $direction)
            ->orderBy('player_id')
            ->with('player')
            ->first();

        if (! $item) {
            return null;
        }

        return [
            'player' => [
                'id' => $item->player->id,
                'name' => $item->player->name,
                'nickname' => $item->player->nickname,
                'position' => $item->player->position,
            ],
            'matches' => (int) $item->total_matches,
            'value' => (float) $item->{$metricColumn},
            'average_per_match' => (float) $item->{$averageColumn},
        ];
    }

    /**
     * Melhor dupla (jogadores do mesmo time, na mesma pelada) considerando
     * apenas peladas em que ambos jogaram juntos, com mínimo de jogos juntos.
     * "Vitória junta" exige que os dois tenham `result = win` naquela pelada.
     */
    private function pickBestDuoRow($rows): ?array
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $best = $rows->reduce(function ($carry, $row) {
            $rate = $row->matches_together > 0 ? $row->wins_together / $row->matches_together : 0;

            if ($carry === null) {
                return (object) ['row' => $row, 'rate' => $rate];
            }

            if ($rate > $carry->rate || ($rate === $carry->rate && $row->matches_together > $carry->row->matches_together)) {
                return (object) ['row' => $row, 'rate' => $rate];
            }

            return $carry;
        });

        $row = $best->row;
        $playerIds = [(int) $row->player_a_id, (int) $row->player_b_id];
        $players = Player::whereIn('id', $playerIds)->get()->keyBy('id');

        return [
            'players' => collect($playerIds)
                ->map(fn ($id) => $players->get($id))
                ->filter()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'nickname' => $p->nickname])
                ->values()
                ->all(),
            'matches_together' => (int) $row->matches_together,
            'wins_together' => (int) $row->wins_together,
            'win_rate_together' => $row->matches_together > 0
                ? round($row->wins_together / $row->matches_together * 100, 2)
                : 0.0,
        ];
    }

    private function bestDuoOverall(array $filters, int $minimum): ?array
    {
        $rows = DB::table('team_players as tp1')
            ->join('team_players as tp2', function ($join) {
                $join->on('tp1.team_id', '=', 'tp2.team_id')
                    ->whereColumn('tp1.player_id', '<', 'tp2.player_id');
            })
            ->join('teams as t', 't.id', '=', 'tp1.team_id')
            ->join('peladas as p', function ($join) {
                $join->on('p.id', '=', 't.pelada_id')->whereNull('p.deleted_at');
            })
            ->join('match_players as mp1', function ($join) {
                $join->on('mp1.player_id', '=', 'tp1.player_id')->on('mp1.pelada_id', '=', 't.pelada_id');
            })
            ->join('match_players as mp2', function ($join) {
                $join->on('mp2.player_id', '=', 'tp2.player_id')->on('mp2.pelada_id', '=', 't.pelada_id');
            })
            ->tap(fn ($q) => $this->applyDateFilters($q, $filters, 'p.date'))
            ->selectRaw('
                tp1.player_id as player_a_id,
                tp2.player_id as player_b_id,
                COUNT(*) as matches_together,
                SUM(CASE WHEN mp1.result = "win" AND mp2.result = "win" THEN 1 ELSE 0 END) as wins_together
            ')
            ->groupBy('tp1.player_id', 'tp2.player_id')
            ->having('matches_together', '>=', max(1, $minimum))
            ->get();

        return $this->pickBestDuoRow($rows);
    }

    public function bestDuoForPlayer(int $playerId, array $filters, int $minimum = 0): ?array
    {
        $rows = DB::table('team_players as tp1')
            ->join('team_players as tp2', function ($join) use ($playerId) {
                $join->on('tp1.team_id', '=', 'tp2.team_id')
                    ->where('tp1.player_id', '=', $playerId)
                    ->where('tp2.player_id', '!=', $playerId);
            })
            ->join('teams as t', 't.id', '=', 'tp1.team_id')
            ->join('peladas as p', function ($join) {
                $join->on('p.id', '=', 't.pelada_id')->whereNull('p.deleted_at');
            })
            ->join('match_players as mp1', function ($join) {
                $join->on('mp1.player_id', '=', 'tp1.player_id')->on('mp1.pelada_id', '=', 't.pelada_id');
            })
            ->join('match_players as mp2', function ($join) {
                $join->on('mp2.player_id', '=', 'tp2.player_id')->on('mp2.pelada_id', '=', 't.pelada_id');
            })
            ->tap(fn ($q) => $this->applyDateFilters($q, $filters, 'p.date'))
            ->selectRaw('
                tp1.player_id as player_a_id,
                tp2.player_id as player_b_id,
                COUNT(*) as matches_together,
                SUM(CASE WHEN mp1.result = "win" AND mp2.result = "win" THEN 1 ELSE 0 END) as wins_together
            ')
            ->groupBy('tp1.player_id', 'tp2.player_id')
            ->having('matches_together', '>=', max(1, $minimum))
            ->get();

        return $this->pickBestDuoRow($rows);
    }

    public function dashboardOverview(array $filters): array
    {
        return Cache::remember($this->cacheKey('dashboard', $filters), self::CACHE_TTL_SECONDS, function () use ($filters) {
            $minimum = $this->minimumMatchesForScope($filters);

            $totalPeladas = tap(Pelada::query(), fn ($q) => $this->applyDateFilters($q, $filters))->count();
            $totalPlayers = Player::count();

            $totals = MatchPlayer::selectRaw('SUM(goals) as total_goals, SUM(assists) as total_assists, COUNT(*) as total_participations')
                ->whereHas('pelada', fn ($q) => $this->applyDateFilters($q, $filters))
                ->first();

            $totalGoals = (int) ($totals->total_goals ?? 0);
            $totalAssists = (int) ($totals->total_assists ?? 0);
            $totalParticipations = (int) ($totals->total_participations ?? 0);

            $topPeladaByGoals = MatchPlayer::selectRaw('pelada_id, SUM(goals) as pelada_goals')
                ->whereHas('pelada', fn ($q) => $this->applyDateFilters($q, $filters))
                ->groupBy('pelada_id')
                ->having('pelada_goals', '>', 0)
                ->orderBy('pelada_goals', 'desc')
                ->with('pelada')
                ->first();

            return [
                'total_peladas' => $totalPeladas,
                'total_players' => $totalPlayers,
                'total_goals' => $totalGoals,
                'total_assists' => $totalAssists,
                'total_goal_participations' => $totalGoals + $totalAssists,
                'avg_goals_per_pelada' => $totalPeladas > 0 ? round($totalGoals / $totalPeladas, 2) : 0.0,
                'avg_assists_per_pelada' => $totalPeladas > 0 ? round($totalAssists / $totalPeladas, 2) : 0.0,
                'avg_players_per_pelada' => $totalPeladas > 0 ? round($totalParticipations / $totalPeladas, 2) : 0.0,
                'pelada_with_most_goals' => $topPeladaByGoals ? [
                    'pelada_id' => $topPeladaByGoals->pelada_id,
                    'date' => $topPeladaByGoals->pelada?->date,
                    'location' => $topPeladaByGoals->pelada?->location,
                    'total_goals' => (int) $topPeladaByGoals->pelada_goals,
                ] : null,
                'top_scorer' => $this->leaderByMetric($filters, $minimum, 'total_goals', 'avg_goals_per_match'),
                'top_assister' => $this->leaderByMetric($filters, $minimum, 'total_assists', 'avg_assists_per_match'),
                'top_goal_participation' => $this->leaderByMetric($filters, $minimum, 'total_goal_participations', 'avg_goal_participations_per_match'),
                'most_wins' => $this->leaderByMetric($filters, $minimum, 'total_wins', 'win_rate'),
                'best_win_rate' => $this->leaderByMetric($filters, $minimum, 'win_rate', 'win_rate', excludeZero: false),
                'best_goalkeeper' => $this->leaderByMetric($filters, $minimum, 'avg_goals_conceded_per_match', 'avg_goals_conceded_per_match', direction: 'asc', onlyGoalkeepers: true, excludeZero: false),
                'best_duo' => $this->bestDuoOverall($filters, $minimum),
                'minimum_matches_for_leaders' => $minimum,
            ];
        });
    }

    /**
     * Todas as partidas de um jogador (respeitando os filtros), ordenadas
     * cronologicamente (mais antiga primeiro) com a pelada já carregada —
     * base para streaks, forma recente e evolução individual.
     */
    private function orderedMatchesForPlayer(int $playerId, array $filters): Collection
    {
        return MatchPlayer::where('player_id', $playerId)
            ->whereHas('pelada', fn ($q) => $this->applyDateFilters($q, $filters))
            ->with('pelada')
            ->get()
            ->sortBy(fn ($matchPlayer) => $matchPlayer->pelada?->date)
            ->values();
    }

    private function longestStreak(Collection $matchesOrderedAscending, callable $predicate): int
    {
        $longest = 0;
        $current = 0;

        foreach ($matchesOrderedAscending as $matchPlayer) {
            if ($predicate($matchPlayer)) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 0;
            }
        }

        return $longest;
    }

    /**
     * Classifica a tendência de uma sequência de valores (mais antigo -> mais
     * recente) comparando a média da primeira metade com a da segunda metade
     * da janela: alta (>+10%), queda (<-10%) ou estável.
     */
    private function classifyTrend(array $valuesOldestToNewest): string
    {
        $count = count($valuesOldestToNewest);

        if ($count < 2) {
            return 'estavel';
        }

        $half = intdiv($count, 2);
        $firstHalf = array_slice($valuesOldestToNewest, 0, $half);
        $secondHalf = array_slice($valuesOldestToNewest, $count - $half);

        $avgFirst = array_sum($firstHalf) / max(1, count($firstHalf));
        $avgSecond = array_sum($secondHalf) / max(1, count($secondHalf));

        if ($avgFirst == 0 && $avgSecond == 0) {
            return 'estavel';
        }

        $change = $avgFirst > 0 ? ($avgSecond - $avgFirst) / $avgFirst : 1.0;

        if ($change > 0.1) {
            return 'alta';
        }

        if ($change < -0.1) {
            return 'queda';
        }

        return 'estavel';
    }

    /**
     * Posição de um jogador num ranking específico: 1 + quantidade de
     * jogadores elegíveis estritamente melhores naquela métrica (critério
     * "1224" de desempate — jogadores empatados dividem a mesma posição).
     * Retorna `eligible = false` se o jogador não atinge o mínimo de partidas
     * do escopo (ou, no caso de "goalkeepers", não é goleiro).
     *
     * @return array{eligible: bool, position: int|null}
     */
    private function playerRankingPosition(int $playerId, string $key, array $filters): array
    {
        $minimum = $this->minimumMatchesForScope($filters);

        [$metricColumn, $direction, $onlyGoalkeepers] = match ($key) {
            'goals' => ['total_goals', 'desc', false],
            'assists' => ['total_assists', 'desc', false],
            'goal_participations' => ['total_goal_participations', 'desc', false],
            'wins' => ['total_wins', 'desc', false],
            'win_rate' => ['win_rate', 'desc', false],
            'appearances' => ['total_matches', 'desc', false],
            'goalkeepers' => ['avg_goals_conceded_per_match', 'asc', true],
        };

        $base = $this->fullRankingBaseQuery($filters);

        if ($key !== 'appearances') {
            $base->having('total_matches', '>=', $minimum);
        }

        if ($onlyGoalkeepers) {
            $base->whereHas('player', fn ($q) => $q->where('position', 'goleiro'));
        }

        $player = (clone $base)->where('player_id', $playerId)->first();

        if (! $player) {
            return ['eligible' => false, 'position' => null];
        }

        $value = $player->{$metricColumn};
        $better = (clone $base)
            ->where('player_id', '!=', $playerId)
            ->having($metricColumn, $direction === 'desc' ? '>' : '<', $value)
            ->count();

        return ['eligible' => true, 'position' => $better + 1];
    }

    public function playerStatistics(Player $player, array $filters = []): array
    {
        return Cache::remember(
            $this->cacheKey('player.'.$player->id, $filters),
            self::CACHE_TTL_SECONDS,
            function () use ($player, $filters) {
                $matches = $this->orderedMatchesForPlayer($player->id, $filters);

                $totalMatches = $matches->count();
                $totalGoals = (int) $matches->sum('goals');
                $totalAssists = (int) $matches->sum('assists');
                $totalWins = $matches->where('result', 'win')->count();
                $totalLosses = $matches->where('result', 'loss')->count();
                $totalDraws = $matches->where('result', 'draw')->count();
                $matchesScoring = $matches->filter(fn ($m) => $m->goals > 0)->count();
                $matchesAssisting = $matches->filter(fn ($m) => $m->assists > 0)->count();
                $matchesParticipating = $matches->filter(fn ($m) => ($m->goals + $m->assists) > 0)->count();

                $firstMatchDate = $matches->first()?->pelada?->date;
                $totalPeladasSinceStart = $firstMatchDate
                    ? tap(Pelada::where('date', '>=', $firstMatchDate), fn ($q) => $this->applyDateFilters($q, $filters))->count()
                    : 0;

                $recentFive = $matches->slice(-5)->values();

                $rankingKeys = ['goals', 'assists', 'goal_participations', 'wins', 'win_rate', 'appearances'];
                if ($player->position === 'goleiro') {
                    $rankingKeys[] = 'goalkeepers';
                }
                $rankings = collect($rankingKeys)
                    ->mapWithKeys(fn ($key) => [$key => $this->playerRankingPosition($player->id, $key, $filters)])
                    ->all();

                return [
                    'total_matches' => $totalMatches,
                    'total_goals' => $totalGoals,
                    'total_assists' => $totalAssists,
                    'total_goal_participations' => $totalGoals + $totalAssists,
                    'total_wins' => $totalWins,
                    'total_losses' => $totalLosses,
                    'total_draws' => $totalDraws,
                    'win_rate' => $totalMatches > 0 ? round($totalWins / $totalMatches * 100, 2) : 0.0,
                    'avg_goals_per_match' => $totalMatches > 0 ? round($totalGoals / $totalMatches, 2) : 0.0,
                    'avg_assists_per_match' => $totalMatches > 0 ? round($totalAssists / $totalMatches, 2) : 0.0,
                    'avg_goal_participations_per_match' => $totalMatches > 0 ? round(($totalGoals + $totalAssists) / $totalMatches, 2) : 0.0,
                    'pct_matches_scoring' => $totalMatches > 0 ? round($matchesScoring / $totalMatches * 100, 2) : 0.0,
                    'pct_matches_assisting' => $totalMatches > 0 ? round($matchesAssisting / $totalMatches * 100, 2) : 0.0,
                    'pct_matches_participating' => $totalMatches > 0 ? round($matchesParticipating / $totalMatches * 100, 2) : 0.0,
                    'best_goals_in_a_match' => (int) ($matches->max('goals') ?? 0),
                    'best_assists_in_a_match' => (int) ($matches->max('assists') ?? 0),
                    'best_scoring_streak' => $this->longestStreak($matches, fn ($m) => $m->goals > 0),
                    'best_participation_streak' => $this->longestStreak($matches, fn ($m) => ($m->goals + $m->assists) > 0),
                    'best_unbeaten_streak' => $this->longestStreak($matches, fn ($m) => $m->result !== 'loss'),
                    'attendance_rate' => $totalPeladasSinceStart > 0 ? round($totalMatches / $totalPeladasSinceStart * 100, 2) : 0.0,
                    'best_duo' => $this->bestDuoForPlayer($player->id, $filters, 0),
                    'recent_form' => [
                        'matches' => $recentFive->map(fn ($m) => [
                            'pelada_id' => $m->pelada_id,
                            'date' => $m->pelada?->date,
                            'goals' => $m->goals,
                            'assists' => $m->assists,
                            'result' => $m->result,
                        ])->values()->all(),
                        'trend' => $this->classifyTrend($recentFive->map(fn ($m) => $m->goals + $m->assists)->all()),
                    ],
                    'evolution' => $this->evolution('match', $filters, 12, $player->id),
                    'rankings' => $rankings,
                    'goals_conceded' => $player->position === 'goleiro' ? (int) $matches->sum('goals_conceded') : null,
                ];
            }
        );
    }

    public function comparePlayers(array $playerIds, array $filters = []): array
    {
        $players = Player::whereIn('id', $playerIds)->get()->keyBy('id');

        $stats = collect($playerIds)
            ->mapWithKeys(function ($id) use ($players, $filters) {
                $player = $players->get($id);

                return [$id => $player ? $this->playerStatistics($player, $filters) : null];
            })
            ->filter();

        $radarMetrics = ['total_goals', 'total_assists', 'total_goal_participations', 'win_rate', 'avg_goal_participations_per_match'];

        $maxValues = collect($radarMetrics)->mapWithKeys(function ($metric) use ($stats) {
            return [$metric => $stats->max(fn ($s) => $s[$metric] ?? 0) ?: 0];
        });

        return $stats->map(function ($stat, $id) use ($players, $maxValues, $radarMetrics) {
            $player = $players->get($id);

            $radar = collect($radarMetrics)->mapWithKeys(function ($metric) use ($stat, $maxValues) {
                $value = $stat[$metric] ?? 0;
                $max = $maxValues[$metric];

                return [$metric => $max > 0 ? round($value / $max * 100, 2) : 0.0];
            })->all();

            return array_merge([
                'player' => ['id' => $player->id, 'name' => $player->name, 'nickname' => $player->nickname, 'position' => $player->position],
            ], $stat, ['radar' => $radar]);
        })->values()->all();
    }

    private function pickLeaderInMatch(\Illuminate\Support\Collection $matchPlayers, callable $valueFn): ?array
    {
        $eligible = $matchPlayers->filter(fn ($mp) => $valueFn($mp) > 0);

        if ($eligible->isEmpty()) {
            return null;
        }

        $best = $eligible->sort(function ($a, $b) use ($valueFn) {
            return $valueFn($b) <=> $valueFn($a) ?: $a->player->name <=> $b->player->name;
        })->first();

        return [
            'player' => ['id' => $best->player->id, 'name' => $best->player->name, 'nickname' => $best->player->nickname],
            'value' => $valueFn($best),
        ];
    }

    public function matchStatistics(Pelada $pelada): array
    {
        return Cache::remember($this->cacheKey('match', ['pelada_id' => $pelada->id]), self::CACHE_TTL_SECONDS, function () use ($pelada) {
            $base = $this->peladaStatistics($pelada);

            $matchPlayers = MatchPlayer::where('pelada_id', $pelada->id)->with('player')->get();
            $teams = Team::where('pelada_id', $pelada->id)->with('players')->get();
            $matchPlayersByPlayer = $matchPlayers->keyBy('player_id');

            $teamResults = $teams->map(function (Team $team) use ($matchPlayersByPlayer) {
                $teamMatchPlayers = $team->players
                    ->map(fn ($p) => $matchPlayersByPlayer->get($p->id))
                    ->filter()
                    ->values();

                $resultCounts = $teamMatchPlayers->pluck('result')->filter()->countBy();

                return [
                    'team_id' => $team->id,
                    'name' => $team->name,
                    'total_goals' => (int) $teamMatchPlayers->sum('goals'),
                    'result' => $resultCounts->isNotEmpty() ? $resultCounts->sortDesc()->keys()->first() : null,
                ];
            })->values();

            return array_merge($base, [
                'avg_goals_per_player' => $base['total_players'] > 0 ? round($base['total_goals'] / $base['total_players'], 2) : 0.0,
                'top_scorer' => $this->pickLeaderInMatch($matchPlayers, fn ($mp) => $mp->goals),
                'top_assister' => $this->pickLeaderInMatch($matchPlayers, fn ($mp) => $mp->assists),
                'top_goal_participation' => $this->pickLeaderInMatch($matchPlayers, fn ($mp) => $mp->goals + $mp->assists),
                'team_results' => $teamResults,
                'goal_difference' => $teamResults->count() >= 2 ? $teamResults->max('total_goals') - $teamResults->min('total_goals') : null,
            ]);
        });
    }

    /**
     * Série temporal para gráficos de evolução, agrupada por pelada (`match`),
     * mês (`month`) ou ano (`year`). Quando `$playerId` é informado, restringe
     * ao histórico daquele jogador (usado em `players/{player}` e goleiros).
     */
    public function evolution(string $groupBy, array $filters = [], ?int $limit = null, ?int $playerId = null): array
    {
        $groupBy = in_array($groupBy, ['match', 'month', 'year'], true) ? $groupBy : 'match';

        return Cache::remember(
            $this->cacheKey('evolution', $filters + ['group_by' => $groupBy, 'limit' => $limit, 'player_id' => $playerId]),
            self::CACHE_TTL_SECONDS,
            function () use ($groupBy, $filters, $limit, $playerId) {
                $periodExpression = match ($groupBy) {
                    'match' => 'peladas.id',
                    'month' => "DATE_FORMAT(peladas.date, '%Y-%m')",
                    'year' => 'YEAR(peladas.date)',
                };

                $rows = MatchPlayer::query()
                    ->join('peladas', 'peladas.id', '=', 'match_players.pelada_id')
                    ->whereNull('peladas.deleted_at')
                    ->when($playerId, fn ($q) => $q->where('match_players.player_id', $playerId))
                    ->tap(fn ($q) => $this->applyDateFilters($q, $filters, 'peladas.date'))
                    ->selectRaw("
                        {$periodExpression} as period_key,
                        MIN(peladas.date) as period_date,
                        COUNT(DISTINCT peladas.id) as total_peladas,
                        SUM(COALESCE(match_players.goals, 0)) as total_goals,
                        SUM(COALESCE(match_players.assists, 0)) as total_assists,
                        SUM(COALESCE(match_players.goals, 0) + COALESCE(match_players.assists, 0)) as total_goal_participations,
                        COUNT(DISTINCT match_players.player_id) as total_players
                    ")
                    ->groupBy('period_key')
                    ->orderBy('period_key', 'desc')
                    ->when($limit, fn ($q) => $q->limit($limit))
                    ->get();

                return $rows->map(function ($row) use ($groupBy) {
                    $totalPeladas = (int) $row->total_peladas;

                    return [
                        'period' => match ($groupBy) {
                            'match' => $row->period_date,
                            'month' => (string) $row->period_key,
                            'year' => (string) $row->period_key,
                        },
                        'total_peladas' => $totalPeladas,
                        'total_goals' => (int) $row->total_goals,
                        'total_assists' => (int) $row->total_assists,
                        'total_goal_participations' => (int) $row->total_goal_participations,
                        'avg_goals' => $totalPeladas > 0 ? round($row->total_goals / $totalPeladas, 2) : 0.0,
                        'avg_assists' => $totalPeladas > 0 ? round($row->total_assists / $totalPeladas, 2) : 0.0,
                        'total_players' => (int) $row->total_players,
                    ];
                })->sortBy('period')->values()->all();
            }
        );
    }

    public function goalkeepersOverview(array $filters, int $perPage): LengthAwarePaginator
    {
        return Cache::remember(
            $this->cacheKey('goalkeepers.overview', $filters + ['per_page' => $perPage, 'page' => request()->query('page', 1)]),
            self::CACHE_TTL_SECONDS,
            function () use ($filters, $perPage) {
                $minimum = $this->minimumMatchesForScope($filters);

                $paginator = MatchPlayer::selectRaw('
                        player_id,
                        COUNT(*) as total_matches,
                        SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) as total_wins,
                        ROUND(SUM(CASE WHEN result = "win" THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as win_rate,
                        SUM(COALESCE(goals_conceded, 0)) as total_goals_conceded,
                        ROUND(SUM(COALESCE(goals_conceded, 0)) / COUNT(*), 2) as avg_goals_conceded_per_match,
                        MIN(goals_conceded) as best_match_goals_conceded,
                        MAX(goals_conceded) as worst_match_goals_conceded,
                        SUM(COALESCE(goals, 0)) as total_goals,
                        SUM(COALESCE(assists, 0)) as total_assists
                    ')
                    ->whereHas('pelada', fn ($q) => $this->applyDateFilters($q, $filters))
                    ->whereHas('player', fn ($q) => $q->where('position', 'goleiro'))
                    ->groupBy('player_id')
                    ->having('total_matches', '>=', $minimum)
                    ->orderBy('avg_goals_conceded_per_match', 'asc')
                    ->with('player')
                    ->paginate($perPage);

                $paginator->getCollection()->transform(fn ($item) => [
                    'player' => ['id' => $item->player->id, 'name' => $item->player->name, 'nickname' => $item->player->nickname],
                    'matches' => (int) $item->total_matches,
                    'wins' => (int) $item->total_wins,
                    'win_rate' => (float) $item->win_rate,
                    'goals_conceded' => (int) $item->total_goals_conceded,
                    'avg_goals_conceded_per_match' => (float) $item->avg_goals_conceded_per_match,
                    'best_match_goals_conceded' => (int) $item->best_match_goals_conceded,
                    'worst_match_goals_conceded' => (int) $item->worst_match_goals_conceded,
                    'goals' => (int) $item->total_goals,
                    'assists' => (int) $item->total_assists,
                ]);

                return $paginator;
            }
        );
    }

    public function goalkeeperStatistics(Player $player, array $filters = []): array
    {
        $overview = $this->playerStatistics($player, $filters);
        $matches = $this->orderedMatchesForPlayer($player->id, $filters);

        return array_merge($overview, [
            'best_match_goals_conceded' => $matches->isNotEmpty() ? (int) $matches->min('goals_conceded') : null,
            'worst_match_goals_conceded' => $matches->isNotEmpty() ? (int) $matches->max('goals_conceded') : null,
            'evolution_by_period' => $this->evolution('month', $filters, 12, $player->id),
        ]);
    }

    /**
     * Últimos N (5 ou 10) jogos de cada jogador, via ROW_NUMBER() (MySQL 8),
     * numa única query — evita N+1 de buscar o histórico jogador a jogador.
     */
    public function recentForm(int $lastMatches, array $filters = [], int $limit = 10, ?int $minimumMatchesOverride = null): array
    {
        $lastMatches = in_array($lastMatches, [5, 10], true) ? $lastMatches : 5;

        return Cache::remember(
            $this->cacheKey('recent_form', $filters + ['last_matches' => $lastMatches, 'limit' => $limit, 'min' => $minimumMatchesOverride]),
            self::CACHE_TTL_SECONDS,
            function () use ($lastMatches, $filters, $limit, $minimumMatchesOverride) {
                $inner = DB::table('match_players as mp')
                    ->join('peladas as p', function ($join) {
                        $join->on('p.id', '=', 'mp.pelada_id')->whereNull('p.deleted_at');
                    })
                    ->tap(fn ($q) => $this->applyDateFilters($q, $filters, 'p.date'))
                    ->selectRaw('
                        mp.player_id,
                        mp.goals,
                        mp.assists,
                        mp.result,
                        p.date as pelada_date,
                        ROW_NUMBER() OVER (PARTITION BY mp.player_id ORDER BY p.date DESC, mp.id DESC) as rn
                    ');

                $ranked = DB::query()->fromSub($inner, 'ranked')->where('rn', '<=', $lastMatches)->get();

                $eligibilityFloor = $minimumMatchesOverride ?? min(3, $lastMatches);

                $items = collect($ranked)
                    ->groupBy('player_id')
                    ->map(function ($rows) {
                        $rows = $rows->sortBy('pelada_date')->values();
                        $matches = $rows->count();
                        $goals = (int) $rows->sum('goals');
                        $assists = (int) $rows->sum('assists');
                        $wins = $rows->where('result', 'win')->count();

                        return [
                            'matches' => $matches,
                            'goals' => $goals,
                            'assists' => $assists,
                            'goal_participations' => $goals + $assists,
                            'wins' => $wins,
                            'win_rate' => $matches > 0 ? round($wins / $matches * 100, 2) : 0.0,
                            'avg_participation_per_match' => $matches > 0 ? round(($goals + $assists) / $matches, 2) : 0.0,
                            'trend' => $this->classifyTrend($rows->map(fn ($r) => $r->goals + $r->assists)->all()),
                        ];
                    })
                    ->filter(fn ($item) => $item['matches'] >= $eligibilityFloor)
                    ->sortByDesc('avg_participation_per_match')
                    ->take($limit);

                $players = Player::whereIn('id', $items->keys())->get()->keyBy('id');

                return $items->map(function ($item, $playerId) use ($players) {
                    $player = $players->get($playerId);
                    $item['player'] = $player ? [
                        'id' => $player->id,
                        'name' => $player->name,
                        'nickname' => $player->nickname,
                        'position' => $player->position,
                    ] : null;

                    return $item;
                })->values()->all();
            }
        );
    }
}
