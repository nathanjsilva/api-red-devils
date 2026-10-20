<?php
namespace App\Services;
use App\Models\ChampionshipAuditLog;
use Illuminate\Support\Facades\Auth;
class ChampionshipAuditService { public function record(int $championshipId, string $action, $model=null, array $payload=[]): void { ChampionshipAuditLog::create(['championship_id'=>$championshipId,'user_id'=>Auth::id(),'action'=>$action,'auditable_type'=>$model ? $model::class : null,'auditable_id'=>$model?->getKey(),'payload'=>$payload]); } }
