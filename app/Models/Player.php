<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'nickname',
        'position',
    ];

    public function matchPlayers()
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function peladas()
    {
        return $this->belongsToMany(Pelada::class, 'match_players')
            ->withPivot('goals', 'assists', 'result', 'goals_conceded')
            ->withTimestamps();
    }
}
