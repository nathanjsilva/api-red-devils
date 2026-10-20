<?php
namespace App\Services;
use App\Models\Championship;
use App\Models\ChampionshipAwardCategory;
use App\Models\ChampionshipAwardResult;
use App\Models\ChampionshipVote;
class ChampionshipAwardService
{
    public function seed(Championship $championship): void
    {
        $items = [['Bola de Ouro','Melhor jogador do campeonato.','vote',false],['Revelação do Campeonato','Jogador que mais surpreendeu.','vote',false],['Garçom do Campeonato','Maior número de assistências oficiais.','statistic',false],['Paredão do Campeonato','Melhor goleiro.','vote',true],['Xerifão do Campeonato','Melhor defensor.','vote',false],['Artilheiro do Campeonato','Maior número de gols oficiais.','statistic',false],['Craque da Galera','Jogador mais admirado.','vote',false],['Bagre de Ouro','Pior desempenho do campeonato.','vote',false],['Decepção do Campeonato','Expectativa não correspondida.','vote',false],['Inimigo do Gol','Mais oportunidades desperdiçadas.','vote',false],['Pipoqueiro do Campeonato','Desapareceu nos momentos decisivos.','vote',false],['Avenida do Campeonato','Mais facilitou ataques adversários.','vote',false],['Resenha do Campeonato','Mais engraçado e participativo.','vote',false],['Chato do Campeonato','Mais reclamão.','vote',false],['Prêmio Felipe Melo','Mais faltoso.','vote',false]];
        foreach ($items as [$name,$description,$type,$keepers]) ChampionshipAwardCategory::firstOrCreate(['championship_id'=>$championship->id,'name'=>$name], ['description'=>$description,'type'=>$type,'goalkeepers_only'=>$keepers,'voting_open'=>$type==='vote']);
    }
    public function endVoting(Championship $championship): int
    {
        return $championship->awardCategories()->where('type','vote')->where('published',false)->update(['voting_open'=>false]);
    }
    public function consolidate(ChampionshipAwardCategory $category): void
    {
        $total = $category->votes()->count(); if (!$total) return;
        $category->votes()->selectRaw('candidate_id, COUNT(*) as votes')->groupBy('candidate_id')->get()->each(function ($row) use ($category,$total) { ChampionshipAwardResult::updateOrCreate(['championship_award_category_id'=>$category->id,'winner_id'=>$row->candidate_id], ['votes'=>$row->votes,'percentage'=>round($row->votes / $total * 100, 2)]); });
    }
}
