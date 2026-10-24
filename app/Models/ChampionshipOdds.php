<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChampionshipOdds extends Model
{
    protected $fillable = ['match_id','calculation_version','home_probability','draw_probability','away_probability','home_odd','draw_odd','away_odd','parameters'];
    protected $casts = ['calculation_version' => 'integer', 'parameters' => 'array'];
}
