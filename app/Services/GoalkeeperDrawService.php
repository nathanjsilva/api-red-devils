<?php
namespace App\Services;
use App\Models\Championship;
use App\Models\ChampionshipGoalkeeperDraw;
use Illuminate\Support\Facades\DB;
class GoalkeeperDrawService
{
    public function draw(Championship $championship, ?int $userId=null, bool $confirmed=false): void
    {
        DB::transaction(function () use ($championship,$userId) {
            abort_if($championship->status !== 'draft', 422, 'O sorteio só pode ser feito antes do início.');
            $teams=$championship->teams()->orderBy('id')->lockForUpdate()->get(); $keepers=$championship->players()->where('player_type','goalkeeper')->inRandomOrder()->lockForUpdate()->get();
            abort_if($teams->count() !== 5 || $keepers->count() !== 5, 422, 'São necessários cinco equipes e cinco goleiros.');
            abort_if($championship->goalkeeperDraws()->exists() && !$confirmed, 422, 'Confirme o novo sorteio antes de refazê-lo.');
            $teams->each(fn($team) => $team->players()->wherePivotIn('championship_player_id', $championship->players()->where('player_type','goalkeeper')->pluck('id'))->detach());
            $number=(int)($championship->goalkeeperDraws()->max('draw_number') ?? 0)+1;
            foreach ($teams as $index=>$team) { $team->players()->attach($keepers[$index]->id); ChampionshipGoalkeeperDraw::create(['championship_id'=>$championship->id,'team_id'=>$team->id,'championship_player_id'=>$keepers[$index]->id,'draw_number'=>$number,'drawn_by'=>$userId]); }
        });
    }
}
