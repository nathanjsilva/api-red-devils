# Context: Organização de Times

## Arquivos envolvidos

- `app/Http/Controllers/TeamController.php`
- `app/Http/Controllers/AdminController.php::organizeTeams`
- `app/Models/Team.php`, `TeamPlayer.php`, `Pelada.php`, `Player.php`
- `database/migrations/2026_03_18_125000_create_teams_table.php`, `..._create_team_players_table.php`

## Rotas (todas em `routes/api.php`, grupo `admin`)

| Rota | Controller/Método | Propósito |
|---|---|---|
| `GET /api/admin/teams/pelada/{peladaId}/fields` | `TeamController::getTeamFields` | Lista os "slots" de time (`time_1`..`time_N`) conforme `qtd_times` da pelada |
| `GET /api/admin/teams/pelada/{peladaId}/players` | `TeamController::getPeladaPlayers` | Jogadores ainda **não** registrados como `MatchPlayer` nessa pelada |
| `GET /api/admin/teams/pelada/{peladaId}/players-with-statistics` | `TeamController::getPeladaPlayersWithStatistics` | Jogadores da pelada + estatísticas + time atual |
| `GET /api/admin/teams/pelada/{peladaId}/organized` | `TeamController::getPeladaTeams` | Times já montados, com jogadores |
| `POST /api/admin/teams/pelada/{peladaId}/organize` | `TeamController::organizePlayers` | Organização **manual**: recebe `team_assignments` explícitos |
| `POST /api/admin/peladas/{peladaId}/organize-teams` | `AdminController::organizeTeams` | Organização **automática**: recebe só `player_ids`, distribui goleiros e linha automaticamente |

## Duas lógicas de organização — não confundir

1. **`TeamController::organizePlayers`** (`POST .../teams/pelada/{id}/organize`): o cliente já decidiu quem vai em cada time (`team_assignments: [{ team_number, player_ids }]`). O controller apenas valida, apaga times antigos da pelada e recria dentro de uma transação (`DB::beginTransaction`/`commit`/`rollBack`).
2. **`AdminController::organizeTeams`** (`POST .../peladas/{id}/organize-teams`): recebe só uma lista `player_ids` e distribui automaticamente — primeiro goleiros (até 2 por time, na ordem em que vêm no array), depois jogadores de linha (`floor(count / qtd_times)` por time). **Não usa transação** (diferente do método 1). Se `goalkeepers->count() < $pelada->qtd_goleiros`, retorna erro 400 antes de criar qualquer time.

Ao editar organização de times, confirme com o usuário **qual dos dois fluxos** ele quer alterar — são independentes e não compartilham código.

## Regras de negócio

- Antes de (re)organizar, os dois fluxos **apagam os times existentes da pelada** (`Team::where('pelada_id', ...)->get()` → `detach()` + `delete()`). Isso é destrutivo: não há histórico dos times anteriores. Avise o usuário se for alterar esse comportamento.
- `organizeTeams` (automático) distribui no máximo 2 goleiros por time, mesmo que `qtd_goleiros` da pelada seja diferente disso — o número `2` está hardcoded em `AdminController::organizeTeams` (`min(2, ...)`). Isso pode não bater com `qtd_goleiros` configurado na pelada; é um comportamento existente, não corrigir sem confirmar com o usuário.
- Nomes de time gerados automaticamente: `"Time {n}"` (organização automática) ou `"Time {team_number}"` (organização manual).

## Ao alterar

- Qualquer mudança na regra de distribuição de goleiros/jogadores de linha deve ser replicada (ou deliberadamente divergida) nos dois controllers — deixe claro ao usuário se a intenção é unificar os dois fluxos.
