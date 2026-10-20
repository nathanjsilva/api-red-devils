# Contexto: Campeonato

Este contexto pertence exclusivamente à API Laravel. A especificação detalhada das regras está em [`../regras-negocio-campeonato.md`](../regras-negocio-campeonato.md); este arquivo organiza a arquitetura, os contratos HTTP e os pontos que devem ser lidos antes de alterar o módulo.

## Objetivo

O módulo administra campeonatos independentes das peladas tradicionais: cinco equipes, inscrições próprias de jogadores de linha e goleiros, formação por Pedra, turno único, playoffs, eventos de partidas, estatísticas, odds informativas, premiações, votação e auditoria.

## Arquivos envolvidos

- `app/Http/Controllers/ChampionshipController.php` — leitura pública e operações administrativas do campeonato.
- `app/Models/Championship.php` — campeonato e relações com equipes, participantes, partidas, sorteios, desempates, auditoria e premiações.
- `app/Models/ChampionshipTeam.php` — equipes, brasões e participantes.
- `app/Models/ChampionshipPlayer.php` — inscrição do jogador global no campeonato, com tipo e Pedra próprios.
- `app/Models/ChampionshipMatch.php` — partidas, placares, pênaltis, eventos, participações e odds.
- `app/Models/ChampionshipMatchEvent.php` — gols, assistências, equipe do evento, minuto e gol contra.
- `app/Models/ChampionshipMatchParticipation.php` — participantes e MVP.
- `app/Models/ChampionshipAwardCategory.php`, `ChampionshipAwardResult.php`, `ChampionshipVote.php` — premiações, resultados e votos.
- `app/Models/ChampionshipGoalkeeperDraw.php`, `ChampionshipTieBreaker.php`, `ChampionshipAuditLog.php` — sorteios, desempates e auditoria.
- `app/Services/ChampionshipFixtureService.php` — dez jogos de turno único em cinco rodadas.
- `app/Services/ChampionshipValidationService.php` — validação da configuração antes do início.
- `app/Services/ChampionshipStandingsService.php` — classificação e critérios de desempate.
- `app/Services/ChampionshipPlayoffService.php` — congelamento da classificação e geração dos playoffs.
- `app/Services/ChampionshipStatisticsService.php` — estatísticas por jogador e fase.
- `app/Services/ChampionshipOddsService.php` — probabilidades e odds com modelo Poisson.
- `app/Services/ChampionshipAwardService.php` — categorias, consolidação e publicação de premiações.
- `app/Services/GoalkeeperDrawService.php` — sorteio persistido e auditável dos goleiros.
- `database/migrations/2026_10_19_120000_create_championship_module_tables.php`
- `database/migrations/2026_10_19_121000_create_championship_awards_and_user_player_link.php`
- `tests/Feature/ChampionshipModuleTest.php`

## Rotas públicas

| Método | Rota | Resultado |
|---|---|---|
| `GET` | `/championships` | Lista campeonatos |
| `GET` | `/championships/{championship}` | Detalhes, classificação, equipes, partidas e campeão |
| `GET` | `/championships/{championship}/awards` | Categorias, candidatos e resultados publicados |
| `GET` | `/championships/{championship}/statistics` | Estatísticas individuais, com filtro opcional `phase` |
| `GET` | `/championships/{championship}/rankings` | Rankings de gols, assistências, MVP e goleiros, com filtro opcional `phase` |
| `POST` | `/championship-awards/{category}/votes` | Voto de participante autenticado |

A votação exige `auth:sanctum`; as demais rotas acima são públicas.

## Rotas administrativas

Todas ficam no grupo `middleware(['auth:sanctum', 'admin'])->prefix('admin')`.

