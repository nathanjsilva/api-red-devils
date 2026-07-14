# Context: Estatísticas e Rankings

## Arquivos envolvidos

- `app/Http/Controllers/StatisticsController.php` — fino, delega tudo para `StatisticsService`
- `app/Services/StatisticsService.php` — toda a lógica de cálculo/consulta
- `app/Models/MatchPlayer.php`, `Pelada.php`, `Player.php`
- `routes/api.php` (grupo `Route::prefix('statistics')`, **rotas públicas**, sem `auth:sanctum`)

## Rotas (todas públicas, sem autenticação)

| Rota | Método do Controller | Método do Service | Paginado? |
|---|---|---|---|
| `GET /api/statistics/player/{playerId}/pelada/{peladaId}` | `playerInPelada` | `playerInPelada` | não |
| `GET /api/statistics/player/{playerId}/total` | `playerTotalStatistics` | `playerTotalStatistics` | não |
| `GET /api/statistics/players/overview` | `playersOverview` | `playersOverview` | **sim** |
| `GET /api/statistics/rankings/wins` | `winsRanking` | `winsRanking` | **sim** |
| `GET /api/statistics/rankings/goals` | `goalsRanking` | `goalsRanking` | **sim** |
| `GET /api/statistics/rankings/assists` | `assistsRanking` | `assistsRanking` | **sim** |
| `GET /api/statistics/rankings/goal-participation` | `goalParticipationRanking` | `goalParticipationRanking` | **sim** |
| `GET /api/statistics/rankings/goalkeepers` | `goalkeepersRanking` | `goalkeepersRanking` | **sim** |
| `GET /api/statistics/pelada/{peladaId}` | `peladaStatistics` | `peladaStatistics` | não |

Confirme com o usuário antes de restringir essas rotas com autenticação — hoje são deliberadamente públicas (é o que permite qualquer visitante ver rankings sem login).

## Onde fica a lógica

O controller só resolve 404 (`Player`/`Pelada` inexistente) e monta o envelope de resposta (`ranking_type`, `reference_year`, `minimum_matches_for_ranking`, etc.). **Toda consulta e cálculo está em `StatisticsService`** — se for alterar uma regra de ranking, mexa lá, não no controller.

## Conceito central: "ano de referência" e elegibilidade mínima

Todos os métodos de ranking (não os de consulta individual) operam **sobre o ano corrente** (`StatisticsService::currentYear()`, `now()->year`), filtrando `MatchPlayer` por `whereHas('pelada', fn($q) => $q->whereYear('date', $currentYear))` (método privado `rankingBaseQuery()`).

`minimumMatchesForRanking()`: calcula o mínimo de partidas que um jogador precisa ter disputado para aparecer em um ranking — **20% do total de peladas do ano** (`ceil($totalPeladas * 0.2)`), 0 se ainda não houve pelada no ano. Esse mínimo é aplicado via `having('total_matches', '>=', $minimumMatches)` em cada ranking. `playersOverview` não filtra por esse mínimo, mas expõe `eligible_for_ranking` por jogador para o consumidor decidir.

Se o usuário pedir para mudar essa regra de elegibilidade (o percentual `0.2`, ou a base "ano corrente"), o ponto único de alteração é `StatisticsService::minimumMatchesForRanking()` e `currentYear()`.

## Cálculos por ranking

- **wins**: conta `result = 'win'`, ordenado por total de vitórias e depois win rate.
- **goals** / **assists**: soma `goals`/`assists`, exige total > 0 e mínimo de partidas.
- **goal-participation**: soma `goals + assists`.
- **goalkeepers**: filtra `whereHas('player', fn($q) => $q->where('position', 'goleiro'))`, ordena por **menor** média de gols sofridos (`avg_goals_conceded_per_match asc`) — menor é melhor.

Desde a consolidação de `result` (ver [contexts/match-players.md](match-players.md)), as expressões SQL usam só `CASE WHEN result = 'win' THEN 1 ELSE 0 END` — **sem** o `OR (result IS NULL AND is_winner = 1)` que existia antes (o campo `is_winner` não existe mais na tabela).

## Paginação

`playersOverview` e todos os métodos de `rankings/*` retornam um `LengthAwarePaginator` (via `->paginate($perPage)->through(fn ($item) => [...])`), controlado por `?per_page=` na query string (helper `Controller::perPage()`, padrão 15, máximo 100). `playerInPelada`, `playerTotalStatistics` e `peladaStatistics` continuam retornando um objeto único, sem paginação — são consultas pontuais, não listagens.

## Ao alterar

- Qualquer novo ranking deve seguir o padrão `rankingBaseQuery()` (já filtra por ano) e retornar via `paginate()->through()` para manter consistência com os demais.
- `peladaStatistics` (estatística de uma pelada específica) **não** aplica filtro de ano nem mínimo de partidas — é uma visão pontual daquela pelada, diferente dos rankings anuais. Não confundir os dois contextos.
