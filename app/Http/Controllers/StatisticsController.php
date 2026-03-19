<?php

namespace App\Http\Controllers;

use App\Models\MatchPlayer;
use App\Models\Pelada;
use App\Models\Player;

class StatisticsController extends Controller
{
    protected function currentYear(): int
    {
        return now()->year;
    }

    protected function totalPeladasInCurrentYear(): int
    {
        return Pelada::whereYear('date', $this->currentYear())->count();
    }

    protected function minimumMatchesForRanking(): int
    {
        $totalPeladas = $this->totalPeladasInCurrentYear();

        if ($totalPeladas === 0) {
            return 0;
        }

        return (int) ceil($totalPeladas * 0.2);
    }

    protected function rankingBaseQuery(string $selectRaw)
    {
        return MatchPlayer::selectRaw($selectRaw)
            ->whereHas('pelada', function ($query) {
                $query->whereYear('date', $this->currentYear());
            });
    }

    public function playerInPelada($playerId, $peladaId)
    {
        $player = Player::find($playerId);
        if (!$player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $statistics = MatchPlayer::where('player_id', $playerId)
            ->where('pelada_id', $peladaId)
            ->with(['player', 'pelada'])
            ->first();

        if (!$statistics) {
            return $this->errorResponse('Jogador não participou desta pelada.', 404);
        }

        return response()->json([
            'player' => $player,
            'pelada' => $pelada,
            'statistics' => [
                'goals' => $statistics->goals,
                'assists' => $statistics->assists,
                'goals_conceded' => $statistics->goals_conceded,
                'is_winner' => $statistics->is_winner,
                'result' => $statistics->result ?? ($statistics->is_winner ? 'win' : 'loss'),
                'goal_participation' => $statistics->goals + $statistics->assists,
            ],
        ]);
    }

    public function playerTotalStatistics($playerId)
    {
        $player = Player::find($playerId);
        if (!$player) {
            return $this->errorResponse('Jogador não encontrado.', 404);
        }

        $statistics = MatchPlayer::where('player_id', $playerId)
            ->selectRaw('
                SUM(goals) as total_goals,
                SUM(assists) as total_assists,
                SUM(goals_conceded) as total_goals_conceded,
                COUNT(*) as total_matches,
                SUM(CASE WHEN result = "win" OR (result IS NULL AND is_winner = 1) THEN 1 ELSE 0 END) as total_wins,
                SUM(CASE WHEN result = "loss" OR (result IS NULL AND is_winner = 0) THEN 1 ELSE 0 END) as total_losses,
                SUM(CASE WHEN result = "draw" THEN 1 ELSE 0 END) as total_draws,
                AVG(goals + assists) as avg_goal_participation
            ')
            ->first();

        return response()->json([
            'player' => $player,
            'total_statistics' => [
                'total_goals' => $statistics->total_goals ?? 0,
                'total_assists' => $statistics->total_assists ?? 0,
                'total_goals_conceded' => $statistics->total_goals_conceded ?? 0,
                'total_matches' => $statistics->total_matches ?? 0,
                'total_wins' => $statistics->total_wins ?? 0,
                'total_losses' => $statistics->total_losses ?? 0,
                'total_draws' => $statistics->total_draws ?? 0,
                'win_rate' => $statistics->total_matches > 0 ? round(($statistics->total_wins / $statistics->total_matches) * 100, 2) : 0,
                'avg_goal_participation' => round($statistics->avg_goal_participation ?? 0, 2),
            ],
        ]);
    }

    public function playersOverview()
    {
        $currentYear = $this->currentYear();
        $totalPeladas = $this->totalPeladasInCurrentYear();
        $minimumMatches = $this->minimumMatchesForRanking();

        $groupedStatistics = $this->rankingBaseQuery('
                player_id,
                COUNT(*) as total_matches,
                SUM(goals) as total_goals,
                SUM(assists) as total_assists,
                SUM(goals_conceded) as total_goals_conceded,
                SUM(CASE WHEN result = "win" OR (result IS NULL AND is_winner = 1) THEN 1 ELSE 0 END) as total_wins,
                ROUND(AVG(goals + assists), 2) as avg_goal_participation,
                ROUND(AVG(goals), 2) as avg_goals_per_match,
                ROUND(AVG(assists), 2) as avg_assists_per_match
            ')
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        $players = Player::orderBy('name')->get()->map(function ($player) use ($groupedStatistics, $minimumMatches) {
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

        return response()->json([
            'reference_year' => $currentYear,
            'total_peladas_in_year' => $totalPeladas,
            'minimum_matches_for_ranking' => $minimumMatches,
            'players' => $players,
        ]);
    }

    public function winsRanking()
    {
        $currentYear = $this->currentYear();
        $minimumMatches = $this->minimumMatchesForRanking();

        $ranking = $this->rankingBaseQuery('
                player_id,
                COUNT(*) as total_matches,
                SUM(CASE WHEN result = "win" OR (result IS NULL AND is_winner = 1) THEN 1 ELSE 0 END) as total_wins,
                SUM(CASE WHEN result = "loss" OR (result IS NULL AND is_winner = 0) THEN 1 ELSE 0 END) as total_losses,
                SUM(CASE WHEN result = "draw" THEN 1 ELSE 0 END) as total_draws,
                ROUND((SUM(CASE WHEN result = "win" OR (result IS NULL AND is_winner = 1) THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as win_rate
            ')
            ->groupBy('player_id')
            ->having('total_matches', '>=', $minimumMatches)
            ->orderBy('total_wins', 'desc')
            ->orderBy('win_rate', 'desc')
            ->with('player')
            ->get();

        return response()->json([
            'ranking_type' => 'Vitórias',
            'reference_year' => $currentYear,
            'minimum_matches_for_ranking' => $minimumMatches,
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_wins' => $item->total_wins,
                    'total_losses' => $item->total_losses,
                    'total_draws' => $item->total_draws,
                    'total_matches' => $item->total_matches,
                    'win_rate' => $item->win_rate . '%',
                ];
            }),
        ]);
    }

    public function goalsRanking()
    {
        $currentYear = $this->currentYear();
        $minimumMatches = $this->minimumMatchesForRanking();

        $ranking = $this->rankingBaseQuery('
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
            ->get();

        return response()->json([
            'ranking_type' => 'Gols',
            'reference_year' => $currentYear,
            'minimum_matches_for_ranking' => $minimumMatches,
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_goals' => $item->total_goals,
                    'total_matches' => $item->total_matches,
                    'avg_goals_per_match' => $item->avg_goals_per_match,
                ];
            }),
        ]);
    }

    public function assistsRanking()
    {
        $currentYear = $this->currentYear();
        $minimumMatches = $this->minimumMatchesForRanking();

        $ranking = $this->rankingBaseQuery('
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
            ->get();

        return response()->json([
            'ranking_type' => 'Assistências',
            'reference_year' => $currentYear,
            'minimum_matches_for_ranking' => $minimumMatches,
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_assists' => $item->total_assists,
                    'total_matches' => $item->total_matches,
                    'avg_assists_per_match' => $item->avg_assists_per_match,
                ];
            }),
        ]);
    }

    public function goalParticipationRanking()
    {
        $currentYear = $this->currentYear();
        $minimumMatches = $this->minimumMatchesForRanking();

        $ranking = $this->rankingBaseQuery('
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
            ->get();

        return response()->json([
            'ranking_type' => 'Participação em Gols',
            'reference_year' => $currentYear,
            'minimum_matches_for_ranking' => $minimumMatches,
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_goal_participation' => $item->total_goal_participation,
                    'total_matches' => $item->total_matches,
                    'avg_goal_participation' => $item->avg_goal_participation,
                ];
            }),
        ]);
    }

