# Context: Estatísticas e Rankings

## Arquivos envolvidos

- `app/Http/Controllers/StatisticsController.php` — fino, delega tudo para `StatisticsService`
- `app/Services/StatisticsService.php` — toda a lógica de cálculo/consulta
- `app/Http/Requests/ComparePlayersRequest.php` — valida `players/compare`
- `app/Models/MatchPlayer.php`, `Pelada.php`, `Player.php`, `Team.php`, `TeamPlayer.php`
- `routes/api.php` (grupo `Route::prefix('statistics')`, **rotas públicas**, sem `auth:sanctum`)
- `tests/Feature/StatisticsTest.php` — testes de cálculo, ranking, desempate e filtros

## Rotas (todas públicas, sem autenticação)

### Endpoints originais (formato de resposta antigo, mantido)

| Rota | Método do Controller | Paginado? |
|---|---|---|
| `GET /api/statistics/player/{playerId}/pelada/{peladaId}` | `playerInPelada` | não |
| `GET /api/statistics/player/{playerId}/total` | `playerTotalStatistics` | não |
| `GET /api/statistics/players/overview` | `playersOverview` | sim |
| `GET /api/statistics/rankings/goal-participation` | `goalParticipationRanking` | sim |
| `GET /api/statistics/pelada/{peladaId}` | `peladaStatistics` | não |

### Endpoints novos/atualizados (envelope `data`/`meta`)

| Rota | Método do Controller | Paginado? |
|---|---|---|
| `GET /api/statistics/dashboard` | `dashboard` | não |
| `GET /api/statistics/rankings/goals` | `goalsRanking` | sim (payload enriquecido — ver abaixo) |
| `GET /api/statistics/rankings/assists` | `assistsRanking` | sim (enriquecido) |
| `GET /api/statistics/rankings/wins` | `winsRanking` | sim (enriquecido) |
| `GET /api/statistics/rankings/goalkeepers` | `goalkeepersRanking` | sim (enriquecido) |
| `GET /api/statistics/rankings/goal-participations` | `goalParticipationsRanking` | sim |
| `GET /api/statistics/rankings/win-rate` | `winRateRanking` | sim |
| `GET /api/statistics/rankings/appearances` | `appearancesRanking` | sim |
| `GET /api/statistics/players/{player}` | `playerStatistics` | não |
| `GET /api/statistics/players/compare?player_ids[]=..` | `comparePlayers` | não |
| `GET /api/statistics/goalkeepers` | `goalkeepers` | sim |
| `GET /api/statistics/goalkeepers/{player}` | `goalkeeperStatistics` | não |
| `GET /api/statistics/matches/{match}` | `matchStatistics` | não |
| `GET /api/statistics/evolution` | `evolution` | não (lista de períodos) |
| `GET /api/statistics/peladas-per-month` | `peladasPerMonth` | não (lista de meses) |
| `GET /api/statistics/recent-form` | `recentForm` | não (lista, limitada por `limit`) |

**⚠️ Mudança de contrato deliberada:** os endpoints `rankings/goals`, `rankings/assists`, `rankings/wins` e `rankings/goalkeepers` **já existiam** com o mesmo path. Como o pedido do usuário especificou literalmente esses mesmos paths para o novo formato enriquecido (posição, jogos, gols, assistências, participações, vitórias, aproveitamento, média por jogo, valor principal), não havia como manter duas rotas com o mesmo método+path — o controller e o envelope de resposta desses 4 endpoints foram **substituídos** pelo novo formato (`data`/`meta`), e os métodos antigos correspondentes em `StatisticsService` (`goalsRanking`, `assistsRanking`, `winsRanking`, `goalkeepersRanking`) ficaram **sem uso**, preservados no arquivo (não foram apagados, por não haver autorização explícita para remover código). `rankings/goal-participation` (singular, formato antigo) continua intocado porque seu path não colide com o novo `goal-participations` (plural).

## Onde fica a lógica

O controller só resolve 404 (`Player`/`Pelada` inexistente), extrai filtros da query string e monta o envelope de resposta. **Toda consulta e cálculo está em `StatisticsService`.**

## Filtros aceitos (nos endpoints novos)

`start_date`, `end_date`, `year`, `month`, `division` — aplicados via `StatisticsService::applyDateFilters()` sobre a data (e, se `division` vier, também sobre a coluna `division`) da pelada. `minimum_matches` (rankings/goalkeepers/recent-form) sobrescreve o mínimo calculado. `limit` é usado por `evolution` (número de períodos) e `recent-form` (top N jogadores) — os rankings continuam paginados por `per_page` (padrão já existente do projeto), não por `limit`.

### `division` (quinta/sábado)

