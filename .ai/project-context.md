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
| `Player` | `players` | `name`, `nickname` (único), `position` (`linha` \| `goleiro`) | `hasMany MatchPlayer`, `belongsToMany Pelada` via `match_players` |
| `Pelada` | `peladas` | `date`, `location`, `qtd_times`, `qtd_jogadores_por_time`, `qtd_goleiros` | `hasMany MatchPlayer`, `belongsToMany Player`, `hasMany Team` |
| `MatchPlayer` | `match_players` | `player_id`, `pelada_id`, `goals`, `assists`, `goals_conceded`, `is_winner`, `result` (`win`\|`loss`\|`draw`) | `belongsTo Player`, `belongsTo Pelada` |
| `Team` | `teams` | `pelada_id`, `name` | `belongsTo Pelada`, `belongsToMany Player` via `team_players` |
| `TeamPlayer` | `team_players` | `team_id`, `player_id` | tabela pivô de `Team` ↔ `Player` |

Ponto importante: **`User` (autenticação/admin) e `Player` (atleta cadastrado) são entidades separadas e desacopladas.** `Player` não tem login, email ou senha — é apenas um cadastro de atleta gerenciado por quem estiver autenticado como `User` admin. Isso é diferente do que o `README.md` descreve (ele fala de jogador com email/senha fazendo login e se auto-cadastrando via rota pública) — **essa parte do README está obsoleta**, não reflita esse fluxo no código.

## Autenticação e autorização

- Login é feito com `User` (`username` + `password`), não com `Player`.
- Token gerado via Sanctum (`createToken`), enviado como `Authorization: Bearer {token}`.
- Middleware `admin` (`app/Http/Middleware/AdminMiddleware.php`) exige `$user->isAdmin()` (`profile === 'admin'`); retorna 403 caso contrário.
- **Hoje, praticamente todas as rotas de escrita/gestão exigem `auth:sanctum` + `admin`** (grupo `admin` em `routes/api.php`), incluindo `/admin/logout` e `/admin/me`. Não existe usuário "comum" autenticado sem ser admin atualmente — veja [feature-index.md](feature-index.md) e [backend/contexts/auth.md](backend/contexts/auth.md).
- Rotas públicas (sem token): `POST /api/login`, `GET /api/players`, `GET /api/players/{id}`, e todo o grupo `GET /api/statistics/*`.

## Endpoints — visão geral

Fonte de verdade: [`routes/api.php`](../routes/api.php). Detalhe completo por área em [feature-index.md](feature-index.md) e nos contexts de `.ai/backend/contexts/`.

- `POST /api/login` — autentica `User`, devolve token Sanctum.
- `GET /api/players`, `GET /api/players/{id}` — públicas.
- `GET /api/statistics/*` — rankings e estatísticas, públicas.
- `POST /api/admin/logout`, `GET /api/admin/me` — exigem admin.
- `admin/players`, `admin/peladas`, `admin/teams/...`, `admin/match-players` — CRUD de cada domínio, todos exigindo admin.

## Deploy

- Produção roda em VPS (Ubuntu) via Docker Compose (`docker-compose.prod.yml`) + Nginx (`nginx/production.conf`).
- Deploy automatizado por push na branch `main` via GitHub Actions, que dispara `deploy.sh` no servidor (ver [docs/DEPLOY_AUTOMATICO.md](../docs/DEPLOY_AUTOMATICO.md)).
- `deploy.sh` faz: sync do código, derruba stack antiga, sobe MySQL, instala dependências, sobe `app` + `nginx`, roda migrations, recria caches.
- **Nunca** edite `deploy.sh`, `docker-compose.prod.yml`, `nginx/production.conf` ou segredos do GitHub Actions sem autorização explícita — afeta produção diretamente.

## Coisas a notar / pontos de atenção

- Existe uma migration não versionada em `git status` (`2026_04_30_..._add_goalkeeper_goal_support_comments_to_match_players_table.php`) — trabalho em andamento do usuário; não sobrescrever nem descartar sem perguntar.
- `README.md` tem seções de documentação de API desatualizadas — não confiar nele para payloads/rotas reais.
