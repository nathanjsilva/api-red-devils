<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipAwardResult extends Model { protected $fillable=['championship_award_category_id','winner_id','votes','percentage']; public function winner(){return $this->belongsTo(ChampionshipPlayer::class,'winner_id');} }
