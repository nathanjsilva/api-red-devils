# Arquitetura do sistema

Estado observado em 2026-10-23. Para uma visão modular, contratos e impactos, consulte [README](README.md), [matriz](matriz-dependencias.md) e [fluxogramas](fluxograma-aplicacao.md).

## Topologia e stack

O sistema tem dois repositórios no workspace:

- app-red-devils: SPA Vue 3, Vue Router, Pinia, Axios, Bootstrap e Chart.js, servida pelo Vite durante desenvolvimento.
- api-red-devils: API REST Laravel 11, PHP 8.2+, Sanctum e MySQL.

O frontend não faz consulta direta ao banco. Os services Axios em app-red-devils/src/services/* consomem API_BASE_URL definido em src/utils/constants.ts. Não foi confirmado estado de produção, endereço operacional ou versão do banco fora dos manifests/configuração examinados.

Não foram encontrados clientes de serviços externos de negócio nem eventos/listeners/jobs customizados. Existe armazenamento de brasões no disk public do Laravel. O schema inclui tabelas de cache e fila do framework; o código da aplicação consultado usa cache síncrono e não implementa jobs customizados.

## Caminho HTTP

~~~text
Navegador
  → View/componente Vue
  → service Axios
  → routes/api.php (/api)
  → middleware, quando configurado na rota
  → validação em Form Request ou inline
  → Controller
  → Service de domínio ou Eloquent direto
  → MySQL / disk public
  → Resource, paginator ou JSON montado pelo controller
  → service Axios / atualização da interface
~~~

### Autenticação e autorização

- POST /api/login consulta User por username, verifica password via Hash e emite token Sanctum.
- O frontend anexa Authorization usando o valor do token guardado no localStorage.
- As rotas no grupo /admin aplicam auth:sanctum e depois AdminMiddleware.
- AdminMiddleware permite apenas User::isAdmin() (profile admin). Não há Policies/Gates.
- O schema inicial de users define profile como enum contendo admin; não há papel common confirmado nas migrations. O estado de um banco externo não foi consultado.
- O voto PIN usa token de sessão próprio, hash SHA-256 persistido e expiração de 12 horas; não é token Sanctum.
- GET /admin/me e POST /admin/logout também estão no grupo admin.

### Validação, serialização e falhas

Form Requests são usados em CRUD de jogadores/peladas, registro de match-player e comparação de jogadores. Auth e parte do campeonato usam validação inline no Controller. Recomenda-se manter mensagens pt-BR e validar escopo entre entidades no backend.

Resources existem para User, Player, Pelada, MatchPlayer e Auth. O módulo de campeonato devolve principalmente arrays/modelos serializados pelo Laravel e envelopes montados diretamente. Listagens variam entre paginator e coleções sem paginação.

bootstrap/app.php registra JSON para validação, autenticação e exceções HTTP da API. Validação retorna 422, falta de credencial 401, falta de permissão admin 403 e recurso inexistente 404. O renderer genérico retorna a mensagem original da exceção no campo error também em 5xx; este comportamento é um achado de segurança, detalhado como A1 na matriz.

## Camadas e implementação por domínio

### Controllers

- Raiz: AuthController, PlayerController, PeladaController, TeamController e StatisticsController servem login ou leitura pública.
- Admin namespace: PlayerController, PeladaController, MatchPlayerController, TeamController e ChampionshipVoterController executam escrita ou gestão sob o grupo protegido.
- Exceção de organização: ChampionshipController fica no namespace raiz e reúne leituras públicas e escrita administrativa. As rotas de escrita continuam dentro do grupo admin. Não mova ou divida essa classe sem revisar rota, binding e chamadas do frontend.

### Services

- TeamOrganizerService: fluxos manual/automático; apaga a formação anterior e cria a nova dentro de DB::transaction.
- StatisticsService: consultas e agregações tradicionais; cache de 300 segundos em consultas pesadas.
- ChampionshipValidationService: pré-condições de elenco para iniciar ou gerar fixtures em draft.
- ChampionshipFixtureService: dez jogos de grupo em cinco rodadas; aceita geração em draft ou active/group.
- ChampionshipStandingsService: cálculo 3/1/0, desempates e leitura do snapshot após playoffs.
- ChampionshipPlayoffService: exige os dez jogos de grupo finalizados, salva snapshot e cria semifinais.
- ChampionshipStatisticsService: deriva números de partidas finalizadas e eventos/participações do campeonato.
- ChampionshipOddsService: Poisson por partida; somente partidas scheduled; cria versões consecutivas e preserva as anteriores.
- ChampionshipAwardService: semeia categorias, encerra votação e consolida resultados votados.
- ChampionshipAuditService: grava mudanças selecionadas em championship_audit_logs.

O sistema não usa Repositories ou interfaces de domínio. Controllers usam Models diretamente para CRUD simples e Services injetados para operações reutilizadas, multi-etapa ou transacionais.

### Modelos e persistência

O domínio de peladas usa players, peladas, match_players, teams, team_players e goal_positions. Player e Pelada aplicam SoftDeletes. MatchPlayer.result é obrigatório; is_winner é calculado no Resource, não é coluna.

O domínio de campeonato usa tabelas próprias: championships, championship_teams, championship_players, championship_team_players, championship_matches, championship_match_events, championship_match_participations, championship_odds, championship_award_categories, championship_votes, championship_award_results, championship_voters, championship_tie_breakers, championship_goalkeeper_draws e championship_audit_logs.

ChampionshipPlayer referencia o cadastro global Player, mas Pedra, tipo e equipe são próprios da inscrição. Não há vínculo entre ChampionshipMatch e MatchPlayer: suas estatísticas são separadas.

As FKs protegem existência de registros e há índices únicos para várias relações. O banco não impõe todas as invariantes compostas de mesmo campeonato; controllers validam IDs e contexto em muitos fluxos. users.player_id também não tem UNIQUE na migration, embora o controller verifique associação existente.

## Transações, cache e arquivos

- Transações estão nos fluxos de formação, inscrições/formação de campeonato, criação inicial, fixtures, playoffs, odds, registro de voto e manutenção de cadastro de votante.
- Estatísticas tradicionais usam Cache::remember com TTL de cinco minutos. Não foi encontrada invalidação pelo fluxo de escrita de match-players ou teams, portanto respostas podem ficar temporariamente antigas.
- Odds de campeonato são persistidas com calculation_version e parâmetros. A relação de partida expõe a versão mais recente; recálculos não sobrescrevem versões anteriores.
- Brasões são gravados no disk public local e servidos pelo endpoint público da API. Upload é validado por tipo/tamanho; remoção física do arquivo ocorre quando se substitui/remove o brasão.
- migrations de cache, jobs e failed_jobs são infraestrutura Laravel e não evidenciam processamento assíncrono de domínio.

## Testes presentes

A pasta tests contém uma suíte Feature para organização de goleiros, estatísticas, histórico de jogador, divisão de pelada, fluxo e módulo de campeonato. O frontend contém testes Vitest de componentes, composables e fluxo de votação. A auditoria documental não executou essas suítes. A configuração PHPUnit aponta para MySQL de teste.

## Desvios e pontos de atenção confirmados

- ChampionshipController combina leitura e escrita em um namespace; a autorização da escrita depende do grupo de rotas.
- Validação e serialização não seguem um único mecanismo em todo o domínio.
- championship_goalkeeper_draws existe como tabela/model, mas não há endpoint ou Service atual de sorteio.
- Resultados de grupo podem ser editados após playoffs e o snapshot permanece; a política dessa correção não é automatizada.
- A substituição da lista de participações não reconcilia eventos de gol já registrados.
- Soft delete de jogador inscrito em campeonato pode ocultar a relação global nas consultas de campeonato.
- A resposta 5xx inclui a mensagem interna no campo error. O seeder administrativo contém dados de bootstrap fixos; valores não são reproduzidos nesta documentação.
- Consulte todos os achados e recomendações em [matriz-dependencias.md](matriz-dependencias.md).

## Leitura antes de implementar

1. [README de contexto e ordem para IA](README.md).
2. [Regras gerais](../ai-rules.md) e [índice de funcionalidades](../feature-index.md).
3. Context do domínio em contexts/ e [regras de campeonato](regras-negocio-campeonato.md), se aplicável.
4. Código da rota, middleware, controller, Request, Service, Model, migration e Resource.
5. Service consumidor e componente/view em app-red-devils para confirmar o contrato em uso.
