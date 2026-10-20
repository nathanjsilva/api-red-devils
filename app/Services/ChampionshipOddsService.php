<?php
namespace App\Services;
use App\Models\ChampionshipMatch;
use App\Models\ChampionshipOdds;

class ChampionshipOddsService
{
    public function calculate(ChampionshipMatch $match): ChampionshipOdds
    {
        $score = function ($team) { return max(1, $team->players->sum(fn ($p) => 6 - $p->stone_group)); };
        $match->load(['homeTeam.players', 'awayTeam.players']);
        $home = $score($match->homeTeam); $away = $score($match->awayTeam); $average=30;
        $lambdaHome=max(0.15,1.35+($home-$average)/25); $lambdaAway=max(0.15,1.15+($away-$average)/25);
        $factorial=function(int $n):int{$value=1;for($i=2;$i<=$n;$i++)$value*=$i;return $value;};
        $poisson=function(float $lambda,int $goals)use($factorial):float{return exp(-$lambda)*($lambda**$goals)/$factorial($goals);};
        $homeProb=$draw=$awayProb=0.0; for($h=0;$h<=8;$h++){for($a=0;$a<=8;$a++){ $p=$poisson($lambdaHome,$h)*$poisson($lambdaAway,$a); if($h>$a)$homeProb+=$p;elseif($h===$a)$draw+=$p;else$awayProb+=$p; }}
        $total=$homeProb+$draw+$awayProb; $homeProb/=$total; $draw/=$total; $awayProb/=$total;
        return ChampionshipOdds::updateOrCreate(['match_id' => $match->id], [
            'home_probability' => round($homeProb, 4), 'draw_probability' => round($draw, 4), 'away_probability' => round($awayProb, 4),
            'home_odd' => round(1 / $homeProb, 3), 'draw_odd' => round(1 / $draw, 3), 'away_odd' => round(1 / $awayProb, 3),
            'parameters' => ['model' => 'poisson', 'home_strength' => $home, 'away_strength' => $away, 'lambda_home' => $lambdaHome, 'lambda_away' => $lambdaAway, 'goal_limit' => 8, 'experimental' => true],
        ]);
    }
}
