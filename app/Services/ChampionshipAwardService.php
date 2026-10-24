<?php

namespace App\Services;

use App\Models\Championship;
use App\Models\ChampionshipAwardCategory;
use App\Models\ChampionshipAwardResult;

class ChampionshipAwardService
{
    public function seed(Championship $championship): void
    {
        $categories = [
            ['Bola de Ouro', 'Reconhece o jogador mais completo da competição: avalie regularidade, impacto nos resultados, qualidade técnica e contribuição para a equipe.', 'vote', false],
            ['Revelação do Campeonato', 'Escolha quem mais surpreendeu em relação ao que se esperava, considerando evolução, boas atuações e confiança ao longo dos jogos.', 'vote', false],
            ['Garçom do Campeonato', 'Premia quem mais criou chances claras para os companheiros, considerando assistências oficiais, visão de jogo e qualidade no último passe.', 'statistic', false],
            ['Paredão do Campeonato', 'Escolha o goleiro que mais protegeu a equipe, considerando defesas difíceis, segurança nas saídas, posicionamento e gols evitados.', 'vote', true],
            ['Xerifão do Campeonato', 'Premia o defensor mais consistente: considere desarmes, antecipações, cobertura, força nos duelos e organização da defesa.', 'vote', false],
            ['Artilheiro do Campeonato', 'Reconhece automaticamente quem marcou mais gols válidos nas partidas finalizadas; gols contra e pênaltis da disputa não entram na conta.', 'statistic', false],
            ['Craque da Galera', 'Escolha o participante que mais empolgou o grupo pelo talento, personalidade em campo e momentos que marcaram o campeonato.', 'vote', false],
            ['Bagre de Ouro', 'Prêmio de resenha para quem acumulou os lances mais atrapalhados ou decisões infelizes; leve em conta o conjunto, sem transformar um erro isolado em critério.', 'vote', false],
            ['Decepção do Campeonato', 'Escolha quem ficou mais abaixo do desempenho que o grupo esperava, considerando participação, regularidade e influência nos jogos.', 'vote', false],
            ['Pipoqueiro do Campeonato', 'Prêmio bem-humorado para quem menos apareceu nos momentos de maior pressão, especialmente em jogos equilibrados ou decisivos.', 'vote', false],
            ['Avenida do Campeonato', 'Escolha quem mais deixou espaço para os ataques adversários, considerando posicionamento, recomposição e proteção da própria área.', 'vote', false],
            ['Chato do Campeonato', 'Prêmio de resenha para quem mais insistiu em reclamações, discussões ou provocações durante os jogos, sempre mantendo a brincadeira entre amigos.', 'vote', false],
            ['Prêmio Felipe Melo', 'Prêmio bem-humorado para o jogador mais intenso e faltoso: considere entradas duras, disputas no limite e discussões em campo.', 'vote', false],
        ];

        foreach ($categories as [$name, $description, $type, $goalkeepersOnly]) {
            $category = ChampionshipAwardCategory::firstOrNew(['championship_id' => $championship->id, 'name' => $name]);
            $isNew = ! $category->exists;
            $category->fill([
                'description' => $description,
                'type' => $type,
                'goalkeepers_only' => $goalkeepersOnly,
                'is_active' => true,
            ]);
            if ($isNew) {
                $category->voting_open = $type === 'vote';
            }
            $category->save();
        }
    }

    public function endVoting(Championship $championship): int
    {
        return $championship->awardCategories()
            ->available()
            ->where('type', 'vote')
            ->where('published', false)
            ->update(['voting_open' => false]);
    }

    public function consolidate(ChampionshipAwardCategory $category): void
    {
        $total = $category->votes()->count();
        if (! $total) {
            return;
        }

        $category->votes()
            ->selectRaw('candidate_id, COUNT(*) as votes')
            ->groupBy('candidate_id')
            ->get()
            ->each(function ($row) use ($category, $total) {
                ChampionshipAwardResult::updateOrCreate(
                    ['championship_award_category_id' => $category->id, 'winner_id' => $row->candidate_id],
                    ['votes' => $row->votes, 'percentage' => round($row->votes / $total * 100, 2)],
                );
            });
    }
}
