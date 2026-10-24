<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $descriptions = [
        'Bola de Ouro' => 'Reconhece o jogador mais completo da competição: avalie regularidade, impacto nos resultados, qualidade técnica e contribuição para a equipe.',
        'Revelação do Campeonato' => 'Escolha quem mais surpreendeu em relação ao que se esperava, considerando evolução, boas atuações e confiança ao longo dos jogos.',
        'Garçom do Campeonato' => 'Premia quem mais criou chances claras para os companheiros, considerando assistências oficiais, visão de jogo e qualidade no último passe.',
        'Paredão do Campeonato' => 'Escolha o goleiro que mais protegeu a equipe, considerando defesas difíceis, segurança nas saídas, posicionamento e gols evitados.',
        'Xerifão do Campeonato' => 'Premia o defensor mais consistente: considere desarmes, antecipações, cobertura, força nos duelos e organização da defesa.',
        'Artilheiro do Campeonato' => 'Reconhece automaticamente quem marcou mais gols válidos nas partidas finalizadas; gols contra e pênaltis da disputa não entram na conta.',
        'Craque da Galera' => 'Escolha o participante que mais empolgou o grupo pelo talento, personalidade em campo e momentos que marcaram o campeonato.',
        'Bagre de Ouro' => 'Prêmio de resenha para quem acumulou os lances mais atrapalhados ou decisões infelizes; leve em conta o conjunto, sem transformar um erro isolado em critério.',
        'Decepção do Campeonato' => 'Escolha quem ficou mais abaixo do desempenho que o grupo esperava, considerando participação, regularidade e influência nos jogos.',
        'Pipoqueiro do Campeonato' => 'Prêmio bem-humorado para quem menos apareceu nos momentos de maior pressão, especialmente em jogos equilibrados ou decisivos.',
        'Avenida do Campeonato' => 'Escolha quem mais deixou espaço para os ataques adversários, considerando posicionamento, recomposição e proteção da própria área.',
        'Chato do Campeonato' => 'Prêmio de resenha para quem mais insistiu em reclamações, discussões ou provocações durante os jogos, sempre mantendo a brincadeira entre amigos.',
        'Prêmio Felipe Melo' => 'Prêmio bem-humorado para o jogador mais intenso e faltoso: considere entradas duras, disputas no limite e discussões em campo.',
    ];

    public function up(): void
    {
        Schema::table('championship_award_categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
        });

        foreach ($this->descriptions as $name => $description) {
            DB::table('championship_award_categories')->where('name', $name)->update(['description' => $description]);
        }

        DB::table('championship_award_categories')
            ->whereIn('name', ['Resenha do Campeonato', 'Inimigo do Gol'])
            ->update(['is_active' => false, 'voting_open' => false]);

        Schema::table('championship_odds', function (Blueprint $table) {
            $table->unsignedSmallInteger('calculation_version')->default(1)->after('match_id');
        });

        $versions = [];
        DB::table('championship_odds')->orderBy('id')->get(['id', 'match_id'])->each(function ($odds) use (&$versions) {
            $versions[$odds->match_id] = ($versions[$odds->match_id] ?? 0) + 1;
            DB::table('championship_odds')->where('id', $odds->id)->update(['calculation_version' => $versions[$odds->match_id]]);
        });

        Schema::table('championship_odds', function (Blueprint $table) {
            $table->unique(['match_id', 'calculation_version'], 'championship_odds_match_version_unique');
        });
    }

    public function down(): void
    {
        Schema::table('championship_odds', function (Blueprint $table) {
            $table->dropUnique('championship_odds_match_version_unique');
            $table->dropColumn('calculation_version');
        });

        Schema::table('championship_award_categories', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
