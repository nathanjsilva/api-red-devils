<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pelada extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'date',
        'division',
        'location',
        'qtd_times',
        'qtd_jogadores_por_time',
        'qtd_goleiros',
    ];

    public function matchPlayers()
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_players')
            ->withPivot('goals', 'assists', 'result', 'goals_conceded')
            ->withTimestamps();
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
