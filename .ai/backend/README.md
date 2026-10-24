# API Red Devils — contexto técnico para desenvolvimento

Auditoria documental feita em 2026-10-23 a partir do código dos repositórios `api-red-devils` e `app-red-devils`. Este diretório é o ponto de entrada para entender a implementação antes de propor mudanças. As rotas e o código atual prevalecem sobre descrições antigas do README geral ou de documentos de produto.

## Aplicação e stack

O Red Devils é uma SPA Vue que consome uma API REST Laravel. O backend administra usuários, jogadores, peladas, formação de times, estatísticas e campeonatos. O módulo Campeonato é um agregado separado das peladas: usa inscrições próprias vinculadas a jogadores globais, partidas, eventos, odds, categorias e votos próprios.

| Parte | Stack declarada nos manifests | Entrada |
|---|---|---|
| API | PHP `^8.2`, Laravel `^11.31`, Sanctum `^4.1`, MySQL | `api-red-devils/routes/api.php`, `bootstrap/app.php` |
| Frontend | Vue `^3.5.13`, Pinia `^3.0.3`, Vue Router `^4.5.1`, Axios `^1.11.0`, Bootstrap `^5.3.7`, Chart.js `^4.5.0`, Vite `^6.3.5`, Vitest `^3.2.4` | `app-red-devils/src/main.js`, `src/router/index.js` |
| Transporte | JSON por HTTP; Axios no frontend | `app-red-devils/src/services/api.ts` |
| Arquivos | Brasões salvos no disk `public` do Laravel | `ChampionshipController::uploadCrest` e `showCrest` |

As versões são faixas declaradas em `composer.json` e `package.json`, não uma afirmação sobre o estado de produção. O backend usa MySQL; há migrations com SQL MySQL específico. O frontend não tem SSR. Não foram encontradas integrações de negócio externas, eventos ou jobs customizados. Tabelas Laravel de cache e filas não provam uso de jobs.

## Arquitetura e limites

Fluxo HTTP típico: rota → middleware de autenticação/autorização → validação → controller → service ou Eloquent → Resource ou JSON direto. Não há Repository, Policies ou Gates. Services concentram operações com vários passos, cálculos reutilizados e transações; CRUD simples costuma ficar no controller.

Há exceções reais à convenção: `ChampionshipController` contém leituras públicas e escritas administrativas, embora as escritas estejam protegidas pelo grupo admin. Parte da validação de campeonato é inline no controller, em vez de Form Request. As respostas do campeonato são JSON direto, sem Resources próprios.

No frontend, as views chamam services Axios; eles não substituem validações do backend. `src/services/api.ts` injeta o token Sanctum do `localStorage`, usa timeout de 30 segundos e centraliza toasts HTTP. Votação por PIN usa sessão independente de Sanctum no cabeçalho `X-Championship-Voter-Session`.

## Mapa dos módulos

