# .ai/project-context.md — Contexto Geral do Projeto

## O que é

**API Red Devils** — API REST em Laravel para organizar "peladas" (partidas de futebol amador), montar times e registrar estatísticas de jogadores (gols, assistências, vitórias, gols sofridos por goleiros).

## Stack

- **PHP** 8.2+
- **Laravel** 11
- **Laravel Sanctum** 4 — autenticação via token (API tokens, sem sessão/cookies para a API)
- **MySQL** 8 — `DB_CONNECTION=mysql`, banco de dados via Docker (`docker-compose.yaml` local, `docker-compose.prod.yml` produção)
- **Docker + Nginx** — ambos ambientes (dev e produção) rodam containerizados
- Sem frontend neste repositório. Sem fila assíncrona relevante em uso (queue driver = database, mas não há jobs customizados hoje).

## Modelos de dados (`app/Models/`)

| Model | Tabela | Campos principais | Relações |
|---|---|---|---|
| `User` | `users` | `name`, `username`, `password` (hashed), `profile` (`admin` ou não) | usa `HasApiTokens` (Sanctum) |
| `Player` | `players` | `name`, `nickname` (único), `position` (`linha` \| `goleiro`), `deleted_at` | `hasMany MatchPlayer`, `belongsToMany Pelada` via `match_players`. **SoftDeletes** |
| `Pelada` | `peladas` | `date`, `location`, `qtd_times`, `qtd_jogadores_por_time`, `qtd_goleiros`, `deleted_at` | `hasMany MatchPlayer`, `belongsToMany Player`, `hasMany Team`. **SoftDeletes** |
| `MatchPlayer` | `match_players` | `player_id`, `pelada_id`, `goals`, `assists`, `goals_conceded`, `result` (`win`\|`loss`\|`draw`, **NOT NULL**) | `belongsTo Player`, `belongsTo Pelada` |
| `Team` | `teams` | `pelada_id`, `name` | `belongsTo Pelada`, `belongsToMany Player` via `team_players` |
| `TeamPlayer` | `team_players` | `team_id`, `player_id` | tabela pivô de `Team` ↔ `Player` |

Ponto importante: **`User` (autenticação/admin) e `Player` (atleta cadastrado) são entidades separadas e desacopladas.** `Player` não tem login, email ou senha — é apenas um cadastro de atleta gerenciado por quem estiver autenticado como `User` admin. Isso é diferente do que o `README.md` descreve (ele fala de jogador com email/senha fazendo login e se auto-cadastrando via rota pública) — **essa parte do README está obsoleta**, não reflita esse fluxo no código.

### `Player` e `Pelada` usam soft delete

Deletar um `Player` ou uma `Pelada` pela API **não remove a linha do banco** — apenas marca `deleted_at`. Isso existe deliberadamente para não perder o histórico de `match_players`/`teams` associado (as FKs têm `cascadeOnDelete()`, e um `DELETE` físico apagaria as estatísticas de todas as peladas em que aquele jogador participou). Consequência: um `nickname` de jogador soft-deletado continua "ocupado" para a validação `unique:players,nickname` (limitação conhecida e aceita — recriar com o mesmo nickname exige restaurar o registro antigo, não criar um novo).

### `MatchPlayer.result` é o único campo de resultado

O campo legado `is_winner` (boolean) foi **removido** da tabela `match_players` — existia em paralelo com `result` (enum `win`/`loss`/`draw`) e causava lógica de fallback duplicada em vários lugares do código. Hoje `result` é obrigatório na criação (`StoreMatchPlayerRequest`) e sempre não-nulo no banco. `MatchPlayerResource` ainda expõe `is_winner` na resposta JSON, mas como **campo derivado** (`result === 'win'`), só por compatibilidade de leitura — não existe mais coluna correspondente nem deve ser aceito como input.

## Autenticação e autorização

- Login é feito com `User` (`username` + `password`), não com `Player`.
- Token gerado via Sanctum (`createToken`), enviado como `Authorization: Bearer {token}`.
- Middleware `admin` (`app/Http/Middleware/AdminMiddleware.php`) exige `$user->isAdmin()` (`profile === 'admin'`); retorna 403 caso contrário.
- Todas as rotas de escrita/gestão exigem `auth:sanctum` + `admin` (grupo `admin` em `routes/api.php`), incluindo `/admin/logout` e `/admin/me` — isso é proposital e **não foi alterado** no refactor mais recente (não existe hoje um nível intermediário de usuário autenticado não-admin).
- Rotas públicas (sem token): `POST /api/login`, leitura de `players`, `peladas` e `teams`, e todo o grupo `GET /api/statistics/*`.

## Endpoints — visão geral

Fonte de verdade: [`routes/api.php`](../routes/api.php). Detalhe completo por área em [feature-index.md](feature-index.md) e nos contexts de `.ai/backend/contexts/`.

- `POST /api/login` — autentica `User`, devolve token Sanctum.
- `GET /api/players`, `GET /api/players/{id}` — públicas, paginadas.
- `GET /api/peladas`, `GET /api/peladas/{id}`, `GET /api/peladas/date/{date}` — **públicas** (antes eram admin-only), paginadas.
- `GET /api/teams/pelada/{id}/...` (fields/players/players-with-statistics/organized) — **públicas** (antes eram admin-only).
- `GET /api/statistics/*` — rankings e estatísticas, públicas, paginadas.
- `POST /api/admin/logout`, `GET /api/admin/me` — exigem admin.
- `admin/players`, `admin/peladas`, `admin/teams/.../organize`, `admin/peladas/{id}/organize-teams`, `admin/match-players` — escrita de cada domínio, todos exigindo admin.

### Paginação

Todas as listagens (players, peladas, statistics/rankings, statistics/players/overview) aceitam `?per_page=N` (padrão 15, máximo 100) e retornam o formato padrão do Laravel paginator (`data`, `links`, `meta`).

## Deploy

- Produção roda em VPS (Ubuntu) via Docker Compose (`docker-compose.prod.yml`) + Nginx (`nginx/production.conf`).
- Deploy automatizado por push na branch `main` via GitHub Actions, que dispara `deploy.sh` no servidor (ver [docs/DEPLOY_AUTOMATICO.md](../docs/DEPLOY_AUTOMATICO.md)).
- `deploy.sh` faz: sync do código, derruba stack antiga, sobe MySQL, instala dependências, sobe `app` + `nginx`, roda migrations, recria caches.
- **Nunca** edite `deploy.sh`, `docker-compose.prod.yml`, `nginx/production.conf` ou segredos do GitHub Actions sem autorização explícita — afeta produção diretamente.

## Coisas a notar / pontos de atenção

- `README.md` tem seções de documentação de API desatualizadas — não confiar nele para payloads/rotas reais.
- Credenciais padrão do admin seed (`database/seeders/AdminUserSeeder.php`) e a ausência de expiração de token Sanctum (`config/sanctum.php`) são pontos de risco **conhecidos e ainda não corrigidos** — qualquer alteração nessa área deve ser tratada como sensível e discutida explicitamente com o usuário antes de mexer.
