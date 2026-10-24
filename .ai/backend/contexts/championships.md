# Contexto da API — Campeonato

Atualizado em 2026-10-23 com base em routes, controllers, Services, Models, migrations e testes atuais. Regras de domínio detalhadas estão em [regras-negocio-campeonato.md](../regras-negocio-campeonato.md); impacto e achados estão na [matriz](../matriz-dependencias.md).

## Objetivo e fronteiras

O módulo administra uma competição separada das peladas tradicionais: equipes e inscrições próprias, formação por Pedra, jogos, classificação, playoffs, estatísticas, odds e prêmios.

ChampionshipPlayer é uma inscrição ligada a Player global, com tipo e Pedra locais à competição. Jogos do campeonato não criam MatchPlayer. As estatísticas tradicionais e do campeonato têm fontes separadas.

## Arquivos principais

- app/Http/Controllers/ChampionshipController.php — leituras públicas e várias operações administrativas protegidas pela rota.
- app/Http/Controllers/ChampionshipVoterController.php — cadastro, identificação, status e votos PIN.
- app/Http/Controllers/Admin/ChampionshipVoterController.php — gestão administrativa de votantes.
- app/Services/ChampionshipFixtureService.php — criação de confrontos.
- app/Services/ChampionshipValidationService.php — validação do elenco para início/fixtures em draft.
- app/Services/ChampionshipStandingsService.php — tabela e desempates.
- app/Services/ChampionshipPlayoffService.php — snapshot e semifinais.
- app/Services/ChampionshipStatisticsService.php — estatísticas derivadas dos jogos.
- app/Services/ChampionshipOddsService.php — estimativas versionadas por partida.
- app/Services/ChampionshipAwardService.php — seed de categorias, encerramento e consolidação.
- app/Services/ChampionshipAuditService.php — log de operações selecionadas.
- app/Models/Championship*.php — entidades específicas do módulo.
- migrations 2026_10_19_* e 2026_10_21_000000_add_championship_voters.php; migration mais recente refina prêmios e versões de odds.
- tests/Feature/ChampionshipModuleTest.php e tests/Feature/ChampionshipWorkflowTest.php.

ChampionshipController é um desvio arquitetural existente: métodos de escrita ficam no controller raiz, mas todas as rotas administrativas estão dentro do grupo auth:sanctum + admin. Não confunda namespace com autorização efetiva.

## Rotas públicas

Todas as rotas abaixo têm prefixo runtime /api.

| Método e rota | Acesso / comportamento |
|---|---|
| GET /championships | Lista competições; atualmente sem paginação |
| GET /championships/{championship} | Detalhes, tabela, times, partidas e campeão |
| GET /championships/{championship}/awards | Categorias ativas, candidatos e resultados |
| GET /championships/{championship}/statistics | Estatísticas, filtro opcional phase |
| GET /championships/{championship}/rankings | Gols, assistências, MVP e goleiros; filtro opcional phase |
| GET /championship-teams/{team}/crest | Entrega o arquivo público do brasão |
| POST /championship-awards/{category}/votes | Voto legado; exige auth:sanctum e usuário ligado a jogador inscrito |
| GET /championships/{championship}/voting/players | Lista inscrições para cadastro/identificação PIN |
| POST /championships/{championship}/voting/voters | Cadastro PIN; throttle de rota |
| POST /championships/{championship}/voting/session | Login PIN; throttle de rota e limite adicional por IP/competição |
| GET/DELETE /championships/{championship}/voting/session | Status privado ou encerramento da sessão pelo cabeçalho X-Championship-Voter-Session |
| POST /championship-votes/{category} | Voto PIN pelo cabeçalho X-Championship-Voter-Session; throttle de rota |

## Rotas administrativas

Todas estão no grupo /api/admin com auth:sanctum e middleware admin.

