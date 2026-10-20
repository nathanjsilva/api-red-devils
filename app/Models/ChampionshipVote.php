<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipVote extends Model { protected $fillable=['championship_award_category_id','voter_id','candidate_id']; }
