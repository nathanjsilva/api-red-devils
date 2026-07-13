# Context: Peladas

"Pelada" = uma partida/evento de futebol amador organizado (data, local, quantidade de times, jogadores por time, goleiros).

## Arquivos envolvidos

- `app/Http/Controllers/AdminController.php` (`listPeladas`, `showPelada`, `getPeladasByDate`, `storePelada`, `updatePelada`, `deletePelada`)
- `app/Models/Pelada.php`
- `app/Http/Requests/StorePeladaRequest.php`, `UpdatePeladaRequest.php`
- `app/Http/Resources/PeladaResource.php`
- `database/migrations/2026_03_18_123000_create_peladas_table.php`
- `database/factories/PeladaFactory.php`
- `docs/import_peladas_2026_03_26_a_2026_04_23.sql` — dump de importação histórica, não é schema fonte de verdade

## Rotas (todas exigem admin)

| Rota | Método/Controller |
|---|---|
| `GET /api/admin/peladas` | `listPeladas` — com `players` carregados |
| `GET /api/admin/peladas/{id}` | `showPelada` |
| `GET /api/admin/peladas/date/{date}` | `getPeladasByDate` — 404 se nenhuma encontrada |
| `POST /api/admin/peladas` | `storePelada` |
| `PUT /api/admin/peladas/{id}` | `updatePelada` |
| `DELETE /api/admin/peladas/{id}` | `deletePelada` |

## Campos e validação

- `date`: obrigatório (`StorePeladaRequest`), formato `date`. Em `UpdatePeladaRequest` é `sometimes|date` — **nenhum outro campo é validado no update** (`location`, `qtd_*` não têm regra em `UpdatePeladaRequest`; se vierem no payload, passam sem validação porque `$request->validated()` só retorna o que está em `rules()`, então campos fora do array de `rules()` são ignorados por padrão do Form Request).
- `qtd_times`: obrigatório na criação, inteiro, min 2, max 10.
- `qtd_jogadores_por_time`: obrigatório na criação, inteiro, min 5, max 15.
- `qtd_goleiros`: obrigatório na criação, inteiro, min 2, max 10 — é a quantidade total de goleiros esperados na pelada (distribuída depois entre os times, ver `contexts/teams.md`).

## Relações

- `Pelada belongsToMany Player` via `match_players` (pivot com `goals`, `assists`, `is_winner`, `result`, `goals_conceded`).
- `Pelada hasMany Team`.

## Ao alterar

- `UpdatePeladaRequest` só valida `date` — se o usuário pedir para permitir editar `location`/`qtd_*`, será necessário adicionar as regras em `UpdatePeladaRequest::rules()`, hoje ausentes por omissão (provável lacuna, não decisão deliberada — confirme antes de "corrigir").