`?division=quinta` ou `?division=sabado` filtra **todas** as estatísticas (dashboard, os 7 rankings completos, `players/{player}`, `compare`, `goalkeepers`, `evolution`, `recent-form`, `matches/{match}` via `meta.division`, melhor dupla) para considerar só peladas daquela divisão. Valor fora desse conjunto é **ignorado silenciosamente** (`StatisticsController::filtersFromRequest`), mesmo padrão de tolerância dos demais filtros de período (que também não são validados estritamente). Sem `division`, o comportamento é o combinado (todas as divisões juntas) — nenhuma mudança para quem já consome os endpoints sem esse parâmetro.

**O piso de 20% (`minimumMatchesForScope`) é recalculado por divisão quando o filtro é usado** — como o cálculo já conta `Pelada::query()` aplicando os mesmos filtros, isso "cai de graça" ao adicionar `division` em `applyDateFilters()`, sem precisar de nenhuma lógica extra por endpoint. Um jogador pode ser elegível num ranking filtrado por `division=sabado` e não ser no `division=quinta` (ou no combinado), dependendo de quantas partidas jogou em cada uma.

A implementação da coluna `division` em si (schema, validação de criação/edição) está documentada em [contexts/peladas.md](peladas.md).

Quando nenhum filtro de período é passado, os endpoints novos **não** assumem "ano corrente" por padrão (diferente da regra antiga) — operam sobre todo o histórico, a menos que `year`/`start_date`/`end_date` seja informado.

## Conceito central: mínimo de partidas por escopo

`StatisticsService::minimumMatchesForScope(array $filters, ?int $override)`: generaliza a regra antiga (`minimumMatchesForRanking`, que só considera o ano corrente) para qualquer combinação de filtros — 20% do total de peladas do escopo filtrado (`ceil($totalPeladas * 0.2)`), 0 se não houver peladas no escopo, ou o valor de `minimum_matches` se informado explicitamente. Usado por todos os rankings novos, pelo dashboard (líderes) e por `players/{player}` (posição nos rankings).

**Exceção:** o ranking `appearances` (presenças) não aplica esse piso por padrão — o próprio critério do ranking já é a quantidade de partidas, então aplicar um mínimo de partidas seria circular. Só filtra se `minimum_matches` vier explícito na query string.

A regra antiga (`minimumMatchesForRanking()`, sempre ano corrente, sem filtros) continua existindo e é usada apenas pelos endpoints antigos (`players/overview`, `rankings/goal-participation`).

## Query agregada compartilhada dos rankings novos

`StatisticsService::fullRankingBaseQuery()` monta, numa única consulta agrupada por jogador, todas as métricas possíveis (jogos, gols, assistências, participações, vitórias/derrotas/empates, aproveitamento, médias por jogo, gols sofridos). Cada ranking (`goalsRankingFull`, `assistsRankingFull`, `winsRankingFull`, `winRateRankingFull`, `appearancesRankingFull`, `goalkeepersRankingFull`, `goalParticipationsRankingFull`) só decide `order by`/`having`/filtro extra em cima dela, e `paginateFullRanking()` padroniza o item de resposta (`position`, `player`, `matches`, `goals`, `assists`, `goal_participations`, `wins`, `win_rate`, `average_per_match`, `value`). Isso evita duplicar a query 7 vezes e garante que todos os rankings tragam o mesmo conjunto de campos cruzados, como pedido.

**Cuidado com `NULL` em `goals`/`assists`/`goals_conceded`:** essas colunas são `nullable` (goleiros podem não ter `goals`/`assists` preenchidos). Somas simples (`SUM(goals)`) já ignoram `NULL` corretamente, mas expressões linha-a-linha (`goals + assists`) propagam `NULL` em SQL caso qualquer um dos dois seja nulo, subestimando a soma agregada. Todo o SQL novo usa `COALESCE(coluna, 0)` antes de somar/dividir para evitar isso — os métodos antigos (`playerTotalStatistics`, `playersOverview`, `goalsRanking`, `assistsRanking`, `goalParticipationRanking`) **não foram tocados** e mantêm o comportamento anterior (fora de escopo deste trabalho).

## Fórmulas

