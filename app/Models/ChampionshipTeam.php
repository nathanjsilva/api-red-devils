<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipTeam extends Model
{
    protected $fillable = ['championship_id', 'name', 'crest_path'];
    protected $appends = ['crest_url'];
    public function getCrestUrlAttribute(): ?string { return $this->crest_path ? asset('storage/'.$this->crest_path) : null; }
    public function championship() { return $this->belongsTo(Championship::class); }
    public function players() { return $this->belongsToMany(ChampionshipPlayer::class, 'championship_team_players'); }
    public function homeMatches() { return $this->hasMany(ChampionshipMatch::class, 'home_team_id'); }
    public function awayMatches() { return $this->hasMany(ChampionshipMatch::class, 'away_team_id'); }
}
