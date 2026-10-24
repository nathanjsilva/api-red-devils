# Fluxogramas da aplicação RED DEVILS

Diagramas baseados nas rotas, controllers, services, models e migrations consultados em 2026-10-23. São diagramas de comportamento presente. Pontos conhecidos e recomendações estão separados na [matriz de dependências](matriz-dependencias.md).

## A. Visão geral da arquitetura

O prefixo /api é aplicado pelo roteador Laravel. O grupo administrativo passa por autenticação Sanctum e middleware admin. As rotas públicas e de PIN não passam por esse grupo; o voto legado exige Sanctum.

~~~mermaid
flowchart LR
  PublicUser["Usuário público"]
  AdminUser["Administrador"]
  Browser["Navegador"]
  Vue["SPA Vue 3<br/>views, componentes e stores"]
  Axios["Services Axios<br/>token Sanctum ou sessão PIN"]
  Routes["Laravel routes/api.php"]
  Auth{"Rota exige Sanctum?"}
  Admin{"Rota exige perfil admin?"}
  Validate["Validação<br/>Form Request ou inline"]
  Controller["Controller"]
  Service["Service de domínio<br/>quando existe"]
  Eloquent["Eloquent Models"]
  MySQL[("MySQL")]
  Storage["Disk public<br/>brasões"]
  Json["JSON / Resource / paginator"]
  Error["Renderer JSON<br/>401, 403, 422 ou 5xx"]

  PublicUser --> Browser
  AdminUser --> Browser
  Browser --> Vue --> Axios --> Routes
  Routes --> Auth
  Auth -->|"não"| Validate
  Auth -->|"sim, token válido"| Admin
  Auth -->|"sem token ou inválido"| Error
  Admin -->|"não"| Validate
  Admin -->|"sim"| Validate
  Admin -->|"sem perfil admin"| Error
  Validate --> Controller
  Controller --> Service
  Controller --> Eloquent
  Service --> Eloquent
  Eloquent --> MySQL
  Controller --> Storage
  MySQL --> Eloquent
  Storage --> Controller
  Eloquent --> Json
  Controller --> Json --> Axios
  Controller --> Error --> Axios
  Axios --> Vue --> Browser
~~~

### Legenda

- Services são usados em lógica de vários passos, transacional ou agregada; CRUD simples pode acessar Eloquent direto.
- Não há Repository, Policy/Gate nem eventos/jobs de domínio customizados confirmados.
- O fluxo do campeonato é uma exceção à convenção de controller: leituras públicas e escritas administrativas ficam em ChampionshipController; a proteção das escritas vem da rota.
- A votação por PIN não usa token Sanctum. Ela apresenta um token de sessão próprio no cabeçalho X-Championship-Voter-Session.
- Respostas de exceção são configuradas em bootstrap/app.php. Respostas 5xx hoje incluem a mensagem original no campo error; ver achado A1 na matriz.

## B. Módulos e relações entre módulos

Os blocos clássico e Campeonato consultam jogadores globais, mas mantêm fontes distintas para partidas e estatísticas.

~~~mermaid
flowchart TB
  Users["users<br/>login admin e vínculo legado player_id"]
  Players["players<br/>cadastro global"]
  Peladas["peladas<br/>data, divisão e local"]
  MatchPlayers["match_players<br/>estatística por jogador/pelada"]
  Teams["teams + team_players<br/>times de pelada"]
  GoalPositions["goal_positions<br/>posição independente de goleiro"]
  TeamRows[("teams, team_players, goal_positions")]
  ClassicStats["StatisticsService<br/>rankings, histórico e dashboard"]

  Championships["championships"]
  Registrations["championship_players<br/>inscrição e Pedra"]
  ChampionshipTeams["championship_teams<br/>elenco e brasão"]
  Matches["championship_matches"]
  Events["championship_match_events"]
  Participations["championship_match_participations"]
  Odds["championship_odds"]
  Awards["categories, votes e results"]
  Voters["championship_voters<br/>PIN e sessão"]
  Standings["tabela e snapshot de playoffs"]

  Players --> MatchPlayers
  Peladas --> MatchPlayers
  Players --> Teams
  Peladas --> Teams
  Peladas --> GoalPositions
  MatchPlayers --> ClassicStats
  Teams --> ClassicStats

  Players --> Registrations
  Championships --> Registrations
  Championships --> ChampionshipTeams
  Registrations --> ChampionshipTeams
  Championships --> Matches
  ChampionshipTeams --> Matches
  Matches --> Events
  Matches --> Participations
  Registrations --> Events
  Registrations --> Participations
  Matches --> Odds
  Matches --> Standings
  ChampionshipTeams --> Standings
  Championships --> Awards
  Awards --> Voters
  Registrations --> Voters
  Voters --> Awards
  Users --> Awards