    public function goalkeepersRanking()
    {
        $currentYear = $this->currentYear();
        $minimumMatches = $this->minimumMatchesForRanking();

        $ranking = $this->rankingBaseQuery('
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
            ->get();

        return response()->json([
            'ranking_type' => 'Goleiros (Gols Sofridos)',
            'reference_year' => $currentYear,
            'minimum_matches_for_ranking' => $minimumMatches,
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_goals_conceded' => $item->total_goals_conceded,
                    'total_matches' => $item->total_matches,
                    'avg_goals_conceded_per_match' => $item->avg_goals_conceded_per_match,
                ];
            }),
        ]);
    }

    public function peladaStatistics($peladaId)
    {
        $pelada = Pelada::find($peladaId);
        if (!$pelada) {
            return $this->errorResponse('Pelada não encontrada.', 404);
        }

        $statistics = MatchPlayer::where('pelada_id', $peladaId)
            ->with('player')
            ->get()
            ->map(function ($matchPlayer) {
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
                        'is_winner' => $matchPlayer->is_winner,
                        'result' => $matchPlayer->result ?? ($matchPlayer->is_winner ? 'win' : 'loss'),
                        'goal_participation' => $matchPlayer->goals + $matchPlayer->assists,
                    ],
                ];

                if ($player->position === 'goleiro') {
                    $stats['statistics']['goals_conceded'] = $matchPlayer->goals_conceded;
                }

                return $stats;
            });

        $fieldPlayers = $statistics->filter(function ($stat) {
            return $stat['player']['position'] === 'linha';
        })->values();

        $goalkeepers = $statistics->filter(function ($stat) {
            return $stat['player']['position'] === 'goleiro';
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
            'statistics' => [
                'field_players' => $fieldPlayers,
                'goalkeepers' => $goalkeepers,
                'total_players' => $statistics->count(),
                'total_goals' => $statistics->sum('statistics.goals'),
                'total_assists' => $statistics->sum('statistics.assists'),
                'winners_count' => $statistics->filter(function ($stat) {
                    return $stat['statistics']['result'] === 'win'
                        || ($stat['statistics']['result'] === null && $stat['statistics']['is_winner'] === true);
                })->count(),
                'draws_count' => $statistics->filter(function ($stat) {
                    return $stat['statistics']['result'] === 'draw';
                })->count(),
            ],
        ]);
    }
}
