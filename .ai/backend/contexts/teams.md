# Context: Organização de Times

## Arquivos envolvidos

- `app/Http/Controllers/TeamController.php` — leitura pública (fields/players/organized/players-with-statistics)
- `app/Http/Controllers/Admin/TeamController.php` — escrita: `organizeManual`, `organizeAutomatic`
- `app/Services/TeamOrganizerService.php` — lógica de negócio compartilhada pelos dois fluxos
- `app/Exceptions/InsufficientGoalkeepersException.php`, `InsufficientFieldPlayersException.php`
- `app/Models/Team.php`, `TeamPlayer.php`, `GoalPosition.php`, `Pelada.php`, `Player.php`
- `database/migrations/2026_03_18_125000_create_teams_table.php`, `..._create_team_players_table.php`, `2026_08_19_120000_create_goal_positions_table.php`
- `tests/Feature/TeamOrganizerGoalkeeperTest.php`

## Rotas

| Rota | Auth | Controller/Método | Propósito |
|---|---|---|---|
| `GET /api/teams/pelada/{peladaId}/fields` | pública | `TeamController::getTeamFields` | Lista os "slots" de time (`time_1`..`time_N`) conforme `qtd_times` da pelada |
| `GET /api/teams/pelada/{peladaId}/players` | pública | `TeamController::getPeladaPlayers` | Jogadores ainda **não** registrados como `MatchPlayer` nessa pelada |
| `GET /api/teams/pelada/{peladaId}/players-with-statistics` | pública | `TeamController::getPeladaPlayersWithStatistics` | Jogadores da pelada + estatísticas + time atual |
| `GET /api/teams/pelada/{peladaId}/organized` | pública | `TeamController::getPeladaTeams` | Times já montados, com jogadores |
| `POST /api/admin/teams/pelada/{peladaId}/organize` | admin | `Admin\TeamController::organizeManual` → `TeamOrganizerService::organizeManual` | Organização **manual**: recebe `team_assignments` explícitos |
| `POST /api/admin/peladas/{peladaId}/organize-teams` | admin | `Admin\TeamController::organizeAutomatic` → `TeamOrganizerService::organizeAutomatic` | Organização **automática**: recebe só `player_ids`, distribui goleiros e linha automaticamente |

As rotas de leitura são públicas (mudança do refactor de arquitetura); as de organização (escrita) continuam exigindo admin.

## Duas lógicas de organização — agora compartilham `TeamOrganizerService`

1. **Manual** (`organizeManual`): o cliente já decidiu quem vai em cada time (`team_assignments: [{ team_number, player_ids }]`). O controller valida (inclusive que todos os `player_ids` existem), e delega a criação para o Service.
2. **Automática** (`organizeAutomatic`): recebe só uma lista `player_ids` e o `TeamOrganizerService` distribui — primeiro goleiros, depois jogadores de linha.

As duas continuam sendo fluxos **diferentes** (não foram unificadas em uma única rota — são casos de uso legítimos e distintos), mas agora compartilham, via `TeamOrganizerService`:
- a mesma lógica de **limpar os times antigos da pelada** antes de recriar (`clearExistingTeams`, privado no Service);
- a mesma garantia de **transação** (`DB::transaction`) — antes, só o fluxo manual era transacional; o automático não era.

## Goleiro é uma posição independente do time de linha (a partir de 2026-08-19)

O goleiro **não é mais necessariamente um membro fixo de um `Team`**. `qtd_jogadores_por_time` significa explicitamente **jogadores de linha por time** — não inclui goleiro. `qtd_goleiros` continua sendo o total de goleiros da pelada (não por time). A regra de amarração depende da comparação entre `qtd_goleiros` e `qtd_times`:

- **`qtd_goleiros >= qtd_times`**: um goleiro fixo por time, anexado em `team_players` como qualquer jogador de linha (round-robin nos dois fluxos). Excedente (se `qtd_goleiros > qtd_times`) fica de reserva, não alocado em lugar nenhum.
- **`qtd_goleiros < qtd_times`**: cada goleiro vira uma `GoalPosition` (tabela nova `goal_positions`, model `App\Models\GoalPosition`) — uma posição física de gol, **independente do time de linha**. Não é inserido em `team_players`. Isso existe porque, durante o revezamento ao vivo (times de linha se revezam, goleiro permanece no mesmo gol — não há motor de "Partida" no sistema, essa dinâmica acontece fora do app), o goleiro nunca pertence a um único time de linha, então não pode ser modelado como membro de `Team`.

`TeamOrganizerService` expõe esse comportamento em dois métodos privados usados pelos dois fluxos:
- `distributeGoalkeepersAutomatically()` (organização automática) — round-robin de goleiros conforme o caso acima.
- `assignGoalkeepersManually()` (organização manual) — recebe `goalkeeperAssignments: [{position, player_id}]`, onde `position` é o `team_number` no caso `>=` ou o número da posição de gol (1..qtd_goleiros) no caso `<`.

Se mudar essa regra, ela é o único lugar que precisa mudar (usada pelos dois fluxos).

## Regras de negócio

- Antes de (re)organizar, os dois fluxos **apagam os times existentes da pelada E as `GoalPosition` da pelada** (`clearExistingTeams`, privado no Service). Times deletados de verdade (`Team` **não** usa soft delete — só `Player`/`Pelada` usam). Isso é destrutivo: não há histórico da organização anterior. Avise o usuário se for alterar esse comportamento.
- `organizeAutomatic` lança `InsufficientGoalkeepersException` (400) se `goalkeepers->count() < $pelada->qtd_goleiros`, e `InsufficientFieldPlayersException` (400) se `fieldPlayers->count() < $pelada->qtd_times * $pelada->qtd_jogadores_por_time` — as duas exceptions se renderizam sozinhas (`render()` próprio), não precisam de `try/catch` no controller.
- `Admin\TeamController::organizeManual` valida que jogadores escalados em slots de time (`team_assignments.*.player_ids`) não são goleiros, e que jogadores escalados em `goalkeeper_assignments` são de fato goleiros — retorna 400 se misturado.
- Nomes de time gerados automaticamente: `"Time {n}"` (organização automática) ou `"Time {team_number}"` (organização manual).
- Endpoints de leitura (`getPeladaTeams`, `getPeladaPlayersWithStatistics`) e as respostas de `organizeManual`/`organizeAutomatic` incluem `goal_positions` — lista de `{numero, goleiro}` (ou, em `players-with-statistics`, `{numero, id, name, nickname, position, statistics}` já mesclado com `match_players`). Vazio quando `qtd_goleiros >= qtd_times` (não há uso de `GoalPosition` nesse caso).
- **Dados antigos**: peladas organizadas antes dessa mudança continuam com o goleiro preso em `team_players` mesmo em cenários `qtd_goleiros < qtd_times` — isso só é corrigido ao reorganizar a pelada (destrutivo, como sempre foi). Não há backfill automático de `GoalPosition` para peladas antigas. `match_players` (estatísticas por jogador/pelada) nunca foi afetado — é independente de `team_players`/`goal_positions`.

## Ao alterar

- Qualquer mudança na regra de distribuição de goleiros/jogadores de linha ou na lógica de limpeza de times deve ser feita em `TeamOrganizerService` — os dois controllers (`organizeManual`/`organizeAutomatic`) não devem reimplementar essa lógica localmente.
