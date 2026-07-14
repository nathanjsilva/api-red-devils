# Context: Jogadores (Players)

## Arquivos envolvidos

- `app/Http/Controllers/PlayerController.php` (leitura pública: `index` paginado, `show`)
- `app/Http/Controllers/Admin/PlayerController.php` (escrita: `store`, `update`, `destroy`)
- `app/Models/Player.php` (usa `SoftDeletes`)
- `app/Http/Requests/AdminStorePlayerRequest.php`, `UpdatePlayerRequest.php`
- `app/Http/Resources/PlayerResource.php`
- `database/migrations/2026_03_18_122000_create_players_table.php`, `2026_07_12_130000_add_soft_deletes_to_players_and_peladas_tables.php`
- `database/factories/PlayerFactory.php`

## Rotas

| Rota | Auth | Controller/Método |
|---|---|---|
| `GET /api/players` | pública, paginada (`?per_page=`) | `PlayerController::index` (ordenado por `name`) |
| `GET /api/players/{id}` | pública | `PlayerController::show` |
| `POST /api/admin/players` | admin | `Admin\PlayerController::store` |
| `PUT /api/admin/players/{id}` | admin | `Admin\PlayerController::update` |
| `DELETE /api/admin/players/{id}` | admin | `Admin\PlayerController::destroy` (**soft delete**) |

## Campos e regras de validação

- `name`: obrigatório, string, max 255. **Não é único** — decisão deliberada (duas pessoas do grupo podem ter o mesmo nome; `nickname` é o identificador único). Não "corrija" isso adicionando unicidade sem alinhar com o usuário.
- `nickname`: obrigatório, string, max 255, **único** (`unique:players,nickname`, ignorando o próprio id em update).
- `position`: obrigatório, `in:linha,goleiro`. É o campo que determina se o jogador é goleiro em toda a lógica de estatísticas e organização de times (`position === 'goleiro'`).

## Regras de negócio

- `Player` não tem email, senha ou telefone no schema atual — apenas `name`, `nickname`, `position`. Se o usuário pedir para adicionar esses campos, isso é uma mudança de schema (nova migration) e deve ser tratada como tal, não assumir que já existem.
- `position` é o campo central: goleiros participam de rankings específicos (`goalkeepersRanking`) e precisam de `goals_conceded` preenchido em `match_players`.
- **`Player` usa soft delete.** `DELETE /api/admin/players/{id}` marca `deleted_at`, não remove a linha. Isso é intencional: como `match_players.player_id` tem `cascadeOnDelete()`, um `DELETE` físico apagaria todo o histórico de estatísticas daquele jogador em todas as peladas. Nunca troque `delete()` por `forceDelete()` num controller/rota sem autorização explícita do usuário — é uma decisão de preservação de dados, não um detalhe técnico incidental.
- Efeito colateral aceito do soft delete: a validação `unique:players,nickname` continua considerando nicknames de jogadores soft-deletados como "em uso" (a checagem de unicidade do Laravel não filtra `deleted_at` automaticamente quando a regra é passada como string `unique:players,nickname`). Para reaproveitar um nickname de um jogador removido, é preciso restaurar o registro antigo (`Player::withTrashed()->find($id)->restore()`), não criar um novo. Isso é uma limitação conhecida, não um bug a "corrigir" sem discutir a abordagem primeiro (resolver direito exigiria índice único parcial ou lógica de restore automática).

## Ao alterar

- Alterar `nickname` para não-único, ou `position` para aceitar outros valores, tem impacto direto em `TeamOrganizerService` (que filtra por `position === 'goleiro'`/`'linha'`) e em `StatisticsService` (rankings). Avalie o impacto nesses arquivos antes de mudar a regra.