| Método e rota | Operação |
|---|---|
| POST /championships | Cria competição, cinco equipes padrão e 13 categorias ativas |
| POST /championships/{championship}/players | Inscreve jogador global como linha ou goleiro |
| POST /championships/{championship}/assignments | Atribui jogadores de linha às equipes |
| POST /championships/{championship}/fixtures | Gera jogos de grupo |
| POST /championships/{championship}/odds | Calcula odds para jogos agendados da competição |
| POST /championships/{championship}/start | Valida o elenco e inicia fase de grupos |
| PUT /championship-teams/{team} | Altera nome de equipe, apenas em draft |
| POST /championships/{championship}/standings/tie-breaker | Registra decisão administrativa de desempate |
| GET /championship-users | Lista contas e vínculos globais para o voto legado |
| PUT /championship-users/{user}/player | Altera vínculo legado User/Player |
| GET /championships/{championship}/voters | Lista votantes, inscrição e categorias votadas |
| PUT /championship-voters/{voter}/player | Corrige vínculo PIN no mesmo campeonato |
| PUT /championship-voters/{voter}/pin | Redefine PIN e invalida sessão |
| POST /championships/{championship}/playoffs | Gera semifinais depois dos dez jogos de grupo finalizados |
| POST/DELETE /championship-teams/{team}/crest | Envia ou remove brasão |
| PUT /championships/{championship}/awards/voting-state | Encerra votações abertas do campeonato |
| POST /championships/{championship}/awards/publish | Consolida e publica resultados |
| PUT /championship-matches/{match} | Marca jogo finished ou cancelled e informa placar/pênaltis |
| POST /championship-matches/{match}/events | Registra evento de gol |
| PUT/DELETE /championship-events/{event} | Atualiza minuto/own_goal ou remove evento; recalcula placar |
| POST /championship-matches/{match}/participation | Substitui lista de participantes e MVP |
| POST /championship-matches/{match}/odds | Calcula nova versão de odds de uma partida |

Não foi encontrada rota para sorteio de goleiros. O cadastro da inscrição de goleiro recebe team_id e vincula esse goleiro diretamente à equipe. championship_goalkeeper_draws permanece no schema/modelo, sem uso de rota ou Service no fluxo atual.

## Setup, inscrição e início

1. store cria cinco equipes padrão e semeia 13 categorias ativas. As categorias Resenha do Campeonato e Inimigo do Gol ficam inativas por migration em competições antigas para preservar dados; novos registros não são semeados.
2. Inscrição é permitida em draft. O jogador global precisa existir e ter posição compatível. Há limite de 25 jogadores de linha e cinco goleiros. A inscrição de linha tem Pedra/grupo e classificação decimal; um goleiro precisa de team_id válido sem goleiro já vinculado.
3. assignPlayers usa exatamente as cinco equipes da competição. Para cada equipe recebe cinco inscrições de linha, uma por Pedra, sem duplicação ou inscrição externa. A troca dos vínculos de linha é transacional; a validação falha reverte a alteração.
4. validateReady exige cinco equipes, 25 inscrições de linha, cinco de goleiro, cinco linhas de Pedras distintas e um goleiro por equipe.
5. start muda status para active e phase para group, registra auditoria e não exige fixtures preexistentes.
6. generateFixtures pode ocorrer em draft (com validateReady) ou active/group. Com cinco equipes, cria dez confrontos em cinco rodadas. Se já existir jogo de grupo, a chamada retorna sem gerar de novo.

## Partidas, tabela e playoffs

