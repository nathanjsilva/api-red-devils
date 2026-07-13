# Context: Estatísticas e Rankings

## Arquivos envolvidos

- `app/Http/Controllers/StatisticsController.php`
- `app/Models/MatchPlayer.php`, `Pelada.php`, `Player.php`
- `routes/api.php` (grupo `Route::prefix('statistics')`, **rotas públicas**, sem `auth:sanctum`)

## Rotas (todas públicas, sem autenticação)

| Rota | Método |
|---|---|
| `GET /api/statistics/player/{playerId}/pelada/{peladaId}` | `playerInPelada` |
| `GET /api/statistics/player/{playerId}/total` | `playerTotalStatistics` |
| `GET /api/statistics/players/overview` | `playersOverview` |
| `GET /api/statistics/rankings/wins` | `winsRanking` |
| `GET /api/statistics/rankings/goals` | `goalsRanking` |
| `GET /api/statistics/rankings/assists` | `assistsRanking` |
| `GET /api/statistics/rankings/goal-participation` | `goalParticipationRanking` |
| `GET /api/statistics/rankings/goalkeepers` | `goalkeepersRanking` |
| `GET /api/statistics/pelada/{peladaId}` | `peladaStatistics` |

Confirme com o usuário antes de restringir essas rotas com autenticação — hoje são deliberadamente públicas (é o que permite qualquer visitante ver rankings sem login).

## Conceito central: "ano de referência" e elegibilidade mínima

Todos os métodos de ranking (não os de consulta individual) operam **sobre o ano corrente** (`now()->year`, método `currentYear()`), filtrando `MatchPlayer` por `whereHas('pelada', fn($q) => $q->whereYear('date', $currentYear))`.

`minimumMatchesForRanking()`: calcula o mínimo de partidas que um jogador precisa ter disputado para aparecer em um ranking — **20% do total de peladas do ano** (`ceil($totalPeladas * 0.2)`), 0 se ainda não houve pelada no ano. Esse mínimo é aplicado via `having('total_matches', '>=', $minimumMatches)` em cada ranking. `playersOverview` não filtra por esse mínimo, mas expõe `eligible_for_ranking` por jogador para o consumidor decidir.

Se o usuário pedir para mudar essa regra de elegibilidade (o percentual `0.2`, ou a base "ano corrente"), o ponto único de alteração é `StatisticsController::minimumMatchesForRanking()` e `currentYear()` — todos os rankings dependem deles via `rankingBaseQuery()`.

## Cálculos por ranking

- **wins**: conta `result = 'win'` (ou `is_winner = 1` quando `result` é nulo — legado), ordenado por total de vitórias e depois win rate.
- **goals** / **assists**: soma `goals`/`assists`, exige total > 0 e mínimo de partidas.
- **goal-participation**: soma `goals + assists`.
- **goalkeepers**: filtra `whereHas('player', fn($q) => $q->where('position', 'goleiro'))`, ordena por **menor** média de gols sofridos (`avg_goals_conceded_per_match asc`) — menor é melhor.

## `result` vs `is_winner` nas queries SQL

As queries usam SQL bruto (`selectRaw`) com a expressão:
```sql
CASE WHEN result = "win" OR (result IS NULL AND is_winner = 1) THEN 1 ELSE 0 END
```
Isso replica em SQL a mesma regra de precedência descrita em [contexts/match-players.md](match-players.md) (`result` tem prioridade, cai para `is_winner` se nulo). Se essa regra mudar em um lugar, tem que mudar nos dois (PHP e SQL raw) — não são derivados de uma função só.

## Ao alterar

- Qualquer novo ranking deve seguir o padrão `rankingBaseQuery()` (já filtra por ano) para manter consistência com os demais.
- `peladaStatistics` (estatística de uma pelada específica) **não** aplica filtro de ano nem mínimo de partidas — é uma visão pontual daquela pelada, diferente dos rankings anuais. Não confundir os dois contextos.
