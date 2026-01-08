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
        'phone',
        'nickname',
        'is_admin',
        'user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function matchPlayers()
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function peladas()
    {
        return $this->belongsToMany(Pelada::class, 'match_players')
                    ->withPivot('goals', 'assists', 'is_winner', 'result', 'goals_conceded')
                    ->withTimestamps();
    }
}
