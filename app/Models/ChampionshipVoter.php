<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChampionshipVoter extends Model
{
    protected $fillable = [
        'championship_id',
        'championship_player_id',
        'pin_hash',
        'failed_attempts',
        'locked_until',
        'session_token_hash',
        'session_expires_at',
    ];

    protected $hidden = ['pin_hash', 'session_token_hash'];

    protected $casts = [
        'failed_attempts' => 'integer',
        'locked_until' => 'datetime',
        'session_expires_at' => 'datetime',
    ];

    public function championship()
    {
        return $this->belongsTo(Championship::class);
    }

    public function championshipPlayer()
    {
        return $this->belongsTo(ChampionshipPlayer::class);
    }

    public function votes()
    {
        return $this->hasMany(ChampionshipVote::class);
    }
}