~~~

Relações e efeitos detalhados estão no diagrama ER e na matriz. A caixa de cadastro global Player não deve ser confundida com ChampionshipPlayer, que tem Pedra, tipo e inscrição por competição.

## C. Fluxo de peladas, registro e estatísticas

~~~mermaid
flowchart TD
  Admin["Admin abre gestão de pelada"]
  Create["POST /admin/peladas<br/>Form Request valida data, divisão e limites"]
  PeladaRow[("Criar/editar soft delete em peladas")]
  Organize["POST organize manual ou automático"]
  ValidatePlayers{"IDs e posições válidos?<br/>quantidade suficiente?"}
  Rollback["Erro JSON; transação revertida"]
  Clear["Apagar teams antigos,<br/>team_players e goal_positions"]
  Build["Criar times e vínculos<br/>distribuir goleiro conforme qtd"]
  TeamRows[("teams, team_players e goal_positions")]
  SaveStats["POST/PUT match-players<br/>ou upsert por par jogador/pelada"]
  ValidateStats{"Jogador, pelada e result válidos?<br/>goleiro tem goals_conceded?"}
  MatchRows[("Gravar em match_players")]
  Query["GET /statistics/*"]
  Cache["StatisticsService agrega<br/>cache de até 5 min"]
  Views["Dashboard, rankings,<br/>perfil e telas de pelada"]

  Admin --> Create --> PeladaRow
  Admin --> Organize --> ValidatePlayers
  ValidatePlayers -->|"não"| Rollback
  ValidatePlayers -->|"sim"| Clear --> Build --> TeamRows
  Admin --> SaveStats --> ValidateStats
  ValidateStats -->|"não"| Rollback
  ValidateStats -->|"sim"| MatchRows
  TeamRows --> Views
  PeladaRow --> Query
  MatchRows --> Query --> Cache --> Views

~~~

A transação de organização remove a organização anterior e cria a nova. O modo manual recebe a distribuição escolhida pelo administrador; o automático divide jogadores de linha e trata goleiros como membro fixo dos times quando qtd_goleiros >= qtd_times ou como GoalPosition independente quando qtd_goleiros < qtd_times. Mudanças nas estatísticas tradicionais podem demorar até o TTL para aparecer nos agregados em cache.

## D. Ciclo de vida do campeonato

~~~mermaid
flowchart TD
  Start["Admin cria campeonato"]
  Seed["Transação: criar 5 equipes<br/>e 13 categorias ativas"]
  Register["Inscrever 25 jogadores de linha<br/>e 5 goleiros"]
  Formation["Atribuir uma Pedra de cada grupo<br/>e um goleiro por equipe"]
  Ready{"Roster completo<br/>e sem duplicidades?"}
  Fix["Corrigir inscrições ou formação"]
  FixturesBefore["Opcional: gerar fixtures em draft<br/>valida roster antes de gerar"]
  StartLeague["Iniciar: status active,<br/>phase group e auditoria"]
  FixturesAfter["Gerar fixtures em active/group"]
  GroupMatches["10 partidas de grupo<br/>em 5 rodadas"]
  Odds["Gerar/recalcular odds<br/>somente jogos agendados"]
  PlayoffsReady{"Os 10 jogos foram<br/>finalizados?"}
  Standings["Calcular tabela<br/>e salvar standings_snapshot"]
  Semis["Semifinais:<br/>2º x 5º e 3º x 4º"]
  Challenger["Após duas semifinais finalizadas:<br/>criar Final dos Desafiantes"]
  Final["Após Final dos Desafiantes:<br/>criar Grande Final contra 1º"]
  Champion["Final finalizada:<br/>status/phase finished"]
  Voting["Votação pode ser usada;<br/>sessão PIN ou voto legado"]
  Close["Admin encerra as categorias"]
  Publish["Admin publica resultados<br/>e cálculos de Artilheiro/Garçom"]

  Start --> Seed --> Register --> Formation --> Ready
  Ready -->|"não"| Fix --> Register
  Ready -->|"sim"| FixturesBefore --> StartLeague
  Ready -->|"sim, sem gerar antes"| StartLeague
  StartLeague --> FixturesAfter --> GroupMatches
  GroupMatches --> Odds
  GroupMatches --> PlayoffsReady
  PlayoffsReady -->|"não"| GroupMatches
  PlayoffsReady -->|"sim"| Standings --> Semis --> Challenger --> Final --> Champion --> Voting --> Close --> Publish
