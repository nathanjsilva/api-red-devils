<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipOdds extends Model
{ protected $fillable = ['match_id','home_probability','draw_probability','away_probability','home_odd','draw_odd','away_odd','parameters']; protected $casts = ['parameters' => 'array']; }