| Módulo | Responsabilidade, dados e regras | Rotas / implementação | Dependências e consumo no frontend |
|---|---|---|---|
| Autenticação e administração | Autentica `User`; token Sanctum; perfil verificado por middleware | `POST /api/login`, `GET /api/admin/me`, `POST /api/admin/logout`; `AuthController`, `AdminMiddleware` | Protege escrita dos demais módulos. Front: `authService.ts`, `stores/auth.ts`, `router/index.js` |
| Jogadores | Cadastro global em `players`, posição e apelido; soft delete | `GET /api/players[/{id}]`; CRUD em `/api/admin/players`; controllers público/admin, `Player` | Fonte de inscrições de campeonato, peladas, times e estatísticas. Front: `playerService.ts`, `adminService.ts` |
| Peladas | Evento com data, divisão, local e limites; soft delete | GET `/api/peladas`, `/date/{date}`, `/{id}`; CRUD admin `/api/admin/peladas`; `Pelada`, Requests | Une jogadores às estatísticas tradicionais e times. Front: `peladaService.ts`, `AdminPeladasView.vue` e telas de pelada |
| Times de pelada | Organização manual/automática e posições de goleiro | GET `/api/teams/pelada/{id}/*`; POST admin `/api/admin/teams/pelada/{id}/organize` e `/api/admin/peladas/{id}/organize-teams`; `TeamOrganizerService` | Usa `Pelada`, `Player`, `Team`, `TeamPlayer`, `GoalPosition`. Front: `teamService.ts`, `AdminOrganizeTeamsView.vue` |
| Estatísticas por pelada | Gols, assistências, gols sofridos e resultado individual | Admin `/api/admin/match-players/*` e upsert por pelada/jogador; `Admin\MatchPlayerController`, `MatchPlayer`, Requests, Resource | Fonte dos rankings tradicionais. Front: `adminService.ts`, `AdminMatchPlayersView.vue` |
| Estatísticas e rankings | Agregações de `match_players`, `players`, `peladas`, `teams` e `team_players` | `/api/statistics/*`; `StatisticsController`, `StatisticsService` | Dashboard, histórico, rankings, gráficos e comparador. Front: `statisticsService.ts`, `rankingService.ts`, `stores/rankings.ts` |
| Campeonato | Competição de cinco equipes, inscrições próprias, classificação, partidas, eliminatórias, odds e prêmios | Público `/api/championships/*`; escrita em `/api/admin/championships/*`; `ChampionshipController` e Services | Participantes são `ChampionshipPlayer`, não `MatchPlayer`. Front: `championshipService.ts`, `ChampionshipView.vue`, `AdminChampionshipView.vue` |
| Votação por PIN | Cadastro por inscrição, hash de PIN, sessão temporária, voto único por categoria | Público `/api/championships/{id}/voting/*` e voto `/api/championship-votes/{category}`; `ChampionshipVoterController` | Compartilha `championship_votes` com o voto legado. Front: `ChampionshipVoterPanel.vue` |
| Gestão de votantes | Consulta, troca de vínculo, redefinição de PIN | Admin `/api/admin/championships/{id}/voters` e `/api/admin/championship-voters/{id}/{player,pin}`; `Admin\ChampionshipVoterController` | Afeta sessão/histórico. Front: `AdminChampionshipVoters.vue` |
| Brasões | Upload, remoção e entrega de imagem | GET `/api/championship-teams/{team}/crest`; upload/remove admin | Caminho em `championship_teams.crest_path`; arquivo no disk público local |

### Regras e relações que cruzam módulos

- `User` e `Player` são entidades distintas. `users.player_id` é vínculo legado para voto Sanctum; votação por PIN usa `championship_voters`.
- `Player` e `Pelada` usam soft delete. As linhas e FKs ficam no banco, mas consultas Eloquent comuns ocultam o registro removido logicamente.
- Estatísticas tradicionais vêm de `match_players`; estatísticas de campeonato vêm de `championship_matches`, `championship_match_events` e `championship_match_participations`. São fontes distintas.
- Reorganizar pelada apaga `teams`, vínculos `team_players` e `goal_positions` anteriores dentro de transação; não apaga `match_players`.
- Placar de campeonato pode ser recalculado pelos eventos. A tabela usa jogos finalizados.
- Gerar playoffs salva `standings_snapshot`. Em `playoffs` ou `finished`, classificação passa a vir desse snapshot.
- Odds são versões persistidas por partida. Recálculo cria nova versão; o serviço só calcula partidas agendadas.
- Categorias inativas permanecem no banco para preservar histórico. Leituras públicas e votos filtram `is_active = true`.

### Rotas da SPA verificadas

Rotas públicas: /login, /home, /players-overview, /players/:id, /peladas, /peladas/:id, /estatisticas e /campeonatos. Rotas admin: /admin/players, /admin/peladas, /admin/match-players, /admin/organize-teams e /admin/championships. / redireciona para /home. Componentes e services por módulo estão listados em app-red-devils/.ai/frontend/overview.md e contexts específicos.
## Superfície HTTP

`routes/api.php` é a fonte de verdade. Laravel aplica o prefixo `/api`.

