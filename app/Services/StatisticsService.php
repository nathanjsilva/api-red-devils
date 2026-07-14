<?php

namespace App\Services;

use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StatisticsService
{
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

    public function goalkeepersRanking(int $perPage): LengthAwarePaginator
    {
        $minimumMatches = $this->minimumMatchesForRanking();

        return $this->rankingBaseQuery('
                player_id,
                SUM(goals_conceded) as total_goals_conceded,
                COUNT(*) as total_matches,
                ROUND(AVG(goals_conceded), 2) as avg_goals_conceded_per_match
            ')
            ->whereHas('player', function ($query) {
                $query->where('position', 'goleiro');
            })
            ->groupBy('player_id')
            ->having('total_matches', '>=', $minimumMatches)
            ->orderBy('avg_goals_conceded_per_match', 'asc')
            ->orderBy('total_goals_conceded', 'asc')
            ->with('player')
            ->paginate($perPage)
            ->through(fn (MatchPlayer $item) => [
                'player' => $item->player,
                'total_goals_conceded' => $item->total_goals_conceded,
                'total_matches' => $item->total_matches,
                'avg_goals_conceded_per_match' => $item->avg_goals_conceded_per_match,
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
}
