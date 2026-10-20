<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipAwardCategory extends Model { protected $fillable=['championship_id','name','description','type','goalkeepers_only','published','voting_open']; protected $casts=['goalkeepers_only'=>'boolean','published'=>'boolean','voting_open'=>'boolean']; public function votes(){return $this->hasMany(ChampionshipVote::class);} public function results(){return $this->hasMany(ChampionshipAwardResult::class);} }
