# .ai/feature-index.md — Índice de Funcionalidades

Para qualquer alteração numa área abaixo, leia o context correspondente em `.ai/backend/contexts/` **antes** de mexer no código.

| Funcionalidade | Rotas principais (`routes/api.php`) | Controller(s) | Context |
|---|---|---|---|
| Autenticação | `POST /login`, `POST /admin/logout`, `GET /admin/me` | `AuthController` | [backend/contexts/auth.md](backend/contexts/auth.md) |
| Jogadores (público) | `GET /players`, `GET /players/{id}` | `PlayerController` | [backend/contexts/players.md](backend/contexts/players.md) |
| Jogadores (admin) | `POST/PUT/DELETE /admin/players[/{id}]` | `AdminController` | [backend/contexts/players.md](backend/contexts/players.md) |
| Peladas (admin) | `GET/POST/PUT/DELETE /admin/peladas[/{id}]`, `GET /admin/peladas/date/{date}` | `AdminController` | [backend/contexts/peladas.md](backend/contexts/peladas.md) |
| Organização de times | `GET/POST /admin/teams/pelada/{peladaId}/...`, `POST /admin/peladas/{peladaId}/organize-teams` | `TeamController`, `AdminController` | [backend/contexts/teams.md](backend/contexts/teams.md) |
| Estatísticas por partida (match-players) | `POST/PUT/DELETE /admin/match-players[/{id}]`, `PUT /admin/peladas/{peladaId}/players/{playerId}/statistics` | `AdminController`, `MatchPlayerController` (não roteado atualmente) | [backend/contexts/match-players.md](backend/contexts/match-players.md) |
| Estatísticas e rankings (público) | `GET /statistics/*` | `StatisticsController` | [backend/contexts/statistics.md](backend/contexts/statistics.md) |
| Middleware/Permissões admin | grupo `middleware(['auth:sanctum','admin'])->prefix('admin')` | `AdminMiddleware` | [backend/contexts/admin.md](backend/contexts/admin.md) |

## Observação sobre `MatchPlayerController`

`app/Http/Controllers/MatchPlayerController.php` existe mas **não está registrado em `routes/api.php`** — as rotas de match-players ativas hoje passam por `AdminController`. Antes de editar estatísticas de partida, confirme com o usuário qual controller ele realmente quer alterar (o roteado, em `AdminController`, ou o órfão `MatchPlayerController`), pois pode ser um resquício de refatoração incompleta.
