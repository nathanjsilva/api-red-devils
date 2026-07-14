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
| Middleware/Permissões admin | grupo `middleware(['auth:sanctum','admin'])->prefix('admin')` | `AdminMiddleware` | — | [backend/contexts/admin.md](backend/contexts/admin.md) |

## Namespace `Admin\*`

Todos os controllers que exigem permissão de administrador vivem em `app/Http/Controllers/Admin/` (namespace `App\Http\Controllers\Admin`). Controllers no namespace raiz (`App\Http\Controllers`) são sempre de leitura pública. Ao criar uma nova funcionalidade de escrita, siga esse padrão: controller em `Admin\`, rota dentro do grupo `middleware(['auth:sanctum','admin'])->prefix('admin')`.

## Camada de Services (`app/Services/`)

Regras de negócio que envolvem múltiplos passos, transação de banco, ou são compartilhadas por mais de um controller vivem em `app/Services/`:

- **`TeamOrganizerService`** — organização manual e automática de times (usada por `Admin\TeamController`). Concentra a lógica transacional e a distribuição de goleiros/jogadores de linha, garantindo que os dois fluxos (manual e automático) fiquem consistentes entre si.
- **`StatisticsService`** — todo o cálculo de rankings e estatísticas (usado por `StatisticsController`).

Controllers não devem reimplementar lógica que já exista num Service — veja [backend/architecture.md](backend/architecture.md).
