<?php

namespace App\Services;

use App\Models\Championship;
use App\Models\ChampionshipMatch;
use App\Models\ChampionshipOdds;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChampionshipOddsService
{
    private const DEFAULT_GOALS_PER_TEAM = 1.25;

    private const PRIOR_MATCHES = 3;

    private const GOAL_LIMIT = 8;

    public function calculate(ChampionshipMatch $match): ChampionshipOdds
    {
        return DB::transaction(function () use ($match) {
            $lockedMatch = ChampionshipMatch::query()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->with(['homeTeam.players', 'awayTeam.players'])
                ->firstOrFail();

            abort_if($lockedMatch->status !== 'scheduled', 422, 'Odds só podem ser calculadas para uma partida agendada.');

            $history = $this->finishedMatches($lockedMatch->championship_id);

            return $this->persistEstimate($lockedMatch, $history);
        });
    }

    public function calculateUpcoming(Championship $championship): int
    {
        return DB::transaction(function () use ($championship) {
            $matches = $championship->matches()
                ->where('status', 'scheduled')
                ->with(['homeTeam.players', 'awayTeam.players'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            abort_if($matches->isEmpty(), 422, 'Não há partidas agendadas para calcular odds.');

            $history = $this->finishedMatches($championship->id);
            $versions = ChampionshipOdds::query()
                ->whereIn('match_id', $matches->pluck('id'))
                ->selectRaw('match_id, MAX(calculation_version) as version')
                ->groupBy('match_id')
                ->pluck('version', 'match_id');
            $calculated = 0;

            foreach ($matches as $match) {
                $this->persistEstimate($match, $history, (int) ($versions[$match->id] ?? 0) + 1);
                $calculated++;
            }

            return $calculated;
        });
    }

    private function finishedMatches(int $championshipId): Collection
    {
        return ChampionshipMatch::query()
            ->where('championship_id', $championshipId)
            ->where('status', 'finished')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderBy('id')
            ->with(['events', 'participations'])
            ->get(['id', 'home_team_id', 'away_team_id', 'phase', 'home_score', 'away_score']);
    }

    private function persistEstimate(ChampionshipMatch $match, Collection $history, ?int $version = null): ChampionshipOdds
    {
        $leagueGoals = $history->sum(fn (ChampionshipMatch $pastMatch) => $pastMatch->home_score + $pastMatch->away_score);
        $leagueAverage = $history->isEmpty()
            ? self::DEFAULT_GOALS_PER_TEAM
            : max(0.35, $leagueGoals / ($history->count() * 2));

        $homeStats = $this->teamStats($match->home_team_id, $history);
        $awayStats = $this->teamStats($match->away_team_id, $history);
        $headToHead = $this->headToHeadStats($match, $history);
        $homeRoster = $this->rosterStrength($match->homeTeam->players, $history, $match->home_team_id);
        $awayRoster = $this->rosterStrength($match->awayTeam->players, $history, $match->away_team_id);

        $homeAttack = $this->smoothedRate($homeStats['goals_for'], $homeStats['matches'], $leagueAverage);
        $awayAttack = $this->smoothedRate($awayStats['goals_for'], $awayStats['matches'], $leagueAverage);

        if ($headToHead['matches'] > 0) {
            $homeHeadToHeadRate = $this->smoothedRate($headToHead['home_goals'], $headToHead['matches'], $leagueAverage, 0.5);
            $awayHeadToHeadRate = $this->smoothedRate($headToHead['away_goals'], $headToHead['matches'], $leagueAverage, 0.5);
            $homeAttack = (($homeAttack * self::PRIOR_MATCHES) + ($homeHeadToHeadRate * 0.5)) / (self::PRIOR_MATCHES + 0.5);
            $awayAttack = (($awayAttack * self::PRIOR_MATCHES) + ($awayHeadToHeadRate * 0.5)) / (self::PRIOR_MATCHES + 0.5);
        }

        $homeDefense = $this->smoothedRate($homeStats['goals_against'], $homeStats['matches'], $leagueAverage);
        $awayDefense = $this->smoothedRate($awayStats['goals_against'], $awayStats['matches'], $leagueAverage);

        // There is no measured home advantage in this competition, so both teams use the same baseline.
        $lambdaHome = $leagueAverage
            * ($homeAttack / $leagueAverage)
            * ($awayDefense / $leagueAverage)
            * $this->fieldQualityFactor($homeRoster['field_average'])
            * $homeRoster['field_form_factor']
            * $awayRoster['goalkeeper_form_factor'];
        $lambdaAway = $leagueAverage
            * ($awayAttack / $leagueAverage)
            * ($homeDefense / $leagueAverage)
            * $this->goalkeeperDefenseFactor($homeRoster['goalkeeper_quality'])
            * $homeRoster['goalkeeper_form_factor']
            * $awayRoster['field_form_factor'];

        // Team points share provides a small outcome-based adjustment when score history exists.
        $outcomeDifference = $homeStats['points_share'] - $awayStats['points_share'];
        $outcomeAdjustment = min(1.15, max(0.85, 1 + ($outcomeDifference * 0.12)));
        $lambdaHome *= $this->goalkeeperDefenseFactor($awayRoster['goalkeeper_quality']) * $outcomeAdjustment;
        $lambdaAway *= $this->fieldQualityFactor($awayRoster['field_average']) / $outcomeAdjustment;
        $lambdaHome = min(4.0, max(0.15, $lambdaHome));
        $lambdaAway = min(4.0, max(0.15, $lambdaAway));

        [$homeProbability, $drawProbability, $awayProbability] = $this->outcomeProbabilities($lambdaHome, $lambdaAway);
        $version ??= (int) ChampionshipOdds::query()->where('match_id', $match->id)->max('calculation_version') + 1;
        $resultSignature = $this->resultsSignature($history);
        $rosterSignature = $this->rosterSignature($match);

        return ChampionshipOdds::create([
            'match_id' => $match->id,
            'calculation_version' => $version,
            'home_probability' => $homeProbability,
            'draw_probability' => $drawProbability,
            'away_probability' => $awayProbability,
            'home_odd' => round(1 / $homeProbability, 3),
            'draw_odd' => round(1 / $drawProbability, 3),
            'away_odd' => round(1 / $awayProbability, 3),
            'parameters' => [
                'model' => 'poisson',
                'method_version' => 3,
                'home_strength' => round($homeRoster['strength'], 3),
                'away_strength' => round($awayRoster['strength'], 3),
                'roster_form_factor' => [
                    'home_field' => round($homeRoster['field_form_factor'], 4),
                    'home_goalkeeper' => round($homeRoster['goalkeeper_form_factor'], 4),
                    'away_field' => round($awayRoster['field_form_factor'], 4),
                    'away_goalkeeper' => round($awayRoster['goalkeeper_form_factor'], 4),
                ],
                'outcome_adjustment' => round($outcomeAdjustment, 4),
                'field_quality' => [
                    'home' => round($homeRoster['field_average'], 3),
                    'away' => round($awayRoster['field_average'], 3),
                ],
                'goalkeeper_quality' => [
                    'home' => round($homeRoster['goalkeeper_quality'], 3),
                    'away' => round($awayRoster['goalkeeper_quality'], 3),
                ],
                'league_goals_per_team' => round($leagueAverage, 3),
                'lambda_home' => round($lambdaHome, 4),
                'lambda_away' => round($lambdaAway, 4),
                'sample_matches' => [
                    'league' => $history->count(),
                    'home_team' => $homeStats['matches'],
                    'away_team' => $awayStats['matches'],
                    'head_to_head' => $headToHead['matches'],
                    'home_field_players' => $homeRoster['field_players_with_history'],
                    'away_field_players' => $awayRoster['field_players_with_history'],
                    'home_goalkeepers' => $homeRoster['goalkeepers_with_history'],
                    'away_goalkeepers' => $awayRoster['goalkeepers_with_history'],
                ],
                'preliminary' => $homeStats['matches'] < 3 || $awayStats['matches'] < 3,
                'goal_limit' => self::GOAL_LIMIT,
                'no_home_advantage' => true,
                'results_signature' => $resultSignature,
                'roster_signature' => $rosterSignature,
            ],
        ]);
    }

    private function teamStats(int $teamId, Collection $history): array
    {
        $matches = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;
        $points = 0;

        foreach ($history as $pastMatch) {
            $homeScore = (int) $pastMatch->home_score;
            $awayScore = (int) $pastMatch->away_score;

            if ((int) $pastMatch->home_team_id === $teamId) {
                $matches++;
                $goalsFor += $homeScore;
                $goalsAgainst += $awayScore;
                $points += $homeScore > $awayScore ? 3 : ($homeScore === $awayScore ? 1 : 0);
            } elseif ((int) $pastMatch->away_team_id === $teamId) {
                $matches++;
                $goalsFor += $awayScore;
                $goalsAgainst += $homeScore;
                $points += $awayScore > $homeScore ? 3 : ($awayScore === $homeScore ? 1 : 0);
            }
        }

        return [
            'matches' => $matches,
            'goals_for' => $goalsFor,
            'goals_against' => $goalsAgainst,
            'points_share' => $matches > 0 ? $points / ($matches * 3) : 0.5,
        ];
    }

    private function headToHeadStats(ChampionshipMatch $match, Collection $history): array
    {
        $matches = 0;
        $homeGoals = 0;
        $awayGoals = 0;

        foreach ($history as $pastMatch) {
            $sameOrder = (int) $pastMatch->home_team_id === (int) $match->home_team_id
                && (int) $pastMatch->away_team_id === (int) $match->away_team_id;
            $reverseOrder = (int) $pastMatch->home_team_id === (int) $match->away_team_id
                && (int) $pastMatch->away_team_id === (int) $match->home_team_id;

            if (! $sameOrder && ! $reverseOrder) {
                continue;
            }

            $matches++;
            $homeGoals += (int) ($sameOrder ? $pastMatch->home_score : $pastMatch->away_score);
            $awayGoals += (int) ($sameOrder ? $pastMatch->away_score : $pastMatch->home_score);
        }

        return ['matches' => $matches, 'home_goals' => $homeGoals, 'away_goals' => $awayGoals];
    }

    private function smoothedRate(int $goals, int $matches, float $leagueAverage, float $priorMatches = self::PRIOR_MATCHES): float
    {
        return ($goals + ($priorMatches * $leagueAverage)) / ($matches + $priorMatches);
    }

    private function rosterStrength(Collection $players, Collection $history, int $teamId): array
    {
        $fieldPlayers = $players->where('player_type', 'field');
        $goalkeepers = $players->where('player_type', 'goalkeeper');
        $fieldRatings = $fieldPlayers->map(fn ($player) => 6 - (float) ($player->stone_level ?? $player->stone_group));
        $fieldAverage = $fieldRatings->isEmpty() ? 3.0 : $fieldRatings->avg();
        $goalkeeperQuality = $goalkeepers->isEmpty()
            ? 3.0
            : 6 - (float) $goalkeepers->first()->stone_group;
        $fieldForm = $fieldPlayers->map(fn ($player) => $this->playerForm($player->id, $player->player_type, $history, $teamId));
        $goalkeeperForm = $goalkeepers->map(fn ($player) => $this->playerForm($player->id, $player->player_type, $history, $teamId));
        $fieldFormSamples = $fieldForm->filter(fn (array $form) => $form['appearances'] > 0);
        $goalkeeperFormSamples = $goalkeeperForm->filter(fn (array $form) => $form['appearances'] > 0);
        $fieldFormFactor = $fieldFormSamples->isEmpty()
            ? 1.0
            : exp(min(0.2, max(-0.2, $fieldFormSamples->avg('impact'))));
        $goalkeeperFormFactor = $goalkeeperFormSamples->isEmpty()
            ? 1.0
            : exp(min(0.2, max(-0.2, $goalkeeperFormSamples->avg('impact'))));

        return [
            'field_average' => $fieldAverage,
            'goalkeeper_quality' => $goalkeeperQuality,
            'strength' => ($fieldAverage * 5 + $goalkeeperQuality) / 6,
            'field_form_factor' => $fieldFormFactor,
            'goalkeeper_form_factor' => $goalkeeperFormFactor,
            'field_players_with_history' => $fieldFormSamples->count(),
            'goalkeepers_with_history' => $goalkeeperFormSamples->count(),
        ];
    }

    private function playerForm(int $participantId, string $playerType, Collection $history, int $teamId): array
    {
        $appearances = 0;
        $wins = 0;
        $draws = 0;
        $goals = 0;
        $assists = 0;
        $mvps = 0;
        $goalsConceded = 0;
        $cleanSheets = 0;

        foreach ($history as $pastMatch) {
            $participation = $pastMatch->participations->firstWhere('championship_player_id', $participantId);
            if (! $participation) {
                continue;
            }

            $isHome = (int) $pastMatch->home_team_id === $teamId;
            $isAway = (int) $pastMatch->away_team_id === $teamId;
            if (! $isHome && ! $isAway) {
                continue;
            }

            $appearances++;
            $for = (int) ($isHome ? $pastMatch->home_score : $pastMatch->away_score);
            $against = (int) ($isHome ? $pastMatch->away_score : $pastMatch->home_score);
            $wins += $for > $against ? 1 : 0;
            $draws += $for === $against ? 1 : 0;
            $mvps += $participation->mvp ? 1 : 0;
            $goals += $pastMatch->events->where('scorer_id', $participantId)->where('own_goal', false)->count();
            $assists += $pastMatch->events->where('assist_id', $participantId)->count();
            if ($playerType === 'goalkeeper') {
                $goalsConceded += $against;
                $cleanSheets += $against === 0 ? 1 : 0;
            }
        }

        if ($appearances === 0) {
            return ['appearances' => 0, 'impact' => 0.0];
        }

        $pointsShare = ($wins * 3 + $draws) / ($appearances * 3);
        $impact = (($pointsShare - 0.5) * ($playerType === 'goalkeeper' ? 0.15 : 0.2))
            + ($playerType === 'goalkeeper'
                ? (($cleanSheets / $appearances) * 0.06) - (($goalsConceded / $appearances) * 0.025)
                : (($goals / $appearances) * 0.08) + (($assists / $appearances) * 0.06) + (($mvps / $appearances) * 0.08));

        return ['appearances' => $appearances, 'impact' => min(0.2, max(-0.2, $impact))];
    }

    private function fieldQualityFactor(float $quality): float
    {
        return exp(($quality - 3.0) * 0.035);
    }

    private function goalkeeperDefenseFactor(float $quality): float
    {
        return exp((3.0 - $quality) * 0.07);
    }

    private function outcomeProbabilities(float $lambdaHome, float $lambdaAway): array
    {
        $poisson = fn (float $lambda, int $goals): float => exp(-$lambda) * ($lambda ** $goals) / $this->factorial($goals);
        $home = 0.0;
        $draw = 0.0;
        $away = 0.0;

        for ($homeGoals = 0; $homeGoals <= self::GOAL_LIMIT; $homeGoals++) {
            for ($awayGoals = 0; $awayGoals <= self::GOAL_LIMIT; $awayGoals++) {
                $probability = $poisson($lambdaHome, $homeGoals) * $poisson($lambdaAway, $awayGoals);
                if ($homeGoals > $awayGoals) {
                    $home += $probability;
                } elseif ($homeGoals === $awayGoals) {
                    $draw += $probability;
                } else {
                    $away += $probability;
                }
            }
        }

        $total = $home + $draw + $away;
        $home = round($home / $total, 4);
        $draw = round($draw / $total, 4);
        $away = round(1 - $home - $draw, 4);

        return [$home, $draw, $away];
    }

    private function factorial(int $number): int
    {
        $value = 1;
        for ($factor = 2; $factor <= $number; $factor++) {
            $value *= $factor;
        }

        return $value;
    }

    private function resultsSignature(Collection $history): string
    {
        return $history->map(fn (ChampionshipMatch $pastMatch) => implode(':', [
            $pastMatch->id,
            $pastMatch->home_team_id,
            $pastMatch->away_team_id,
            $pastMatch->home_score,
            $pastMatch->away_score,
        ]))->implode(';');
    }

    private function rosterSignature(ChampionshipMatch $match): string
    {
        $teamSnapshot = function ($team): string {
            $players = $team->players
                ->sortBy('id')
                ->map(fn ($player) => implode(':', [
                    $player->id,
                    $player->player_type,
                    $player->stone_group,
                    $player->stone_level === null ? '-' : number_format((float) $player->stone_level, 1, '.', ''),
                ]))
                ->implode(',');

            return $team->id.'['.$players.']';
        };

        return $teamSnapshot($match->homeTeam).'|'.$teamSnapshot($match->awayTeam);
    }
}
