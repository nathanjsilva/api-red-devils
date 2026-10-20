<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipTieBreaker extends Model { protected $fillable=['championship_id','team_a_id','team_b_id','winner_id','resolved_by','reason']; }
