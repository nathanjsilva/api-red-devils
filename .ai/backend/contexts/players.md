# Context: Jogadores (Players)

## Arquivos envolvidos

- `app/Http/Controllers/PlayerController.php` (leitura pública)
- `app/Http/Controllers/AdminController.php` (CRUD admin: `storePlayer`, `updatePlayer`, `deletePlayer`)
- `app/Models/Player.php`
- `app/Http/Requests/AdminStorePlayerRequest.php`, `UpdatePlayerRequest.php`
- `app/Http/Resources/PlayerResource.php`
- `database/migrations/2026_03_18_122000_create_players_table.php`
- `database/factories/PlayerFactory.php`

## Rotas

| Rota | Auth | Controller/Método |
|---|---|---|
| `GET /api/players` | pública | `PlayerController::index` (ordenado por `name`) |
| `GET /api/players/{id}` | pública | `PlayerController::show` |
| `POST /api/admin/players` | admin | `AdminController::storePlayer` |
| `PUT /api/admin/players/{id}` | admin | `AdminController::updatePlayer` |
| `DELETE /api/admin/players/{id}` | admin | `AdminController::deletePlayer` |

## Campos e regras de validação

- `name`: obrigatório, string, max 255. **Não é único** (apesar do que o README sugere) — `AdminStorePlayerRequest` não valida unicidade de `name`.
- `nickname`: obrigatório, string, max 255, **único** (`unique:players,nickname`, ignorando o próprio id em update).
- `position`: obrigatório, `in:linha,goleiro`. É o campo que determina se o jogador é goleiro em toda a lógica de estatísticas e organização de times (`position === 'goleiro'`).

## Regras de negócio

- `Player` não tem email, senha ou telefone no schema atual — apenas `name`, `nickname`, `position`. Se o usuário pedir para adicionar esses campos, isso é uma mudança de schema (nova migration) e deve ser tratada como tal, não assumir que já existem.
- `position` é o campo central: goleiros participam de rankings específicos (`goalkeepersRanking`) e precisam de `goals_conceded` preenchido em `match_players`.

## Ao alterar

- Alterar `nickname` para não-único, ou `position` para aceitar outros valores, tem impacto direto em `TeamController::organizeTeams`/`AdminController::organizeTeams` (que filtram por `position === 'goleiro'`/`'linha'`) e em `StatisticsController` (rankings). Avalie o impacto nesses arquivos antes de mudar a regra.
