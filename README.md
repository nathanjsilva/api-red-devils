# API Red Devils

API REST (Laravel 11 / PHP 8.2+) para organizar peladas de futebol amador: cadastro de jogadores, montagem de times, registro de estatísticas por partida e um módulo completo de estatísticas/rankings (dashboard, comparação entre jogadores, evolução, forma recente etc.).

> **Este projeto é somente backend.** Não existe frontend neste repositório.

## Stack

- PHP 8.2+, Laravel 11
- Laravel Sanctum 4 — autenticação via API token (sem sessão/cookies)
- MySQL 8
- Docker + Nginx (dev e produção)

## Requisitos

- Docker e Docker Compose
- (Opcional, para rodar comandos fora do container) PHP 8.2+ e Composer localmente

## Instalação (desenvolvimento local)

```bash
git clone <url-do-repositorio>
cd api-red-devils
cp .env.example .env
```

Ajuste no `.env` as credenciais do MySQL para baterem com o `docker-compose.yaml` (por padrão `DB_CONNECTION=mysql`, `DB_HOST=mysql`, `DB_DATABASE=laravel`, `DB_USERNAME=laravel`, `DB_PASSWORD=1234`).

```bash
docker-compose up -d
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
```

A API fica disponível em `http://localhost` (porta 80, via Nginx).

O seeder `AdminUserSeeder` cria um usuário admin padrão (usuário `ADMIN`) só para permitir o primeiro login — **troque a senha em qualquer ambiente que não seja a sua máquina local**.

## Autenticação

- **Não existe cadastro/login de jogador.** `Player` (atleta) e `User` (conta de acesso) são entidades separadas — só `User` autentica.
- Login: `POST /api/login` com `username` + `password`, devolve um token Sanctum.
- Envie o token nas rotas protegidas: `Authorization: Bearer {token}`.
- Só existe o nível **admin** (`profile = admin`). Todas as rotas de escrita (`/api/admin/*`) exigem `auth:sanctum` + ser admin; leitura (`players`, `peladas`, `teams`, `statistics`) é pública, sem token.

```bash
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "ADMIN", "password": "sua_senha"}'
```

## Endpoints

Visão geral por área — **para payloads, validações, fórmulas e regras de negócio, a fonte de verdade é [`routes/api.php`](routes/api.php) e os documentos em [`.ai/backend/contexts/`](.ai/backend/contexts/)**, não este README.

| Área | Rotas | Auth |
|---|---|---|
| Autenticação | `POST /login`, `POST /admin/logout`, `GET /admin/me` | login público; logout/me exigem admin |
| Jogadores | `GET /players`, `GET /players/{id}` (leitura) · `POST/PUT/DELETE /admin/players[/{id}]` (escrita) | leitura pública; escrita admin |
| Peladas | `GET /peladas`, `GET /peladas/{id}`, `GET /peladas/date/{date}` (leitura) · `POST/PUT/DELETE /admin/peladas[/{id}]` (escrita) | leitura pública; escrita admin |
| Times | `GET /teams/pelada/{id}/fields\|players\|players-with-statistics\|organized` (leitura) · `POST /admin/teams/pelada/{id}/organize` (manual) · `POST /admin/peladas/{id}/organize-teams` (automática) | leitura pública; organização admin |
| Estatísticas por partida | `POST/PUT/DELETE /admin/match-players[/{id}]`, `PUT /admin/peladas/{peladaId}/players/{playerId}/statistics` | admin |
| Estatísticas e rankings | `GET /statistics/*` (dashboard, rankings, jogador individual, comparação, goleiros, evolução, forma recente, pelada/match) — ver lista completa abaixo | pública |

