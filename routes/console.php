<?php

use Database\Seeders\ChampionshipE2ECleanup;
use Database\Seeders\ChampionshipE2ESeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('championship:e2e-seed {championshipId}', function (int $championshipId) {
    $result = app(ChampionshipE2ESeeder::class)->run($championshipId);

    $this->info(sprintf(
        'E2E pronto: %d jogadores, %d inscrições, %d vínculos de time.%s',
        $result['players'],
        $result['registrations'],
        $result['team_links'],
        $result['reused'] ? ' Nenhum dado novo foi criado.' : '',
    ));
})->purpose('Prepara apenas jogadores fictícios em um campeonato local [E2E] vazio');

Artisan::command('championship:e2e-cleanup {championshipId} {--confirm}', function (int $championshipId) {
    if (! $this->option('confirm')) {
        $this->error('Limpeza cancelada. Informe --confirm para remover os dados marcados [E2E].');

        return self::FAILURE;
    }

    $result = app(ChampionshipE2ECleanup::class)->run($championshipId);
    $this->info(sprintf('Limpeza concluída: %d campeonato e %d jogadores fictícios removidos.', $result['championships'], $result['players']));

    return self::SUCCESS;
})->purpose('Remove um campeonato local [E2E] e seus jogadores fictícios sem referências externas');