~~~

O serviço permite gerar fixtures em draft (depois de validar a configuração) ou em active/group. O endpoint de início valida a composição, mas não exige confrontos já gerados. A inscrição de goleiro recebe team_id diretamente; não foi encontrado sorteio operacional ligado à tabela championship_goalkeeper_draws.

## E. Registro de eventos e efeitos de uma partida

~~~mermaid
sequenceDiagram
  actor Admin
  participant UI as AdminChampionshipView
  participant API as ChampionshipController
  participant DB as MySQL
  participant Table as ChampionshipStandingsService

  Admin->>UI: Marca participantes e MVP
  UI->>API: POST participation
  API->>API: Exige partida scheduled e participantes das equipes do jogo
  API->>DB: Substitui championship_match_participations em transação
  DB-->>UI: Lista de participações

  Admin->>UI: Registra gol, assistência ou gol contra
  UI->>API: POST championship-matches/{id}/events
  API->>API: Valida equipe, autor/assistente participante e elegibilidade
  API->>DB: Cria championship_match_events
  API->>DB: Recalcula home_score/away_score a partir dos eventos
  DB-->>UI: Evento criado e placar atualizado

  Admin->>UI: Finaliza a partida e envia placar de pênaltis se necessário
  UI->>API: PUT championship-matches/{id}
  API->>API: Exige status finished ou cancelled
  alt Eliminatória empatada no tempo regulamentar
    API->>API: Exige pênaltis com vencedor
  else Grupo ou placar não empatado
    API->>DB: Persiste placar e status
  end
  API->>DB: Define qualified_team_id e cria fase seguinte quando aplicável
  API->>Table: Tabela de grupo é consultada para classificar ou definir 1º lugar
  Table-->>API: Classificação calculada ou snapshot
  API-->>UI: Partida atualizada

  Note over API,DB: Ao gerar playoffs, a tabela fica congelada em standings_snapshot.
  Note over API,DB: A API aceita editar/cancelar partida já finalizada; a correção não invalida o snapshot automaticamente.
~~~

Gols contra contam para o placar do adversário e não para o total de gols do autor. Pênaltis definem o classificado, mas não entram na estatística de gols. A atualização da lista de participantes não remove automaticamente eventos que apontem para inscrições retiradas; ver achado A4.

## F. Sequência do cálculo e versionamento de odds

~~~mermaid
sequenceDiagram
  actor Admin
  participant UI as AdminChampionshipView
  participant API as ChampionshipController
  participant Odds as ChampionshipOddsService
  participant DB as MySQL
  participant Public as ChampionshipView

  Admin->>UI: Calcula jogo ou próximas partidas
  UI->>API: POST admin match odds ou championship odds
  API->>Odds: calculate(match) ou calculateUpcoming(championship)
  Odds->>DB: Bloqueia partidas scheduled e carrega elencos
  Odds->>DB: Busca partidas finished do campeonato como histórico
  Odds->>Odds: Estima lambdas de gols e Poisson até o limite configurado
  Odds->>Odds: Normaliza probabilidades e calcula odd decimal
  Odds->>DB: Insere nova calculation_version e parâmetros
  DB-->>Public: show lê a versão mais recente da partida
  Public->>Public: Assinaturas ajudam a indicar estimativa desatualizada
  Odds-->>UI: Retorna resultado/versionamento
~~~

