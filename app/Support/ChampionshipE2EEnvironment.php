<?php

namespace App\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class ChampionshipE2EEnvironment
{
    public function assertAllowed(): void
    {
        $host = strtolower((string) Config::get('database.connections.mysql.host'));
        $database = (string) Config::get('database.connections.mysql.database');
        $appUrl = parse_url((string) Config::get('app.url'));
        $urlHost = strtolower((string) ($appUrl['host'] ?? ''));

        $allowed = App::environment('local')
            && Config::get('championship_e2e.enabled') === true
            && Config::get('database.default') === 'mysql'
            && $database === 'laravel'
            && in_array($host, ['mysql', 'localhost', '127.0.0.1'], true)
            && empty(Config::get('database.connections.mysql.url'))
            && in_array($urlHost, ['localhost', '127.0.0.1'], true);

        if (! $allowed) {
            throw new RuntimeException(
                'Operação E2E bloqueada. Exige APP_ENV=local, opt-in explícito, MySQL local (laravel) e APP_URL local.'
            );
        }
    }
}
