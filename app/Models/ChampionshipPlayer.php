<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipPlayer extends Model
{
    protected $fillable = ['championship_id', 'player_id', 'player_type', 'stone_group', 'stone_level', 'sport_position'];
    protected $casts = ['stone_level' => 'float'];
    public function player() { return $this->belongsTo(Player::class); }
    public function championship() { return $this->belongsTo(Championship::class); }
    public function teams() { return $this->belongsToMany(ChampionshipTeam::class, 'championship_team_players'); }
}
