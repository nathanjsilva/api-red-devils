<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipMatchParticipation extends Model
{ protected $fillable = ['match_id','championship_player_id','mvp']; protected $casts = ['mvp' => 'boolean']; }
