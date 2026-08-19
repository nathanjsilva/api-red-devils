<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'pelada_id',
        'numero',
        'goleiro_id',
    ];

    public function pelada()
    {
        return $this->belongsTo(Pelada::class);
    }

    public function goleiro()
    {
        return $this->belongsTo(Player::class, 'goleiro_id');
    }
}
