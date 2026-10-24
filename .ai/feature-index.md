# .ai/feature-index.md — Índice de Funcionalidades

Para qualquer alteração numa área abaixo, leia o context correspondente em `.ai/backend/contexts/` **antes** de mexer no código.

| Funcionalidade | Rotas principais (`routes/api.php`) | Controller(s) | Service(s) | Context |
|---|---|---|---|---|
| Autenticação | `POST /login`, `POST /admin/logout`, `GET /admin/me` | `AuthController` | — | [backend/contexts/auth.md](backend/contexts/auth.md) |
| Jogadores (público) | `GET /players`, `GET /players/{id}` | `PlayerController` | — | [backend/contexts/players.md](backend/contexts/players.md) |
| Jogadores (admin) | `POST/PUT/DELETE /admin/players[/{id}]` | `Admin\PlayerController` | — | [backend/contexts/players.md](backend/contexts/players.md) |
| Peladas (público) | `GET /peladas`, `GET /peladas/{id}`, `GET /peladas/date/{date}` | `PeladaController` | — | [backend/contexts/peladas.md](backend/contexts/peladas.md) |
| Peladas (admin) | `POST/PUT/DELETE /admin/peladas[/{id}]` | `Admin\PeladaController` | — | [backend/contexts/peladas.md](backend/contexts/peladas.md) |
| Times — leitura (público) | `GET /teams/pelada/{id}/fields\|players\|players-with-statistics\|organized` | `TeamController` | — | [backend/contexts/teams.md](backend/contexts/teams.md) |
| Times — organização (admin) | `POST /admin/teams/pelada/{id}/organize` (manual), `POST /admin/peladas/{id}/organize-teams` (automática) | `Admin\TeamController` | `TeamOrganizerService` | [backend/contexts/teams.md](backend/contexts/teams.md) |
| Estatísticas por partida (match-players) | `POST/PUT/DELETE /admin/match-players[/{id}]`, `PUT /admin/peladas/{peladaId}/players/{playerId}/statistics` | `Admin\MatchPlayerController` | — | [backend/contexts/match-players.md](backend/contexts/match-players.md) |
| Estatísticas e rankings (público) | `GET /statistics/*` | `StatisticsController` | `StatisticsService` | [backend/contexts/statistics.md](backend/contexts/statistics.md) |
| Campeonato | `GET /championships[/{id}]`, `/awards`, `/statistics`, `/rankings`, votação pública por PIN, voto legado em `/championship-awards/{category}/votes` e operações administrativas em `/admin` | `ChampionshipController`, `ChampionshipVoterController`, `Admin\ChampionshipVoterController` | `ChampionshipFixtureService`, `ChampionshipValidationService`, `ChampionshipStandingsService`, `ChampionshipPlayoffService`, `ChampionshipStatisticsService`, `ChampionshipOddsService`, `ChampionshipAwardService`, `ChampionshipAuditService` | [backend/contexts/championships.md](backend/contexts/championships.md) |
| Middleware/Permissões admin | grupo `middleware(['auth:sanctum','admin'])->prefix('admin')` | `AdminMiddleware` | — | [backend/contexts/admin.md](backend/contexts/admin.md) |

## Namespaces e autorização admin

A maioria das escritas de CRUD fica em app/Http/Controllers/Admin e precisa estar no grupo auth:sanctum + admin. Exceção atual: ChampionshipController, no namespace raiz, reúne leituras públicas e escritas administrativas; suas rotas de escrita continuam protegidas pelo grupo em routes/api.php. Não infira acesso pelo namespace: confira a rota.

## Camada de Services (app/Services/)

Regras compartilhadas, cálculos e operações transacionais ficam em Services; CRUD simples pode usar Eloquent no Controller.

- TeamOrganizerService — organização manual/automática de times de pelada e posições de goleiro.
- StatisticsService — agregações, rankings e cache das estatísticas tradicionais.
- ChampionshipValidationService — valida elenco para início e geração de fixtures em draft.
- ChampionshipFixtureService — fixtures da fase de grupos.
- ChampionshipStandingsService — classificação e critérios de desempate.
- ChampionshipPlayoffService — snapshot e criação de playoffs.
- ChampionshipStatisticsService — agregados do módulo campeonato.
- ChampionshipOddsService — cálculo e versionamento de odds.
- ChampionshipAwardService — categorias e consolidação de resultados.
- ChampionshipAuditService — log de operações administrativas selecionadas.

ChampionshipController reúne operações públicas e administrativas; autorização vem da rota. Confira [architecture.md](backend/architecture.md) e o context do campeonato antes de alterar esses Services.