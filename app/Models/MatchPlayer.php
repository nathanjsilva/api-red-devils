<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchPlayer extends Model
{
    protected $fillable = [
        'player_id',
        'pelada_id',
        'goals',
        'assists',
        'goals_conceded',
        'is_winner',
        'result',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function pelada()
    {
        return $this->belongsTo(Pelada::class);
    }
}
