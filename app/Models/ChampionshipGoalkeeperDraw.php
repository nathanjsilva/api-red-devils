<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipGoalkeeperDraw extends Model { protected $fillable=['championship_id','team_id','championship_player_id','draw_number','drawn_by']; public function team(){return $this->belongsTo(ChampionshipTeam::class,'team_id');} public function championshipPlayer(){return $this->belongsTo(ChampionshipPlayer::class);}}
