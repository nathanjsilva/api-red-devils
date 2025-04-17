<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Player extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'position',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Relacionamentos
        public function matchPlayers()
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function peladas()
    {
        return $this->belongsToMany(Pelada::class, 'match_players')
                    ->withPivot('goals', 'assists', 'is_winner', 'goals_conceded')
                    ->withTimestamps();
    }
}