- Sem token: leitura de jogadores, peladas, times, estatísticas, campeonatos e brasões; login; cadastro/identificação/status/voto PIN.
- Sanctum: voto legado em `POST /api/championship-awards/{category}/votes`.
- Sanctum e perfil admin: grupo `/api/admin/*`, inclusive `GET /admin/me` e `POST /admin/logout`.
- Rota de saúde: `/up`.
- Nem todas as coleções são paginadas. O formato de resposta varia: alguns CRUDs usam JsonResource/paginator, outros endpoints devolvem arrays, modelos ou envelopes próprios.

Antes de alterar o parsing do frontend, compare controller e service consumidor. Erros são normalizados em `bootstrap/app.php`; leia esse renderer ao mudar o contrato de erro.

## Regras principais confirmadas

- `players.nickname` é único; `players.position` aceita `linha` ou `goleiro`.
- `match_players.result` é obrigatório e é a fonte de resultado; `is_winner` é derivado no Resource.
- Campeonato: cinco equipes, 25 inscrições de linha, cinco goleiros; cada equipe precisa de cinco Pedras distintas e um goleiro para iniciar.
- Fase de grupos: dez jogos em turno único, pontuação 3/1/0. Playoffs requerem os dez jogos finalizados.
- Gols/assistências de campeonato referenciam inscrições; pênaltis não entram na artilharia e gol contra pontua para a equipe adversária.
- Duas categorias antigas ficam inativas por migration. Campeonatos novos recebem 13 categorias ativas; Garçom e Artilheiro são estatísticos e as demais são votadas.
- Voto PIN requer campeonato finalizado, categoria ativa/aberta/não publicada, sessão válida, candidato elegível e ausência de voto anterior/auto-voto.
- O banco não impõe todas as relações compostas entre campeonato, equipe, inscrição e partida; os endpoints validam vários desses vínculos no nível da aplicação.

## Documentação detalhada

- [Arquitetura](architecture.md) · [Padrões](coding-standards.md) · [Regras de alteração](LEIA-ME.md)
- [Regras do campeonato](regras-negocio-campeonato.md) · [Contexto da API do campeonato](contexts/championships.md)
- [Autenticação](contexts/auth.md) · [Jogadores](contexts/players.md) · [Peladas](contexts/peladas.md)
- [Times](contexts/teams.md) · [Estatísticas por partida](contexts/match-players.md) · [Rankings](contexts/statistics.md) · [Permissões](contexts/admin.md)
- [Matriz de dependências e achados](matriz-dependencias.md) · [Fluxogramas](fluxograma-aplicacao.md)
- [Metodologia de odds e prêmios](../../docs/championship-odds-and-awards.md)
- [Índice geral](../feature-index.md) · [Contexto geral](../project-context.md)

## Como uma IA deve compreender este projeto

1. Leia este README e [as regras gerais](../ai-rules.md).
2. Confira arquitetura, contratos e dependências em [architecture.md](architecture.md), [matriz-dependencias.md](matriz-dependencias.md) e [fluxograma-aplicacao.md](fluxograma-aplicacao.md).
3. Leia o context da funcionalidade e as regras de campeonato quando aplicável.
4. Confirme rotas em `routes/api.php`, validação no controller/Request/Service, persistência em Models/migrations e consumo em `app-red-devils/src/services`.
5. Avalie impactos na matriz e atualize a documentação quando um fato mudar.
6. Trate código como descrição do comportamento atual. Não use documentação antiga, dados de produção ou comportamento externo como prova.
7. Separe fatos, limitações e recomendações; não descreva uma recomendação como se já estivesse implementada.

## Escopo e limitações

Foram analisados routes, controllers, middleware, Requests, Resources, Models, Services, migrations, seeders, testes existentes, manifests e rotas/services do frontend. Não foram lidos arquivos de ambiente, dados de produção, nem executadas migrations ou chamadas externas. Os testes da aplicação não foram executados nesta auditoria documental. Links locais foram conferidos estaticamente; renderização requer visualizador Markdown compatível com Mermaid.
