# Context: Peladas

"Pelada" = uma partida/evento de futebol amador organizado (data, local, quantidade de times, jogadores por time, goleiros).

## Arquivos envolvidos

- `app/Http/Controllers/PeladaController.php` (leitura pública: `index` paginado, `show`, `byDate`)
- `app/Http/Controllers/Admin/PeladaController.php` (escrita: `store`, `update`, `destroy`)
- `app/Models/Pelada.php` (usa `SoftDeletes`)
- `app/Http/Requests/StorePeladaRequest.php`, `UpdatePeladaRequest.php`
- `app/Http/Resources/PeladaResource.php`
- `database/migrations/2026_03_18_123000_create_peladas_table.php`, `2026_07_12_130000_add_soft_deletes_to_players_and_peladas_tables.php`
- `database/factories/PeladaFactory.php`
- `docs/import_peladas_2026_03_26_a_2026_04_23.sql` — dump de importação histórica, não é schema fonte de verdade

## Rotas

| Rota | Auth | Controller/Método |
|---|---|---|
| `GET /api/peladas` | **pública**, paginada | `PeladaController::index` — com `matchPlayers` carregados |
| `GET /api/peladas/{id}` | **pública** | `PeladaController::show` |
| `GET /api/peladas/date/{date}` | **pública** | `PeladaController::byDate` — 404 se nenhuma encontrada |
| `POST /api/admin/peladas` | admin | `Admin\PeladaController::store` |
| `PUT /api/admin/peladas/{id}` | admin | `Admin\PeladaController::update` |
| `DELETE /api/admin/peladas/{id}` | admin | `Admin\PeladaController::destroy` (**soft delete**) |

As rotas de leitura eram admin-only antes do refactor de arquitetura; foram abertas ao público (mesmo padrão de `players`) porque um jogador comum precisa conseguir consultar a agenda de peladas e os times sem ter conta de admin. Só escrita continua exigindo admin.

## Campos e validação

- `date`: obrigatório na criação, formato `date`, e **`after_or_equal:today`** — não é possível criar pelada com data no passado. Em edição (`UpdatePeladaRequest`), `date` é `sometimes|date` **sem** essa restrição: é intencional, porque corrigir a data de uma pelada que já aconteceu é um caso de uso legítimo (ex.: corrigir um erro de digitação), e forçar `after_or_equal:today` ali bloquearia isso.
- `location`, `qtd_times`, `qtd_jogadores_por_time`, `qtd_goleiros`: obrigatórios na criação (mesmos limites de antes: `qtd_times` 2–10, `qtd_jogadores_por_time` 5–15, `qtd_goleiros` 2–10); em `UpdatePeladaRequest` são `sometimes` com os mesmos limites — **todos editáveis via `PUT`** (antes só `date` era validado/persistido em update; isso foi corrigido).

## Relações

- `Pelada belongsToMany Player` via `match_players` (pivot com `goals`, `assists`, `result`, `goals_conceded`).
- `Pelada hasMany Team`.
- `Pelada hasMany MatchPlayer` — é a relação carregada nas rotas de leitura (`PeladaResource` espera `matchPlayers`, não `players`; use exatamente esse nome no `with()`/`load()`).

## Regras de negócio

- **`Pelada` usa soft delete.** `DELETE /api/admin/peladas/{id}` marca `deleted_at`, preservando `match_players` e `teams` associados (ambos têm `cascadeOnDelete()` na FK, que só dispara em `DELETE` físico). Nunca troque por `forceDelete()` sem autorização explícita.

## Ao alterar

- Se adicionar novo campo obrigatório na criação, lembre de adicionar também em `UpdatePeladaRequest::rules()` como `sometimes` — já foi uma lacuna real neste projeto (campos existiam no `store` mas não no `update`).
