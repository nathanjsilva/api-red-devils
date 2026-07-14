# Context: Organização de Times

## Arquivos envolvidos

- `app/Http/Controllers/TeamController.php` — leitura pública (fields/players/organized/players-with-statistics)
- `app/Http/Controllers/Admin/TeamController.php` — escrita: `organizeManual`, `organizeAutomatic`
- `app/Services/TeamOrganizerService.php` — lógica de negócio compartilhada pelos dois fluxos
- `app/Exceptions/InsufficientGoalkeepersException.php`
- `app/Models/Team.php`, `TeamPlayer.php`, `Pelada.php`, `Player.php`
- `database/migrations/2026_03_18_125000_create_teams_table.php`, `..._create_team_players_table.php`

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

## Distribuição de goleiros (corrigida)

Antes, a organização automática distribuía **no máximo 2 goleiros por time**, valor fixo no código, independente do `qtd_goleiros` configurado na pelada. Isso foi corrigido: `TeamOrganizerService::organizeAutomatic` calcula `$goleirosPerTeam = ceil($pelada->qtd_goleiros / $pelada->qtd_times)` e distribui de forma proporcional. Se mudar essa fórmula, ela é o único lugar que precisa mudar (usada só ali).

## Regras de negócio

- Antes de (re)organizar, os dois fluxos **apagam os times existentes da pelada** (times deletados de verdade, `Team` **não** usa soft delete — só `Player`/`Pelada` usam). Isso é destrutivo: não há histórico dos times anteriores. Avise o usuário se for alterar esse comportamento.
- `organizeAutomatic` lança `InsufficientGoalkeepersException` (400) se `goalkeepers->count() < $pelada->qtd_goleiros` — a exception se renderiza sozinha (tem `render()` próprio), não precisa de `try/catch` no controller.
- Nomes de time gerados automaticamente: `"Time {n}"` (organização automática) ou `"Time {team_number}"` (organização manual).

## Ao alterar

- Qualquer mudança na regra de distribuição de goleiros/jogadores de linha ou na lógica de limpeza de times deve ser feita em `TeamOrganizerService` — os dois controllers (`organizeManual`/`organizeAutomatic`) não devem reimplementar essa lógica localmente.
