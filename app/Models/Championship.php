<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Championship extends Model
{
    protected $fillable = ['name', 'status', 'phase', 'goalkeeper_rule_enabled'];
    protected $casts = ['goalkeeper_rule_enabled' => 'boolean', 'standings_snapshot' => 'array'];
    public function teams() { return $this->hasMany(ChampionshipTeam::class); }
    public function players() { return $this->hasMany(ChampionshipPlayer::class); }
    public function matches() { return $this->hasMany(ChampionshipMatch::class); }
    public function awardCategories() { return $this->hasMany(ChampionshipAwardCategory::class); }
    public function goalkeeperDraws() { return $this->hasMany(ChampionshipGoalkeeperDraw::class); }
    public function auditLogs() { return $this->hasMany(ChampionshipAuditLog::class); }
    public function tieBreakers() { return $this->hasMany(ChampionshipTieBreaker::class); }
}
