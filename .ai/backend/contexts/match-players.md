# Context: Estatísticas de Jogador na Partida (Match Players)

`MatchPlayer` é o registro pivô que guarda o desempenho de um `Player` numa `Pelada` específica: gols, assistências, gols sofridos (goleiro), e o resultado da partida para aquele jogador.

## Arquivos envolvidos

- `app/Http/Controllers/Admin/MatchPlayerController.php` (`store`, `update`, `destroy`, `upsertByPlayerAndPelada`) — único controller de match-players, todas as rotas exigem admin
- `app/Models/MatchPlayer.php`
- `app/Http/Requests/StoreMatchPlayerRequest.php`, `UpdateMatchPlayerRequest.php`
- `app/Http/Resources/MatchPlayerResource.php`
- `database/migrations/2026_03_18_124000_create_match_players_table.php`, `2026_04_30_120000_add_goalkeeper_goal_support_comments_to_match_players_table.php`, `2026_07_12_130100_consolidate_result_column_on_match_players_table.php`

O antigo controller órfão (`MatchPlayerController` no namespace raiz, que existia mas não estava roteado) foi removido no refactor de arquitetura — toda a lógica de match-players vive agora em `Admin\MatchPlayerController`, devidamente roteada.

## Rotas (todas admin)

| Rota | Método |
|---|---|
| `POST /api/admin/match-players` | `Admin\MatchPlayerController::store` |
| `PUT /api/admin/match-players/{id}` | `Admin\MatchPlayerController::update` |
| `DELETE /api/admin/match-players/{id}` | `Admin\MatchPlayerController::destroy` |
| `PUT /api/admin/peladas/{peladaId}/players/{playerId}/statistics` | `Admin\MatchPlayerController::upsertByPlayerAndPelada` — usa `updateOrCreate` por par (`player_id`, `pelada_id`) |

## Campos e validação

- `player_id`: obrigatório (create) / `sometimes` (update), deve existir em `players`.
- `pelada_id`: obrigatório (create) / `sometimes` (update), deve existir em `peladas`.
- `goals`, `assists`, `goals_conceded`: `nullable|integer|min:0`.
- `result`: **obrigatório** (`required|in:win,loss,draw`) na criação; `sometimes|in:win,loss,draw` na atualização. É o **único** campo de resultado — `is_winner` foi removido da tabela (ver abaixo).

## `result` é a única fonte de verdade (campo `is_winner` foi removido)

Até a migration `2026_07_12_130100_consolidate_result_column_on_match_players_table.php`, a tabela tinha dois campos redundantes (`is_winner` boolean legado + `result` enum), com lógica de fallback duplicada espalhada por vários arquivos (`?? ($is_winner ? 'win' : 'loss')`, inclusive em SQL bruto nos rankings). Isso foi consolidado:

- A coluna `is_winner` **não existe mais** no banco.
- `result` é `NOT NULL` no banco (default `'loss'`, usado apenas como salvaguarda de migração — todo código novo deve sempre enviar `result` explicitamente).
- `MatchPlayerResource` ainda expõe `is_winner` na resposta JSON, mas como **campo derivado** (`$this->result === 'win'`), calculado na hora, sem coluna correspondente — mantido só por compatibilidade de leitura para quem já consumia esse campo.

Se precisar adicionar um novo estado de resultado no futuro, mude apenas o enum `result` (`ENUM('win','loss','draw')` no banco + `in:win,loss,draw` nas validações) — não reintroduza um segundo campo paralelo.

## Regras de validação cruzada (`withValidator`)

- **Create** (`StoreMatchPlayerRequest`): rejeita se já existe `MatchPlayer` para o mesmo par `player_id`+`pelada_id` (jogador já registrado na pelada). Exige `goals_conceded` preenchido se o jogador é goleiro (`position === 'goleiro'`) e o campo veio `null`.
- **Update** (`UpdateMatchPlayerRequest`): lógica mais complexa — tenta resolver `player_id`/`pelada_id` tanto do corpo quanto da rota (`route('playerId')`, `route('peladaId')`, ou `route('id')` legado) para decidir se precisa exigir `goals_conceded` de goleiro. Ao mexer nesse arquivo, teste os três formatos de rota que ele suporta.

## Ao alterar

- Como `result` é `NOT NULL` no banco, não é mais necessário nenhum fallback (`?? ...`) ao ler esse campo em novos códigos — pode confiar que sempre existe um valor válido.