- Estados persistidos de partida: scheduled, in_progress, finished, cancelled. O endpoint de atualização recebe apenas finished ou cancelled.
- Participantes são inscrições de equipes da partida. O MVP deve estar na lista. A gravação substitui as linhas de participação.
- Evento exige equipe participante do jogo. Autor, se informado, e assistente precisam estar na lista de participantes e pertencer à equipe associada. Assistente não pode ser o autor. Gol contra beneficia a equipe adversária.
- Criar, editar ou apagar evento recalcula placar a partir dos eventos. A edição de evento só permite alterar minuto e indicação own_goal.
- Apenas partidas finished de grupo entram na classificação. Vitória vale 3, empate 1, derrota 0. Desempates: pontos, vitórias, saldo, gols pró, confronto direto, decisão administrativa persistida e, por fim, id de equipe.
- Playoffs exigem dez partidas de grupo finalizadas. O serviço guarda standings_snapshot e cria 2º×5º e 3º×4º. Os vencedores criam a Final dos Desafiantes; o vencedor enfrenta o 1º colocado na Grande Final.
- Empate em fase eliminatória exige placares de pênaltis diferentes. Pênaltis não entram em gols de jogador. Final finalizada define qualified_team_id e status/phase finished.
- Após snapshot, ChampionshipStandingsService retorna a tabela salva em vez de recalcular. updateMatch não invalida esse snapshot automaticamente; correções de resultados após playoffs podem divergir da classificação congelada. Ver achado A3 na matriz.
- updateMatch não impede alteração de partida previamente finalizada/cancelada. Definir política para isso é recomendação, não validação existente.

## Odds e estatísticas

- calculateOdds aceita uma partida scheduled. calculateChampionshipOdds percorre partidas scheduled; ambas acumulam calculation_version e nunca sobrescrevem as anteriores.
- O modelo usa dados de resultados finalizados, força de elenco (Pedra e forma), defesa/gols, parâmetros de suavização e Poisson truncado. Sem histórico usa média inicial e marca preliminary como verdadeiro; não há vantagem de mando.
- Parâmetros persistidos incluem amostra, lambdas, fórmulas auxiliares e assinaturas de resultados/escalação. A tela pode sinalizar estimativa antiga. Detalhe metodológico em [championship-odds-and-awards.md](../../../docs/championship-odds-and-awards.md).
- Rankings e estatísticas usam somente jogos finalizados e podem filtrar phase. Categorias estatísticas Garçom/Artilheiro leem assistências e gols válidos.

## Votação e publicação

- Categorias ativas de votação começam abertas. Votos só são aceitos após status finished e antes de a votação ser encerrada/publicada.
- PIN tem seis dígitos, é salvo com hash. Cinco erros consecutivos bloqueiam o cadastro por 15 minutos; token de sessão aleatório tem validade de 12 horas e só seu hash é persistido.
- O cadastro público identifica uma inscrição por nome e PIN, mas não valida identidade externa.
- PIN e voto legado Sanctum compartilham a prevenção de duplicidade pela inscrição/categoria. Candidato precisa pertencer ao mesmo campeonato; auto-voto é proibido; Paredão aceita goalkeeper e Xerifão exige posição defensiva elegível.
- Administrador pode listar inscrições/categorias votadas, trocar inscrição do cadastro dentro da competição e redefinir PIN. Troca/reset invalida sessão, registra auditoria e não transfere votos existentes.
- Encerrar votação fecha as categorias ativas do tipo vote. Publicar exige campeonato finished e nenhuma categoria de voto ainda aberta. Garçom/Artilheiro são calculados das estatísticas; categorias de voto são consolidadas por candidato.

## Respostas, limites e testes

- Relacionamentos Eloquent saem em snake_case, como home_team, away_team e championship_player. Os Services Vue aceitam envelopes diferentes; consulte app-red-devils/src/services/championshipService.ts antes de ajustar contrato.
- A lista de campeonatos, categorias, estatísticas e rankings não usa paginator Laravel neste controller.
- A remoção do participante de uma partida não checa se ele permanece referenciado como autor/assistente em eventos já cadastrados. Ver achado A4.
- Soft delete de Player não é impedido por inscrição de campeonato; a relação Player pode ficar oculta em leituras. Ver achado A5.
- Testes existentes do domínio: ChampionshipModuleTest.php e ChampionshipWorkflowTest.php. Não foram executados nesta auditoria documental.