- `participações em gols = gols + assistências`
- `aproveitamento = vitórias / jogos × 100`
- `média por jogo` (rankings) = a métrica do próprio ranking dividida por `total_matches` (ex.: `avg_goals_per_match` no ranking de gols); no ranking de presenças não se aplica (`null`); nos rankings de aproveitamento/goleiros, `average_per_match` repete o próprio valor (já é uma taxa).
- **Posição num ranking** (`playerRankingPosition`): 1 + quantidade de jogadores elegíveis estritamente melhores naquela métrica (critério "1224": empatados dividem a mesma posição). Independente do desempate secundário usado na *listagem* paginada (ex.: total de gols empatado desempatado pela média).
- **Desempate nos rankings paginados**: métrica principal desc, depois a média/taxa relacionada desc (ex.: gols → total de gols, depois média de gols por jogo).
- **Sequências** (`best_scoring_streak`, `best_participation_streak`, `best_unbeaten_streak`): maior sequência de partidas consecutivas (ordenadas por data da pelada) satisfazendo a condição (gols > 0; gols+assistências > 0; resultado ≠ derrota).
- **Assiduidade** (`attendance_rate`): partidas disputadas pelo jogador (no escopo filtrado) ÷ peladas realizadas desde a data da primeira partida do jogador (no mesmo escopo) × 100.
- **Melhor dupla** (`best_duo`/`bestDuoForPlayer`): entre jogadores que estiveram no mesmo time (`team_players`) na mesma pelada, agrupados por par; "vitória junta" exige `result = win` para **ambos**; ordenado por aproveitamento juntos desc, depois jogos juntos desc; exige mínimo de jogos juntos (mesmo piso de 20%, ou 1 no caso individual do jogador).
- **Forma recente / tendência** (`classifyTrend`): compara a média da primeira metade da janela (últimos 5/10 jogos) com a segunda metade; alta se `> +10%`, queda se `< -10%`, estável caso contrário.
- **Radar de comparação** (`comparePlayers`): cada métrica normalizada como `valor / maior_valor_entre_os_comparados × 100` (0 se o maior valor do grupo for 0).
- **Resultado de time numa pelada** (`matchStatistics`): soma de `goals` dos jogadores daquele time; `result` do time = valor mais frequente entre os `result` dos seus jogadores; `goal_difference` = maior total de gols entre os times menos o menor.
- Todas as médias/percentuais são arredondados para 2 casas decimais; divisões por zero (nenhuma pelada/partida no escopo) retornam `0`/`0.0`/`null`, nunca erro.

## Cache

Consultas agregadas pesadas (dashboard, os 7 rankings completos, goleiros, evolução, forma recente, pelada/match) usam `Cache::remember` com TTL de 5 minutos (`StatisticsService::CACHE_TTL_SECONDS`), chave derivada dos filtros/paginação (`cacheKey()`). **Não há invalidação por evento** — o projeto não tem observers/eventos hoje; o cache expira sozinho em até 5 minutos após uma escrita em `match-players`/`teams`. Se isso for um problema real de UX, considerar invalidação explícita no futuro.

## Testes

`tests/Feature/StatisticsTest.php` cobre: agregados do dashboard (com caso de divisão por zero), ordenação e desempate do ranking de gols, exclusão por `minimum_matches`, `minimum_matches` como override explícito, ranking de presenças sem piso, streaks/percentuais/assiduidade do jogador individual, classificação de tendência da forma recente, normalização do radar de comparação, validação do comparador (mínimo de 2 jogadores), resultado de times e diferença de gols em `matches/{match}`, agrupamento de evolução por mês/ano, melhor dupla, listagem de goleiros, o tratamento de `NULL` em gols/assistências, isolamento de estatísticas por `division` e o mínimo de 20% calculado separadamente por divisão. `tests/Feature/PeladaDivisionTest.php` cobre a validação de `division`/dia da semana no `Store`/`UpdatePeladaRequest`.

**Infraestrutura de testes recriada:** `phpunit.xml` não existia no repositório (removido num commit antigo) e `phpunit/phpunit`/`mockery/mockery` não estavam em `require-dev`; ambos foram adicionados. Os testes rodam contra um banco MySQL dedicado (`laravel_testing`, no mesmo container Docker do projeto) em vez de SQLite, porque duas migrations existentes (`add_goalkeeper_goal_support_comments...`, `consolidate_result_column...`) usam `ALTER TABLE ... MODIFY` (sintaxe exclusiva do MySQL) e não rodam em SQLite.

## Ao alterar

- `peladasPerMonth()` é uma versão enxuta de `evolution('month', ...)` — reaproveita a mesma query/cache e só remapeia a resposta para `period`/`total_peladas`, sem gols/assistências/jogadores. Existe porque o gráfico de "peladas por mês" no consumidor da API não precisa do resto dos campos de `evolution`. Se `evolution()` mudar o formato de `period` ou de `total_peladas`, `peladasPerMonth()` acompanha automaticamente.
- Qualquer novo ranking "completo" deve reaproveitar `fullRankingBaseQuery()` + `paginateFullRanking()`.
- Qualquer nova consulta agregada pesada deve usar `Cache::remember` seguindo o padrão de `cacheKey()`.
- `peladaStatistics`/`GET /statistics/pelada/{peladaId}` (visão simples, sem filtro de ano/mínimo) continua existindo separado de `matchStatistics`/`GET /statistics/matches/{match}` (visão enriquecida, reaproveita `peladaStatistics` internamente e acrescenta líderes/times) — não confundir os dois.
