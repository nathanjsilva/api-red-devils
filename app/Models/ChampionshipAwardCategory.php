<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ChampionshipAwardCategory extends Model
{
    protected $fillable = ['championship_id', 'name', 'description', 'type', 'goalkeepers_only', 'published', 'voting_open', 'is_active'];

    protected $casts = ['goalkeepers_only' => 'boolean', 'published' => 'boolean', 'voting_open' => 'boolean', 'is_active' => 'boolean'];

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isAvailable(): bool
    {
        return (bool) $this->is_active;
    }

    public function championship()
    {
        return $this->belongsTo(Championship::class);
    }

    public function votes()
    {
        return $this->hasMany(ChampionshipVote::class);
    }

    public function results()
    {
        return $this->hasMany(ChampionshipAwardResult::class);
    }
}