O recálculo cria novo registro; versões anteriores não são sobrescritas pelo serviço. A explicação das fontes, parâmetros e limitações está em [championship-odds-and-awards.md](../../docs/championship-odds-and-awards.md).

## G. Votação por PIN e publicação

~~~mermaid
sequenceDiagram
  actor Participante
  actor Admin
  participant UI as ChampionshipVoterPanel
  participant AdminUI as AdminChampionshipView
  participant API as ChampionshipVoterController
  participant AdminAPI as ChampionshipController
  participant DB as MySQL

  Participante->>UI: Escolhe inscrição e cadastra PIN de 6 dígitos
  UI->>API: POST voting/voters, sem Sanctum
  API->>API: Confere inscrição do campeonato e PIN confirmado
  API->>DB: Persiste pin_hash e sessão com hash e expiração
  API-->>UI: Token bruto uma vez e resumo do votante
  UI->>UI: Guarda token em sessionStorage do navegador

  Participante->>UI: Faz login em sessão existente ou consulta status
  UI->>API: POST/GET voting/session com X-Championship-Voter-Session
  API->>DB: Valida hash, prazo, falhas e lockout
  API-->>UI: Categorias ativas e status privado

  Participante->>UI: Escolhe categoria e candidato
  UI->>API: POST championship-votes/{category} com token de sessão
  API->>API: Confere campeonato encerrado, categoria aberta e elegibilidade
  API->>DB: Verifica duplicidade e grava voto transacional
  API-->>UI: Voto registrado; categoria passa a has_voted

  Admin->>AdminUI: Encerra categorias de votação
  AdminUI->>AdminAPI: PUT voting-state
  API->>DB: Fecha categorias e registra auditoria
  Admin->>AdminUI: Publica resultados
  AdminUI->>AdminAPI: POST awards/publish
  API->>DB: Consolida votos e calcula categorias estatísticas
  AdminAPI-->>AdminUI: Retorna resultados publicados
~~~

Cadastro por PIN não comprova identidade real: qualquer pessoa que selecione uma inscrição sem cadastro anterior pode criar o PIN. O backend aplica limite por IP/campeonato no login e bloqueio temporário após cinco erros do PIN. O voto legado autenticado verifica o vínculo users.player_id e grava na mesma tabela de votos.

## H. Entidades e relacionamentos do banco

O diagrama mostra as tabelas de domínio e a tabela Sanctum relevante. Tabelas framework de cache, sessões de navegador, jobs e failed jobs foram omitidas. Relações condicionais do domínio também precisam de validação de aplicação: FKs individuais não comprovam, por exemplo, que time, inscrição e partida pertencem ao mesmo campeonato.

