<?php
namespace App\Services;
use App\Models\Championship;

class ChampionshipStandingsService
{
    public function calculate(Championship $championship): array
    {
        if (in_array($championship->phase, ['playoffs','finished'], true) && $championship->standings_snapshot) return $championship->standings_snapshot;
        $table = $championship->teams()->with(['homeMatches', 'awayMatches'])->get()->map(function ($team) {
            $matches = $team->homeMatches->merge($team->awayMatches)->where('status', 'finished')->where('phase', 'group');
            $row = ['team_id'=>$team->id,'name'=>$team->name,'crest_path'=>$team->crest_path,'points'=>0,'played'=>0,'wins'=>0,'draws'=>0,'losses'=>0,'goals_for'=>0,'goals_against'=>0];
            foreach ($matches as $match) {
                $home = $match->home_team_id === $team->id;
                $for = (int) ($home ? $match->home_score : $match->away_score);
                $against = (int) ($home ? $match->away_score : $match->home_score);
                $row['played']++; $row['goals_for'] += $for; $row['goals_against'] += $against;
                if ($for > $against) { $row['wins']++; $row['points'] += 3; }
                elseif ($for === $against) { $row['draws']++; $row['points']++; }
                else $row['losses']++;
            }
            $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
            $row['win_rate'] = $row['played'] ? round($row['points'] / ($row['played'] * 3) * 100, 2) : 0;
            return $row;
        });
        $matches=$championship->matches()->where('status','finished')->where('phase','group')->get();
        $tieBreakers=$championship->tieBreakers()->get();
        $table=$table->sort(function($a,$b)use($matches,$tieBreakers){foreach(['points','wins','goal_difference','goals_for'] as $field){if($a[$field]!==$b[$field])return $a[$field]<$b[$field]?1:-1;} $pair=$matches->first(fn($m)=>in_array($a['team_id'],[$m->home_team_id,$m->away_team_id],true)&&in_array($b['team_id'],[$m->home_team_id,$m->away_team_id],true)); if($pair){$aHome=$pair->home_team_id===$a['team_id'];$aScore=$aHome?$pair->home_score:$pair->away_score;$bScore=$aHome?$pair->away_score:$pair->home_score;if($aScore!==$bScore)return $aScore<$bScore?1:-1;} $decision=$tieBreakers->first(fn($t)=>in_array($a['team_id'],[$t->team_a_id,$t->team_b_id],true)&&in_array($b['team_id'],[$t->team_a_id,$t->team_b_id],true)); if($decision && $decision->winner_id!==$a['team_id'])return 1; if($decision && $decision->winner_id===$a['team_id'])return -1; return $a['team_id']<=>$b['team_id'];})->values();
        return $table->map(fn ($r, $i) => ['position' => $i + 1, ...$r])->all();
    }
}
