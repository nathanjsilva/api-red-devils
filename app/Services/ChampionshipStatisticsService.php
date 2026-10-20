<?php
namespace App\Services;
use App\Models\Championship;
class ChampionshipStatisticsService
{
    public function calculate(Championship $championship, ?string $phase=null): array
    {
        $matches=$championship->matches()->where('status','finished')->when($phase && $phase!=='all',fn($q)=>$q->where('phase',$phase))->with(['events','homeTeam','awayTeam','participations'])->get(); $rows=[];
        foreach($championship->players()->with(['player','teams'])->get() as $participant){ $id=$participant->id; $teamId=$participant->teams->first()?->id; $goals=$matches->pluck('events')->flatten()->where('scorer_id',$id)->where('own_goal',false)->count(); $assists=$matches->pluck('events')->flatten()->where('assist_id',$id)->count(); $mvp=$matches->pluck('participations')->flatten()->where('championship_player_id',$id)->where('mvp',true)->count(); $played=$matches->filter(fn($m)=>$m->participations->contains('championship_player_id',$id)); $wins=$draws=$losses=$conceded=$clean=0; foreach($played as $match){$home=$match->home_team_id===$teamId; $for=$home?$match->home_score:$match->away_score; $against=$home?$match->away_score:$match->home_score; if($for>$against)$wins++;elseif($for===$against)$draws++;else$losses++;$conceded+=(int)$against;if($against===0)$clean++;} $rows[]=['participant_id'=>$id,'player'=>$participant->player,'position'=>$participant->player_type,'played'=>$played->count(),'wins'=>$wins,'draws'=>$draws,'losses'=>$losses,'goals'=>$goals,'assists'=>$assists,'goal_participations'=>$goals+$assists,'mvp'=>$mvp,'goals_conceded'=>$participant->player_type==='goalkeeper'?$conceded:0,'clean_sheets'=>$participant->player_type==='goalkeeper'?$clean:0,'goal_average'=>$played->count()?round($goals/$played->count(),2):0,'assist_average'=>$played->count()?round($assists/$played->count(),2):0]; }
        return $rows;
    }
}
