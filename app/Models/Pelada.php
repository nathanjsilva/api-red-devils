<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pelada extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'location',
        'qtd_times',
        'qtd_jogadores_por_time',
        'qtd_goleiros'
    ];

    public function matchPlayers()
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_players')
                    ->withPivot('goals', 'assists', 'is_winner', 'result', 'goals_conceded')
                    ->withTimestamps();
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
