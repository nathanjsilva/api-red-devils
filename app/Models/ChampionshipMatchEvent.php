<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipMatchEvent extends Model
{ protected $fillable = ['match_id','scorer_id','assist_id','team_id','own_goal','minute']; protected $casts = ['own_goal' => 'boolean']; public function match(){return $this->belongsTo(ChampionshipMatch::class,'match_id');} }
