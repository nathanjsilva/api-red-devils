<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pelada extends Model
{
    protected $fillable = ['date'];

    public function matchPlayers()
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_players')
                    ->withPivot('goals', 'assists', 'is_winner', 'goals_conceded')
                    ->withTimestamps();
}

}