<details>
<summary>Rotas completas de <code>/api/statistics/*</code></summary>

```
GET /statistics/dashboard
GET /statistics/rankings/goals
GET /statistics/rankings/assists
GET /statistics/rankings/goal-participation        (formato simples, legado)
GET /statistics/rankings/goal-participations        (formato completo)
GET /statistics/rankings/wins
GET /statistics/rankings/win-rate
GET /statistics/rankings/appearances
GET /statistics/rankings/goalkeepers
GET /statistics/players/overview
GET /statistics/players/compare?player_ids[]=1&player_ids[]=2
GET /statistics/players/{player}
GET /statistics/player/{playerId}/pelada/{peladaId}  (legado)
GET /statistics/player/{playerId}/total              (legado)
GET /statistics/goalkeepers
GET /statistics/goalkeepers/{player}
GET /statistics/pelada/{peladaId}                    (legado, visão simples)
GET /statistics/matches/{match}                      (visão enriquecida: líderes, times, saldo de gols)
GET /statistics/evolution?group_by=match|month|year
GET /statistics/recent-form?last_matches=5|10
```

Filtros aceitos (quando aplicável): `start_date`, `end_date`, `year`, `month`, `division` (`quinta`/`sabado`), `minimum_matches`, `limit`, `per_page`. Detalhes e fórmulas em [`.ai/backend/contexts/statistics.md`](.ai/backend/contexts/statistics.md).

</details>

### Divisões (quinta / sábado)

Toda pelada pertence a uma divisão (`division`: `quinta` ou `sabado`), validada contra o dia da semana da `date`. Qualquer endpoint de `/statistics` aceita `?division=quinta` ou `?division=sabado` para ver as estatísticas isoladas daquela divisão (incluindo o piso de elegibilidade de rankings, recalculado por divisão). Sem o filtro, os dados aparecem combinados. Ver [`.ai/backend/contexts/peladas.md`](.ai/backend/contexts/peladas.md).

### Exemplos rápidos

```bash
# Criar uma pelada de sábado (autenticado como admin)
curl -X POST http://localhost/api/admin/peladas \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2026-08-01",
    "division": "sabado",
    "location": "Quadra Central",
    "qtd_times": 2,
    "qtd_jogadores_por_time": 6,
    "qtd_goleiros": 2
  }'

# Dashboard geral de estatísticas
curl http://localhost/api/statistics/dashboard

# Ranking de artilheiros, só da divisão de sábado
curl "http://localhost/api/statistics/rankings/goals?division=sabado"

# Comparar dois jogadores lado a lado
curl "http://localhost/api/statistics/players/compare?player_ids[]=1&player_ids[]=2"
```

## Estrutura do projeto

```
app/
├── Http/Controllers/        # público na raiz, escrita em Controllers/Admin/
├── Http/Requests/           # validação (Form Requests)
├── Http/Resources/          # serialização das respostas
├── Services/                # regra de negócio multi-passo (TeamOrganizerService, StatisticsService)
├── Models/                  # Eloquent
└── Exceptions/              # exceptions de domínio (ex.: InsufficientGoalkeepersException)
routes/api.php                # todas as rotas da API
database/migrations/
database/factories/
tests/Feature/
.ai/                          # documentação de contexto (arquitetura, convenções, regras de negócio)
```

Arquitetura detalhada em [`.ai/backend/architecture.md`](.ai/backend/architecture.md) e padrões de código em [`.ai/backend/coding-standards.md`](.ai/backend/coding-standards.md).

## Testes

```bash
composer install
php vendor/bin/phpunit
```

Os testes rodam contra um banco MySQL dedicado (não SQLite), porque parte das migrations usa SQL específico do MySQL. Configure `phpunit.xml` (`DB_DATABASE=laravel_testing` por padrão) apontando para um banco MySQL acessível localmente — no ambiente Docker deste projeto, isso significa expor a porta do serviço `mysql` (`3306`) e criar esse banco uma vez:

```sql
CREATE DATABASE IF NOT EXISTS laravel_testing;
GRANT ALL PRIVILEGES ON laravel_testing.* TO 'laravel'@'%';
```

## Deploy

Deploy automatizado por push na branch `main` via GitHub Actions, que executa `deploy.sh` numa VPS (Docker Compose + Nginx). Guia completo de configuração do servidor do zero em [`docs/DEPLOY_AUTOMATICO.md`](docs/DEPLOY_AUTOMATICO.md).

**Nunca** edite `deploy.sh`, `docker-compose.prod.yml`, `nginx/production.conf` ou os secrets do GitHub Actions sem necessidade — afeta produção diretamente.

## Pontos de atenção conhecidos

- O usuário admin seedado (`AdminUserSeeder`) usa uma senha padrão fraca — troque-a fora do ambiente local.
- Tokens Sanctum não têm expiração configurada (`config/sanctum.php`).
- `Player` e `Pelada` usam soft delete: "apagar" não remove a linha do banco, preserva o histórico de partidas.
