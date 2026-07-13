# Context: Estatísticas de Jogador na Partida (Match Players)

`MatchPlayer` é o registro pivô que guarda o desempenho de um `Player` numa `Pelada` específica: gols, assistências, gols sofridos (goleiro), vitória/derrota/empate.

## Arquivos envolvidos

- `app/Http/Controllers/AdminController.php` (`storeMatchPlayer`, `updateMatchPlayer`, `updateMatchPlayerByPlayerAndPelada`, `deleteMatchPlayer`) — **controller efetivamente roteado**
- `app/Http/Controllers/MatchPlayerController.php` — **existe mas não está em `routes/api.php`**, ver aviso abaixo
- `app/Models/MatchPlayer.php`
- `app/Http/Requests/StoreMatchPlayerRequest.php`, `UpdateMatchPlayerRequest.php`
- `app/Http/Resources/MatchPlayerResource.php`
- `database/migrations/2026_03_18_124000_create_match_players_table.php`, `2026_04_30_120000_add_goalkeeper_goal_support_comments_to_match_players_table.php`

## ⚠️ Controller órfão

`MatchPlayerController` tem `store`/`update`/`destroy` mas **nenhuma rota em `routes/api.php` aponta para ele**. As rotas ativas de match-players usam `AdminController`. Antes de alterar lógica de estatísticas, confirme com o usuário se:
- a intenção é editar o fluxo real (`AdminController`), ou
- ele quer finalmente rotear `MatchPlayerController` (possível refatoração pendente/esquecida), ou
- `MatchPlayerController` deve ser removido por estar morto.

Não decida sozinho — pergunte.

## Rotas ativas (grupo admin)

| Rota | Método |
|---|---|
| `POST /api/admin/match-players` | `AdminController::storeMatchPlayer` |
| `PUT /api/admin/match-players/{id}` | `AdminController::updateMatchPlayer` |
| `DELETE /api/admin/match-players/{id}` | `AdminController::deleteMatchPlayer` |
| `PUT /api/admin/peladas/{peladaId}/players/{playerId}/statistics` | `AdminController::updateMatchPlayerByPlayerAndPelada` — usa `updateOrCreate` por par (`player_id`, `pelada_id`) |

## Campos e validação

- `player_id`: obrigatório (create) / `sometimes` (update), deve existir em `players`.
- `pelada_id`: obrigatório (create) / `sometimes` (update), deve existir em `peladas`.
- `goals`, `assists`, `goals_conceded`: `nullable|integer|min:0`. Desde a migration `2026_04_30_...`, as colunas correspondentes no banco também são `NULL`-áveis (antes provavelmente não eram — checar histórico se precisar entender o "porquê").
- `is_winner`: `nullable|boolean` (legado).
- `result`: `nullable|in:win,loss,draw` (campo mais novo, substituindo/complementando `is_winner`).

## Regra de negócio: `result` vs `is_winner`

O código mantém os dois campos por compatibilidade. Em várias leituras (`MatchPlayerResource`, `StatisticsController`), o padrão é:
```php
$result = $matchPlayer->result ?? ($matchPlayer->is_winner ? 'win' : 'loss');
```
Ou seja, **`result` tem prioridade; se nulo, cai para `is_winner`**. Empate (`draw`) só existe via `result` — `is_winner` sozinho não representa empate. Ao criar/atualizar registros, prefira sempre popular `result` explicitamente em vez de depender só de `is_winner`.

## Regras de validação cruzada (`withValidator`)

- **Create** (`StoreMatchPlayerRequest`): rejeita se já existe `MatchPlayer` para o mesmo par `player_id`+`pelada_id` (jogador já registrado na pelada). Exige `goals_conceded` preenchido se o jogador é goleiro (`position === 'goleiro'`) e o campo veio `null`.
- **Update** (`UpdateMatchPlayerRequest`): lógica mais complexa — tenta resolver `player_id`/`pelada_id` tanto do corpo quanto da rota (`route('playerId')`, `route('peladaId')`, ou `route('id')` legado) para decidir se precisa exigir `goals_conceded` de goleiro. Ao mexer nesse arquivo, teste os três formatos de rota que ele suporta.

## Ao alterar

- Mudanças em como `result`/`is_winner` se relacionam afetam `StatisticsController` inteiro (todas as queries `SUM(CASE WHEN result = "win" OR (result IS NULL AND is_winner = 1) ...)`) — veja [contexts/statistics.md](statistics.md) antes de mudar esse campo.