| Método | Rota | Responsabilidade |
|---|---|---|
| `POST` | `/admin/championships` | Cria campeonato, cinco equipes padrão e quinze categorias |
| `POST` | `/admin/championships/{championship}/players` | Inscreve jogador global como linha ou goleiro |
| `POST` | `/admin/championships/{championship}/assignments` | Forma as equipes com uma Pedra de cada grupo |
| `POST` | `/admin/championships/{championship}/fixtures` | Gera os dez jogos da primeira fase |
| `POST` | `/admin/championships/{championship}/start` | Valida configuração e inicia competição |
| `PUT` | `/admin/championship-teams/{team}` | Edita nome da equipe |
| `POST` | `/admin/championships/{championship}/standings/tie-breaker` | Registra sorteio de desempate |
| `GET` | `/admin/championship-users` | Lista contas para vínculo com jogador |
| `PUT` | `/admin/championship-users/{user}/player` | Vincula conta autenticada a jogador global |
| `POST` | `/admin/championships/{championship}/playoffs` | Gera semifinais e congela classificação |
| `POST` | `/admin/championships/{championship}/goalkeepers/draw` | Sorteia ou refaz sorteio dos goleiros |
| `POST/DELETE` | `/admin/championship-teams/{team}/crest` | Envia ou remove brasão |
| `POST` | `/admin/championships/{championship}/awards/publish` | Consolida e publica premiações |
| `PUT` | `/admin/championships/{championship}/awards/voting-state` | Encerra todas as votações do campeonato |
| `PUT` | `/admin/championship-matches/{match}` | Finaliza/cancela partida e registra pênaltis |
| `POST` | `/admin/championship-matches/{match}/events` | Cria gol, assistência ou gol contra |
| `PUT/DELETE` | `/admin/championship-events/{event}` | Altera/remove evento e recalcula placar |
| `POST` | `/admin/championship-matches/{match}/participation` | Salva participantes e MVP |
| `POST` | `/admin/championship-matches/{match}/odds` | Calcula e persiste odds informativas |

## Regras invariáveis

- Cada campeonato possui cinco equipes, 25 jogadores de linha e cinco goleiros.
- Cada equipe recebe cinco jogadores de linha, um de cada Pedra, e um goleiro.
- Inscrições, classificações e vínculos pertencem ao campeonato; o cadastro global de jogadores não é duplicado nem alterado.
- Um jogador não pode ser inscrito duas vezes, nem pertencer a duas equipes.
- A fase de grupos tem dez partidas, cinco rodadas e pontuação 3/1/0.
- O desempate segue pontos, vitórias, saldo, gols marcados, confronto direto e decisão administrativa persistida.
- Os playoffs são `2º x 5º`, `3º x 4º`, Final dos Desafiantes e Grande Final; a classificação é congelada ao gerar os playoffs.
- Empate em eliminatória exige pênaltis. Pênaltis não entram na artilharia.
- Eventos só podem usar participantes da partida; assistência deve ser de companheiro e não pode ser o próprio autor.
- Gol contra beneficia a equipe adversária e não conta como gol do autor.
- Estatísticas consideram apenas partidas finalizadas e válidas.
- Odds são informativas, calculadas por Poisson e persistidas com os parâmetros do cálculo; não há apostas financeiras.
- Existem quinze premiações. Artilheiro e Garçom são automáticos; as outras categorias usam votação.
- Voto fica disponível até o encerramento global pelo administrador, exige conta autenticada vinculada a jogador inscrito, um voto por categoria e impede auto-voto.

## Respostas e serialização

- Controllers de leitura retornam JSON direto; o frontend aceita também envelopes `{ data: ... }` por compatibilidade geral.
- Relacionamentos Eloquent são serializados em `snake_case`, como `home_team`, `away_team`, `goalkeeper_draws` e `championship_player`.
- `GET /championships/{championship}` retorna `championship`, `standings`, `matches` e `champion`.
- `GET /championships/{championship}/rankings` retorna `scorers`, `assists`, `mvp` e `goalkeepers`.
- `GET /championships/{championship}/statistics` retorna linhas com jogador, posição, jogos, resultados, gols, assistências, participações, MVP e métricas de goleiro.

## Auditoria, testes e cuidados

- Início, alterações de equipe, brasões, sorteios e desempates devem continuar auditáveis.
- Não editar `deploy.sh`, `docker-compose.prod.yml`, `nginx/production.conf` ou segredos para alterar este módulo.
- Não executar migrations que alterem dados sem autorização explícita.
- O teste base do módulo está em `tests/Feature/ChampionshipModuleTest.php`; alterações em regras de fixture, classificação, odds, inscrição, votação ou eventos devem incluir cobertura correspondente.
- Antes de alterar regra de negócio, comparar o código com `regras-negocio-campeonato.md` e confirmar o impacto nos contratos consumidos pelo frontend.
