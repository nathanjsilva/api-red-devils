<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChampionshipVote extends Model
{
    protected $fillable = [
        'championship_award_category_id',
        'voter_id',
        'championship_voter_id',
        'voter_championship_player_id',
        'candidate_id',
    ];

    public function category()
    {
        return $this->belongsTo(ChampionshipAwardCategory::class, 'championship_award_category_id');
    }

    public function voter()
    {
        return $this->belongsTo(ChampionshipVoter::class, 'championship_voter_id');
    }
}
