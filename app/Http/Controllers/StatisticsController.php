<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Obtém estatísticas de um jogador em uma pelada específica.
     */
    public function playerInPelada($playerId, $peladaId)
    {
        $player = Player::find($playerId);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $pelada = \App\Models\Pelada::find($peladaId);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
        }

        $statistics = MatchPlayer::where('player_id', $playerId)
                                ->where('pelada_id', $peladaId)
                                ->with(['player', 'pelada'])
                                ->first();

        if (!$statistics) {
            return response()->json(['message' => 'Jogador não participou desta pelada.'], 404);
        }

        return response()->json([
            'player' => $player,
            'pelada' => $pelada,
            'statistics' => [
                'goals' => $statistics->goals,
                'assists' => $statistics->assists,
                'goals_conceded' => $statistics->goals_conceded,
                'is_winner' => $statistics->is_winner,
                'goal_participation' => $statistics->goals + $statistics->assists,
            ]
        ]);
    }

    /**
     * Obtém estatísticas totais de um jogador.
     */
    public function playerTotalStatistics($playerId)
    {
        $player = Player::find($playerId);
        if (!$player) {
            return response()->json(['message' => 'Jogador não encontrado.'], 404);
        }

        $statistics = MatchPlayer::where('player_id', $playerId)
                                ->selectRaw('
                                    SUM(goals) as total_goals,
                                    SUM(assists) as total_assists,
                                    SUM(goals_conceded) as total_goals_conceded,
                                    COUNT(*) as total_matches,
                                    SUM(CASE WHEN is_winner = 1 THEN 1 ELSE 0 END) as total_wins,
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
                'win_rate' => $statistics->total_matches > 0 ? round(($statistics->total_wins / $statistics->total_matches) * 100, 2) : 0,
                'avg_goal_participation' => round($statistics->avg_goal_participation ?? 0, 2),
            ]
        ]);
    }

    /**
     * Obtém ranking de vitórias.
     */
    public function winsRanking()
    {
        $ranking = MatchPlayer::selectRaw('
                player_id,
                COUNT(*) as total_matches,
                SUM(CASE WHEN is_winner = 1 THEN 1 ELSE 0 END) as total_wins,
                ROUND((SUM(CASE WHEN is_winner = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as win_rate
            ')
            ->groupBy('player_id')
            ->having('total_matches', '>', 0)
            ->orderBy('total_wins', 'desc')
            ->orderBy('win_rate', 'desc')
            ->with('player')
            ->get();

        return response()->json([
            'ranking_type' => 'Vitórias',
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_wins' => $item->total_wins,
                    'total_matches' => $item->total_matches,
                    'win_rate' => $item->win_rate . '%'
                ];
            })
        ]);
    }

    /**
     * Obtém ranking de gols.
     */
    public function goalsRanking()
    {
        $ranking = MatchPlayer::selectRaw('
                player_id,
                SUM(goals) as total_goals,
                COUNT(*) as total_matches,
                ROUND(AVG(goals), 2) as avg_goals_per_match
            ')
            ->groupBy('player_id')
            ->having('total_goals', '>', 0)
            ->orderBy('total_goals', 'desc')
            ->orderBy('avg_goals_per_match', 'desc')
            ->with('player')
            ->get();

        return response()->json([
            'ranking_type' => 'Gols',
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_goals' => $item->total_goals,
                    'total_matches' => $item->total_matches,
                    'avg_goals_per_match' => $item->avg_goals_per_match
                ];
            })
        ]);
    }

    /**
     * Obtém ranking de assistências.
     */
    public function assistsRanking()
    {
        $ranking = MatchPlayer::selectRaw('
                player_id,
                SUM(assists) as total_assists,
                COUNT(*) as total_matches,
                ROUND(AVG(assists), 2) as avg_assists_per_match
            ')
            ->groupBy('player_id')
            ->having('total_assists', '>', 0)
            ->orderBy('total_assists', 'desc')
            ->orderBy('avg_assists_per_match', 'desc')
            ->with('player')
            ->get();

        return response()->json([
            'ranking_type' => 'Assistências',
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_assists' => $item->total_assists,
                    'total_matches' => $item->total_matches,
                    'avg_assists_per_match' => $item->avg_assists_per_match
                ];
            })
        ]);
    }

    /**
     * Obtém ranking de participação em gols (gols + assistências).
     */
    public function goalParticipationRanking()
    {
        $ranking = MatchPlayer::selectRaw('
                player_id,
                SUM(goals + assists) as total_goal_participation,
                COUNT(*) as total_matches,
                ROUND(AVG(goals + assists), 2) as avg_goal_participation
            ')
            ->groupBy('player_id')
            ->having('total_goal_participation', '>', 0)
            ->orderBy('total_goal_participation', 'desc')
            ->orderBy('avg_goal_participation', 'desc')
            ->with('player')
            ->get();

        return response()->json([
            'ranking_type' => 'Participação em Gols',
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_goal_participation' => $item->total_goal_participation,
                    'total_matches' => $item->total_matches,
                    'avg_goal_participation' => $item->avg_goal_participation
                ];
            })
        ]);
    }

    /**
     * Obtém ranking de goleiros (gols sofridos).
     */
    public function goalkeepersRanking()
    {
        $ranking = MatchPlayer::selectRaw('
                player_id,
                SUM(goals_conceded) as total_goals_conceded,
                COUNT(*) as total_matches,
                ROUND(AVG(goals_conceded), 2) as avg_goals_conceded_per_match
            ')
            ->whereHas('player', function ($query) {
                $query->where('position', 'goleiro');
            })
            ->groupBy('player_id')
            ->having('total_matches', '>', 0)
            ->orderBy('avg_goals_conceded_per_match', 'asc') // Menor média = melhor goleiro
            ->orderBy('total_goals_conceded', 'asc')
            ->with('player')
            ->get();

        return response()->json([
            'ranking_type' => 'Goleiros (Gols Sofridos)',
            'ranking' => $ranking->map(function ($item) {
                return [
                    'player' => $item->player,
                    'total_goals_conceded' => $item->total_goals_conceded,
                    'total_matches' => $item->total_matches,
                    'avg_goals_conceded_per_match' => $item->avg_goals_conceded_per_match
                ];
            })
        ]);
    }

    /**
     * Obtém estatísticas de uma pelada específica.
     */
    public function peladaStatistics($peladaId)
    {
        $pelada = \App\Models\Pelada::find($peladaId);
        if (!$pelada) {
            return response()->json(['message' => 'Pelada não encontrada.'], 404);
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
                        'position' => $player->position
                    ],
                    'statistics' => [
                        'goals' => $matchPlayer->goals,
                        'assists' => $matchPlayer->assists,
                        'is_winner' => $matchPlayer->is_winner,
                        'goal_participation' => $matchPlayer->goals + $matchPlayer->assists
                    ]
                ];

                // Adiciona gols sofridos apenas para goleiros
                if ($player->position === 'goleiro') {
                    $stats['statistics']['goals_conceded'] = $matchPlayer->goals_conceded;
                }

                return $stats;
            });

        // Separa jogadores por posição
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
                'qtd_goleiros' => $pelada->qtd_goleiros
            ],
            'statistics' => [
                'field_players' => $fieldPlayers,
                'goalkeepers' => $goalkeepers,
                'total_players' => $statistics->count(),
                'total_goals' => $statistics->sum('statistics.goals'),
                'total_assists' => $statistics->sum('statistics.assists'),
                'winners_count' => $statistics->where('statistics.is_winner', true)->count()
            ]
        ]);
    }
}

