<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'pelada_id',
        'name',
    ];

    public function pelada()
    {
        return $this->belongsTo(Pelada::class);
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'team_players');
    }

    public function teamPlayers()
    {
        return $this->hasMany(TeamPlayer::class);
    }
}