~~~mermaid
erDiagram
  USERS {
    bigint id PK
    bigint player_id FK "nullable, sem unique no schema"
    string username UK
    string profile
  }
  PERSONAL_ACCESS_TOKENS {
    bigint id PK
    string tokenable_type
    bigint tokenable_id
    string token UK
  }
  PLAYERS {
    bigint id PK
    string name
    string nickname UK
    string position
    datetime deleted_at
  }
  PELADAS {
    bigint id PK
    date date
    string division
    string location
    datetime deleted_at
  }
  MATCH_PLAYERS {
    bigint id PK
    bigint player_id FK
    bigint pelada_id FK
    integer goals
    integer assists
    integer goals_conceded
    string result
  }
  TEAMS {
    bigint id PK
    bigint pelada_id FK
    string name
  }
  TEAM_PLAYERS {
    bigint id PK
    bigint team_id FK
    bigint player_id FK
  }
  GOAL_POSITIONS {
    bigint id PK
    bigint pelada_id FK
    bigint goleiro_id FK
    integer numero
  }

  CHAMPIONSHIPS {
    bigint id PK
    string name
    string status
    string phase
    json standings_snapshot
  }
  CHAMPIONSHIP_TEAMS {
    bigint id PK
    bigint championship_id FK
    string name
    string crest_path
  }
  CHAMPIONSHIP_PLAYERS {
    bigint id PK
    bigint championship_id FK
    bigint player_id FK
    string player_type
    integer stone_group
    decimal stone_level
  }
  CHAMPIONSHIP_TEAM_PLAYERS {
    bigint id PK
    bigint championship_team_id FK
    bigint championship_player_id FK
  }
  CHAMPIONSHIP_MATCHES {
    bigint id PK
    bigint championship_id FK
    bigint home_team_id FK
    bigint away_team_id FK
    string phase
    string status
    integer home_score
    integer away_score
    integer home_penalty_score
    integer away_penalty_score
  }
  CHAMPIONSHIP_MATCH_EVENTS {
    bigint id PK
    bigint match_id FK
    bigint scorer_id FK "nullable"
    bigint assist_id FK "nullable"
    bigint team_id FK
    boolean own_goal
  }
  CHAMPIONSHIP_MATCH_PARTICIPATIONS {
    bigint id PK
    bigint match_id FK
    bigint championship_player_id FK
    boolean mvp
  }
  CHAMPIONSHIP_ODDS {
    bigint id PK
    bigint match_id FK
    integer calculation_version
    decimal home_probability
    decimal draw_probability
    decimal away_probability
    json parameters
  }
  CHAMPIONSHIP_AWARD_CATEGORIES {
    bigint id PK
    bigint championship_id FK
    string name
    string type
    boolean is_active
    boolean voting_open
    boolean published
  }
  CHAMPIONSHIP_VOTERS {
    bigint id PK
    bigint championship_id FK
    bigint championship_player_id FK
    string pin_hash
    string session_token_hash
    datetime session_expires_at
  }
  CHAMPIONSHIP_VOTES {
    bigint id PK
    bigint championship_award_category_id FK
    bigint voter_id FK "nullable legacy user"
    bigint championship_voter_id FK "nullable PIN account"
    bigint voter_championship_player_id FK "nullable"
    bigint candidate_id FK
  }
  CHAMPIONSHIP_AWARD_RESULTS {
    bigint id PK
    bigint championship_award_category_id FK
    bigint winner_id FK "nullable"
    integer votes
    decimal percentage
  }
  CHAMPIONSHIP_TIE_BREAKERS {
    bigint id PK
    bigint championship_id FK
    bigint team_a_id FK
    bigint team_b_id FK
    bigint winner_id FK
    bigint resolved_by FK "nullable user"
  }
  CHAMPIONSHIP_GOALKEEPER_DRAWS {
    bigint id PK
    bigint championship_id FK
    bigint team_id FK
    bigint championship_player_id FK
    bigint drawn_by FK "nullable user"
    integer draw_number
  }
  CHAMPIONSHIP_AUDIT_LOGS {
    bigint id PK
    bigint championship_id FK
    bigint user_id FK "nullable"
    string action
    string auditable_type
    bigint auditable_id
    json payload
  }

  USERS }o--o| PLAYERS : "legacy player link; not unique in DB"
  USERS ||--o{ PERSONAL_ACCESS_TOKENS : "Sanctum tokens"
  PLAYERS ||--o{ MATCH_PLAYERS : "traditional statistics"
  PELADAS ||--o{ MATCH_PLAYERS : "traditional statistics"
  PELADAS ||--o{ TEAMS : "has"
  TEAMS ||--o{ TEAM_PLAYERS : "has"
  PLAYERS ||--o{ TEAM_PLAYERS : "plays on"
  PELADAS ||--o{ GOAL_POSITIONS : "has"
  PLAYERS ||--o{ GOAL_POSITIONS : "goalkeeper"

  CHAMPIONSHIPS ||--o{ CHAMPIONSHIP_TEAMS : "has"
  CHAMPIONSHIPS ||--o{ CHAMPIONSHIP_PLAYERS : "registers"
  PLAYERS ||--o{ CHAMPIONSHIP_PLAYERS : "global player"
  CHAMPIONSHIP_TEAMS ||--o{ CHAMPIONSHIP_TEAM_PLAYERS : "roster"
  CHAMPIONSHIP_PLAYERS ||--o{ CHAMPIONSHIP_TEAM_PLAYERS : "roster"
  CHAMPIONSHIPS ||--o{ CHAMPIONSHIP_MATCHES : "schedule"
  CHAMPIONSHIP_TEAMS ||--o{ CHAMPIONSHIP_MATCHES : "home team"
  CHAMPIONSHIP_TEAMS ||--o{ CHAMPIONSHIP_MATCHES : "away team"
  CHAMPIONSHIP_MATCHES ||--o{ CHAMPIONSHIP_MATCH_EVENTS : "events"
  CHAMPIONSHIP_PLAYERS o|--o{ CHAMPIONSHIP_MATCH_EVENTS : "scorer"
  CHAMPIONSHIP_PLAYERS o|--o{ CHAMPIONSHIP_MATCH_EVENTS : "assister"
  CHAMPIONSHIP_TEAMS ||--o{ CHAMPIONSHIP_MATCH_EVENTS : "event team"
  CHAMPIONSHIP_MATCHES ||--o{ CHAMPIONSHIP_MATCH_PARTICIPATIONS : "lineup"
  CHAMPIONSHIP_PLAYERS ||--o{ CHAMPIONSHIP_MATCH_PARTICIPATIONS : "participates"
  CHAMPIONSHIP_MATCHES ||--o{ CHAMPIONSHIP_ODDS : "calculation versions"
  CHAMPIONSHIPS ||--o{ CHAMPIONSHIP_AWARD_CATEGORIES : "categories"
  CHAMPIONSHIP_AWARD_CATEGORIES ||--o{ CHAMPIONSHIP_VOTES : "receives"
  USERS o|--o{ CHAMPIONSHIP_VOTES : "legacy voter"
  CHAMPIONSHIP_VOTERS o|--o{ CHAMPIONSHIP_VOTES : "PIN voter"
  CHAMPIONSHIP_PLAYERS ||--o{ CHAMPIONSHIP_VOTES : "candidate or PIN voter identity"
  CHAMPIONSHIP_AWARD_CATEGORIES ||--o{ CHAMPIONSHIP_AWARD_RESULTS : "published results"
  CHAMPIONSHIP_PLAYERS o|--o{ CHAMPIONSHIP_AWARD_RESULTS : "winner"
  CHAMPIONSHIPS ||--o{ CHAMPIONSHIP_VOTERS : "voting access"
  CHAMPIONSHIP_PLAYERS ||--o| CHAMPIONSHIP_VOTERS : "one voter per registration"
  CHAMPIONSHIPS ||--o{ CHAMPIONSHIP_TIE_BREAKERS : "resolves"
  CHAMPIONSHIP_TEAMS ||--o{ CHAMPIONSHIP_TIE_BREAKERS : "team pair/winner"
  USERS o|--o{ CHAMPIONSHIP_TIE_BREAKERS : "resolved by"
  CHAMPIONSHIPS ||--o{ CHAMPIONSHIP_GOALKEEPER_DRAWS : "legacy draw history"
  CHAMPIONSHIP_TEAMS ||--o{ CHAMPIONSHIP_GOALKEEPER_DRAWS : "drawn for"
  CHAMPIONSHIP_PLAYERS ||--o{ CHAMPIONSHIP_GOALKEEPER_DRAWS : "goalkeeper"
  USERS o|--o{ CHAMPIONSHIP_GOALKEEPER_DRAWS : "drawn by"
  CHAMPIONSHIPS ||--o{ CHAMPIONSHIP_AUDIT_LOGS : "audit"
  USERS o|--o{ CHAMPIONSHIP_AUDIT_LOGS : "actor"
~~~

### Notas de cardinalidade e integridade

- Índices únicos relevantes incluem nickname global, inscrição por campeonato/jogador, pedra decimal por campeonato, um time por inscrição, participação por partida/inscrição, versão por partida, cadastro PIN por campeonato/inscrição e voto por categoria/identidade.
- users.player_id é opcional e não tem UNIQUE em migration; a exclusividade é verificada pelo controller em fluxo normal, sem garantia equivalente para gravação concorrente ou fora da API.
- championship_team_players não usa FK composta que garanta que time e inscrição são do mesmo campeonato. Os endpoints de formação validam escopo.
- championship_matches liga-se a duas equipes por FKs, mas o schema não exige que sejam equipes de championship_id daquela partida.
- championship_goalkeeper_draws está no schema, mas não foi encontrado uso por rota/Service no código atual.
- Soft deletes não apagam os relacionamentos físicos. Para Player e Pelada, consultas Eloquent comuns ocultam os registros com deleted_at.
