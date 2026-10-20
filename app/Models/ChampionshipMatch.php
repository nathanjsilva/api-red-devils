<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipMatch extends Model
{
    protected $fillable = ['championship_id','home_team_id','away_team_id','phase','round','scheduled_at','status','home_score','away_score','home_penalty_score','away_penalty_score','qualified_team_id'];
    protected $casts = ['scheduled_at' => 'datetime'];
    public function homeTeam() { return $this->belongsTo(ChampionshipTeam::class, 'home_team_id'); }
    public function awayTeam() { return $this->belongsTo(ChampionshipTeam::class, 'away_team_id'); }
    public function events() { return $this->hasMany(ChampionshipMatchEvent::class, 'match_id'); }
    public function participations() { return $this->hasMany(ChampionshipMatchParticipation::class, 'match_id'); }
    public function odds() { return $this->hasOne(ChampionshipOdds::class, 'match_id'); }
    public function championship() { return $this->belongsTo(Championship::class); }
}
