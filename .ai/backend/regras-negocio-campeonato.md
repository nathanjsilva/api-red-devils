# Regras de negócio do campeonato

Este documento descreve as regras atualmente implementadas no módulo de campeonato do Red Devils.

## 1. Estrutura do campeonato

Cada campeonato possui cinco equipes. Ao criar um campeonato, são criadas automaticamente estas equipes:

1. Surubeiros FC
2. Hardworkers FC
3. Borracha Fraca FC
4. Fimose FC
5. Transformers FC

Os nomes podem ser alterados enquanto o campeonato ainda estiver em configuração. O campeonato possui os estados de configuração, andamento, playoffs e encerrado.

O cadastro global de jogadores existente no sistema é reutilizado. O campeonato não cria uma segunda ficha global para o jogador e não utiliza fotos ou avatares de jogadores.

## 2. Inscrição de jogadores

Cada jogador é inscrito individualmente no campeonato como jogador de linha ou goleiro.

As regras são:

- São permitidos exatamente 25 jogadores de linha.
- São permitidos exatamente 5 goleiros.
- O mesmo jogador não pode ser inscrito duas vezes no mesmo campeonato.
- O jogador não pode ser inscrito simultaneamente como linha e goleiro.
- A posição informada precisa ser compatível com a posição cadastrada no jogador global.
- Um jogador de linha não pode utilizar uma classificação decimal já usada no campeonato.
- A Pedra e a classificação pertencem à inscrição daquele campeonato, preservando o histórico de outros campeonatos.

## 3. Pedras

Os jogadores de linha são distribuídos em cinco grupos:

- Pedra 1: jogadores de 1.1 a 1.5.
- Pedra 2: jogadores de 2.1 a 2.5.
- Pedra 3: jogadores de 3.1 a 3.5.
- Pedra 4: jogadores de 4.1 a 4.5.
- Pedra 5: jogadores de 5.1 a 5.5.

Cada classificação decimal pode ser usada somente uma vez dentro do campeonato. A Pedra 1 representa a maior força técnica e a Pedra 5 a menor.

Os goleiros também possuem uma Pedra de 1 a 5, usada no cadastro do campeonato.

## 4. Formação das equipes

Cada equipe deve receber:

- Um jogador de linha da Pedra 1.
- Um jogador de linha da Pedra 2.
- Um jogador de linha da Pedra 3.
- Um jogador de linha da Pedra 4.
- Um jogador de linha da Pedra 5.
- Um goleiro.

Um jogador não pode pertencer a duas equipes. Uma equipe não pode ter duas Pedras do mesmo grupo. A formação só pode ser alterada antes do início do campeonato.

O campeonato não pode começar se houver menos ou mais que cinco jogadores de linha, menos ou mais que cinco goleiros, ou se alguma equipe estiver incompleta.

## 5. Brasões

Somente os times possuem imagens no módulo. O administrador pode enviar, substituir ou remover o brasão de cada equipe.

São aceitos arquivos JPG, JPEG, PNG ou WEBP, com limite de 2 MB. Quando não há brasão, a interface mostra as iniciais do nome da equipe.

## 6. Cadastro dos goleiros

Cada goleiro é atribuído a uma única equipe no próprio cadastro do campeonato. A equipe precisa pertencer ao campeonato e ainda não pode possuir outro goleiro.

O cadastro só pode ocorrer antes do início do campeonato. A mesma inscrição de goleiro não pode ser vinculada a duas equipes, e cada equipe deve terminar com exatamente um goleiro.


## 7. Fase classificatória

A primeira fase é disputada em turno único, no formato todos contra todos.

Com cinco equipes, o sistema gera exatamente dez jogos, distribuídos em cinco rodadas. Cada equipe enfrenta todas as outras uma vez, sem confrontos repetidos, e possui uma folga por rodada.

A pontuação é:

- Vitória: 3 pontos.
- Empate: 1 ponto.
- Derrota: 0 pontos.

A tabela calcula posição, jogos, vitórias, empates, derrotas, gols marcados, gols sofridos, saldo de gols, pontos e aproveitamento. Após gerar playoffs, as leituras usam o snapshot salvo, mesmo se o placar de um jogo de grupo for editado posteriormente.

O aproveitamento é calculado por:

```text
(pontos / (jogos disputados × 3)) × 100
```

Os critérios de classificação, nesta ordem, são:

1. Pontos.
2. Vitórias.
3. Saldo de gols.
4. Gols marcados.
5. Confronto direto entre as equipes empatadas.
6. Desempate administrativo registrado.

## 8. Playoffs

Depois que todos os dez jogos classificatórios forem finalizados, o administrador pode gerar os playoffs.

O formato é:

- Semifinal 1: segundo colocado contra quinto colocado.
- Semifinal 2: terceiro colocado contra quarto colocado.
- O primeiro colocado avança diretamente para a Grande Final.
- Os vencedores das semifinais disputam a Final dos Desafiantes.
- O vencedor da Final dos Desafiantes enfrenta o primeiro colocado na Grande Final.

O campeonato possui quatorze jogos no total: dez classificatórios, duas semifinais, uma Final dos Desafiantes e uma Grande Final.

Ao gerar os playoffs, a classificação da primeira fase é congelada para impedir que alterações posteriores mudem o chaveamento.

Nas partidas eliminatórias, empate no tempo regulamentar exige placar de pênaltis. O placar regulamentar e o placar dos pênaltis são armazenados separadamente, e os pênaltis não entram na artilharia.

O vencedor da Grande Final é definido como campeão e o campeonato passa para o estado encerrado.

## 9. Partidas e eventos

Cada partida possui fase, rodada, equipes, status, placar, eventos, participações, MVP e odds.

Os status disponíveis são agendado, em andamento, finalizado e cancelado.

Um gol pode conter:

- Jogador autor.
- Equipe relacionada ao evento.
- Assistência opcional.
- Minuto opcional.
- Indicação de gol contra.

O autor e a assistência devem ser participantes do jogo e pertencer à equipe indicada. Um jogador não pode dar assistência para si mesmo. Goleiros podem marcar gols.

No gol contra, o gol beneficia a equipe adversária e não conta para a artilharia do autor. Ao criar, alterar ou excluir um evento, o placar é recalculado automaticamente.

O MVP precisa estar entre os participantes da partida. A quantidade de MVPs é usada nas estatísticas e rankings. A troca da lista de participantes não verifica eventos já ligados a autores/assistentes removidos; essa inconsistência está registrada na matriz de dependências.

## 10. Estatísticas

Somente partidas finalizadas e válidas alimentam as estatísticas.

Para jogadores de linha, o sistema calcula:

- Jogos disputados.
- Vitórias, empates e derrotas.
- Gols.
- Assistências.
- Participações em gols.
- Média de gols.
- Média de assistências.
- MVPs.

Participações em gols são calculadas por:

```text
gols + assistências
```

Para goleiros, o sistema calcula também gols sofridos, média de gols sofridos, jogos sem sofrer gols, gols marcados, assistências e MVPs.

As consultas podem considerar o campeonato completo, a fase classificatória, as semifinais, a Final dos Desafiantes ou a Grande Final.

## 11. Odds

As odds são informativas e não representam apostas financeiras.

O cálculo utiliza a força das equipes derivada das Pedras dos jogadores e um modelo de Poisson para estimar gols. São calculadas as probabilidades de vitória da equipe da casa, empate e vitória da equipe visitante.

As probabilidades são normalizadas para somar 100%. A odd decimal é calculada por:

```text
1 / probabilidade decimal
```

Cada cálculo é salvo com probabilidades, odds, parâmetros, assinaturas do histórico e do elenco e um número de versão único por partida. Recalcular partida agendada cria nova versão sem sobrescrever a anterior; partidas finalizadas/canceladas não podem ser recalculadas pelo Service. O parâmetro preliminary indica histórico curto.

## 12. Rankings

O módulo disponibiliza rankings de:

- Artilheiros.
- Assistências.
- Participações em gols.
- Médias de gols e assistências.
- MVPs.
- Vitórias.
- Goleiros menos vazados.
- Média de gols sofridos por goleiros.
- Jogos sem sofrer gols.

Os rankings podem ser filtrados por fase e consideram apenas jogos finalizados.

## 13. Premiações

A seed atual cria treze categorias ativas. Duas categorias antigas ficam inativas nas competições existentes por migration, para preservar seus votos e resultados históricos, e não são disponibilizadas pela API pública.

Categorias ativas:

- Bola de Ouro — votação.
- Revelação do Campeonato — votação.
- Garçom do Campeonato — estatística de assistências.
- Paredão do Campeonato — votação, somente goleiros.
- Xerifão do Campeonato — votação, posição defensiva elegível.
- Artilheiro do Campeonato — estatística de gols válidos.
- Craque da Galera — votação.
- Bagre de Ouro — votação.
- Decepção do Campeonato — votação.
- Pipoqueiro do Campeonato — votação.
- Avenida do Campeonato — votação.
- Chato do Campeonato — votação.
- Prêmio Felipe Melo — votação.

Garçom e Artilheiro são publicados a partir das estatísticas do campeonato. As outras onze categorias recebem voto. Categorias históricas inativas continuam no banco, mas as consultas e fluxos de voto usam somente categorias ativas.

## 14. Votação

A rota de votação legada (`POST /championship-awards/{category}/votes`) exige que:

- O usuário esteja autenticado.
- A conta esteja vinculada a um jogador.
- O jogador vinculado esteja inscrito no campeonato.
- A categoria seja do tipo votação.
- A votação ainda não tenha sido encerrada globalmente pelo administrador.
- A categoria ainda não esteja publicada.
- O candidato pertença ao campeonato.
- O eleitor não vote em si mesmo.
- O usuário vote no máximo uma vez por categoria.

O cadastro simples por PIN e a sessão independente são o fluxo público recomendado e estão especificados na seção 21. Ambos os fluxos compartilham a verificação de voto anterior por inscrição.

Os votos são persistidos individualmente. As categorias permanecem disponíveis até que um administrador encerre a votação globalmente após o encerramento esportivo. Depois disso, o administrador pode publicar os resultados, que mostram vencedor, quantidade de votos e percentual de votos válidos.

## 15. Auditoria e integridade

O serviço de auditoria é chamado no início do campeonato, edição de nome/brasão de equipe, desempate, encerramento da votação e manutenção administrativa de votantes. Não há operação de sorteio de goleiros ligada às rotas atuais. A tabela championship_goalkeeper_draws existe no schema, mas não é usada por controller ou Service consultado.

O sistema aplica validações de limite, de escopo entre campeonato/equipe/participante, unicidade de inscrições, unicidade de Pedras e um time por inscrição. A exclusividade de users.player_id é verificada pelo controller, mas não há índice UNIQUE correspondente na migration.

O cadastro global de jogadores não é excluído nem alterado quando o participante é usado em um campeonato.

## 16. Fluxo operacional

As fixtures podem ser geradas em draft, depois de validar o elenco, ou em active/group. O endpoint de início valida o elenco, mas não exige que os confrontos já existam.

O fluxo esperado é:

1. Criar o campeonato.
2. Ajustar nomes e brasões dos times.
3. Inscrever 25 jogadores de linha e 5 goleiros.
4. Distribuir os jogadores de linha nas cinco equipes.
5. Inscrever cada goleiro já vinculado a uma das equipes do campeonato.
6. Validar e iniciar o campeonato.
7. Gerar e finalizar os dez jogos classificatórios.
8. Gerar os playoffs.
9. Finalizar semifinais, Final dos Desafiantes e Grande Final.
10. Encerrar a votação das premiações.
11. Publicar os resultados.
12. Consultar o campeão, campanha, estatísticas e premiações no painel público.

## 17. Entidades e integridade referencial

- `championships` é a raiz do agregado. Equipes, inscrições, partidas, categorias de premiação, desempates e auditoria pertencem a um campeonato. A tabela championship_goalkeeper_draws existe, mas não participa das rotas/Services atuais.
- `championship_players` liga um `Player` global a um campeonato e guarda tipo, Pedra, classificação decimal e posição esportiva. A inscrição não copia nem altera o cadastro global.
- `championship_team_players` liga cada inscrição a no máximo uma equipe. O vínculo de goleiro é criado junto com a inscrição; os vínculos dos jogadores de linha são substituídos pela operação de formação.
- `championship_matches` referencia duas equipes do campeonato. Eventos, participações e odds pertencem à partida; resultado e pênaltis são campos distintos.
- Eventos e participações referenciam inscrições do campeonato, não cadastros de jogador de outros campeonatos. Apagar um campeonato apaga suas entidades subordinadas por cascata; a exclusão física de jogador global inscrito é restringida pela FK; o DELETE administrativo atual usa soft delete e não é bloqueado pela inscrição, podendo ocultar a relação Player nas leituras do campeonato.
- Categorias, votos e resultados pertencem ao campeonato por meio da categoria. O voto identifica o eleitor e o candidato; resultado publicado não altera os votos individuais.
- `users.player_id` é um vínculo administrativo legado entre conta e cadastro global do jogador. Ele não substitui o cadastro simplificado de votante descrito na seção 21.

## 18. Estados, operações e pré/pós-condições

| Operação | Pré-condições | Resultado e restrições |
|---|---|---|
| Criar campeonato | Administrador autenticado; nome obrigatório com até 100 caracteres | Cria em draft/setup, cinco equipes padrão e treze categorias ativas; categorias legadas inativas são preservadas. |
| Inscrever atleta | Campeonato `draft`; atleta global existente; tipo compatível; limite e classificação disponíveis | Cria uma inscrição; goleiro exige equipe do mesmo campeonato ainda sem goleiro. |
| Formar equipes | Campeonato `draft`; cinco equipes próprias e cinco inscrições de linha distintas por equipe | Em uma transação substitui os vínculos de linha; falha de validação preserva a formação anterior. Goleiros permanecem vinculados. |
| Iniciar | Campeonato `draft`; cinco equipes completas, 25 atletas de linha (uma Pedra por equipe) e cinco goleiros (um por equipe) | Muda para `active`/`group` e registra auditoria. |
| Gerar primeira fase | Cinco equipes; em draft exige roster válido, ou em active/group aceita a fase de grupos | Cria dez partidas únicas em cinco rodadas; se já houver partida de grupo, retorna sem criar outra. |
| Finalizar partida | Partida existente; placares inteiros não negativos; desempate por pênaltis em mata-mata empatado | Atualiza placar/status; vitória/empate alimenta tabela. A final concluída encerra o campeonato. |
| Gerar playoffs | Campeonato na fase de grupos; dez partidas de grupo concluídas | Salva snapshot da classificação e cria `2º x 5º` e `3º x 4º`; chamadas repetidas não recriam semifinais. |
| Registrar evento/participação | Atleta inscrito em uma das equipes da partida; evento associado à equipe válida | Recalcula o placar pelos gols e gols contra; MVP e eventos só podem usar participantes válidos. |
| Encerrar votação | Administrador autenticado; campeonato esportivamente encerrado | Fecha globalmente as categorias de voto ainda abertas e registra auditoria. |
| Publicar prêmios | Campeonato encerrado; nenhuma categoria de voto permanece aberta | Calcula Garçom/Artilheiro, consolida votos e torna resultados públicos. |

Estados de campeonato persistidos: `draft`, `active`, `playoffs` e `finished`; fases: `setup`, `group`, `playoffs` e `finished`. Partidas persistem `scheduled`, `in_progress`, `finished` ou `cancelled`. A API de atualização de partida aceita atualmente `finished` e `cancelled`; não se deve afirmar que há uma operação pública para marcar `in_progress` sem que ela seja implementada.

## 19. Acesso e superfície administrativa/pública

- Leituras de campeonatos, detalhes, tabela, partidas, estatísticas, rankings e categorias são públicas.
- A criação e gestão de equipes, inscrições, formação, partidas, eventos, participações, odds, desempates, votação e premiações exige `auth:sanctum` e perfil `admin`, salvo a ação de votar.
- O fluxo de votação existente aceita conta Sanctum vinculada a atleta inscrito; o cadastro simples por PIN é adicionado na seção 21 e é independente da autenticação administrativa.
- Brasões aceitam JPG/JPEG/PNG/WEBP até 2 MB. Troca e remoção alteram o arquivo público associado à equipe; a troca registra auditoria.
- Erros de validação retornam HTTP 422; falta de autenticação administrativa retorna 401; perfil não administrador retorna 403; recurso inexistente retorna 404.
- A área pública não expõe resultados de prêmio enquanto `published=false`. A votação termina apenas quando o administrador encerra as categorias; encerramento esportivo e encerramento de votação são operações distintas.

## 20. Critérios verificáveis do módulo existente

1. Criar campeonato administrativo gera cinco equipes e treze categorias ativas (onze de votação, abertas inicialmente, e duas estatísticas automáticas); duas categorias antigas podem permanecer inativas em campeonatos existentes.
2. Inscrição repetida, tipo incompatível, classificação duplicada/incompatível, equipe externa ou limite excedido é rejeitado sem duplicar dados.
3. Uma formação válida tem cinco jogadores de linha, uma Pedra de cada grupo, em cada equipe; uma tentativa inválida não apaga a formação existente.
4. Os dez confrontos de grupo cobrem cada par de equipes uma única vez; cada rodada contém dois jogos e uma folga.
5. Apenas partidas finalizadas alimentam classificação e estatísticas. Pontuação é 3/1/0; gols contra favorecem o adversário e não contam como gol do autor; pênaltis não contam na artilharia.
6. Playoffs não podem ser criados com partida de grupo pendente; ao criá-los, o snapshot não muda depois que resultados de grupo são editados.
7. Participantes, autores, assistentes e MVP precisam pertencer às equipes da partida; assistente não pode ser o autor. Uma edição/exclusão de evento recalcula o placar.
8. Odds são informativas, retornam probabilidades normalizadas e parâmetros do Poisson utilizado.
9. Voto em categoria fechada/publicada, candidato externo, auto-voto ou repetição deve ser rejeitado; categoria Paredão exige goleiro e Xerifão exige posição defensiva elegível.
10. Resultados de votação não são públicos antes da publicação e as operações administrativas não são acessíveis a visitante ou conta comum.

## 21. Cadastro simplificado de votantes (PIN)

Esta seção descreve o novo acesso do módulo de premiações, separado de `users`, email, telefone e login administrativo.

### Cadastro, identificação e sessão

- O cadastro existe por campeonato e por inscrição `championship_players`: cada inscrição pode ter no máximo um votante, garantido por índice `UNIQUE` no banco.
- A tela pública oferece os nomes dos jogadores inscritos no campeonato. O participante escolhe a própria inscrição, informa um PIN numérico de exatamente seis dígitos e o confirma.
- O servidor valida o campeonato/inscrição e persiste somente o hash seguro do PIN. Não coleta email, telefone, senha de conta ou verificação externa. Cadastro não comprova a identidade real do jogador.
- Um cadastro existente impede um segundo cadastro para a mesma inscrição. O participante se identifica selecionando o nome e fornecendo o PIN.
- Após PIN correto, a API emite uma sessão aleatória válida por 12 horas; o banco guarda apenas o hash do token. Identificação e status de votos usam esse token validado, nunca um ID de eleitor enviado livremente pelo cliente.
- PIN incorreto incrementa o contador de tentativas. Após cinco erros consecutivos, o cadastro fica bloqueado por 15 minutos. Uma identificação válida zera o contador. O cliente não recebe hash nem informação que permita enumerar PINs.
- Encerrar/redefinir sessão ou alterar vínculo administrativo invalida a sessão corrente. Uma nova identificação recupera os votos anteriores associados ao mesmo cadastro.

### Votos, categorias e concorrência

- O votante precisa estar identificado por sessão válida; deve pertencer ao campeonato da categoria e a categoria deve estar aberta, não publicada e ser do tipo `vote`.
- Cada inscrição pode registrar no máximo um voto por categoria e campeonato. A validação é feita no backend e a unicidade é garantida no banco; requisições concorrentes não criam dois votos.
- O candidato deve pertencer ao mesmo campeonato, ser elegível para a categoria e não ser a própria inscrição do votante. Paredão aceita goleiro; Xerifão aceita `defensor`, `zagueiro`, `fixo` ou `ala`.
- O status privado do votante lista categorias disponíveis e categoria já votada. Depois de votar, a categoria mostra **Voto realizado** e fica bloqueada para aquele votante; as demais categorias abertas continuam disponíveis.
- A sessão de votação é independente de `auth:sanctum` usado por administradores. Rotas de gestão de votantes são sempre protegidas por `auth:sanctum` e `admin`.

### Gestão administrativa e histórico

- Administrador pode listar cadastros, inscrição/jogador vinculado e categorias votadas; não pode consultar PIN em texto nem seu hash.
- Administrador pode redefinir o PIN e trocar o vínculo para outra inscrição do mesmo campeonato. O novo jogador não pode estar associado a outro cadastro.
- Ao trocar o vínculo, a API registra no log de auditoria campeonato, cadastro, vínculo antigo, vínculo novo, administrador e horário. Votos anteriores permanecem ligados ao cadastro e não são excluídos, transferidos ou reescritos.
- Conflito de campeonato ou jogador já vinculado bloqueia a operação com explicação; a mudança bem-sucedida aparece na próxima identificação.

### Critérios de aceitação E2E para votantes

1. Cadastro com PIN válido cria um único votante; PIN curto, não numérico ou confirmação divergente é rejeitado.
2. Segundo cadastro para a mesma inscrição é rejeitado inclusive sob concorrência.
3. PIN correto permite entrar e recuperar status; PIN incorreto é rejeitado e cinco erros acionam bloqueio temporário.
4. Sem sessão, com token expirado ou com token inválido, não é possível consultar status privado nem votar.
5. Uma sessão pode votar em categorias distintas; repetição da mesma categoria é rejeitada pelo backend e índice único mesmo em chamadas simultâneas.
6. Candidato de outro campeonato, auto-voto, posição inelegível, categoria estatística, fechada ou publicada é rejeitado.
7. Administrador consegue localizar votante, trocar vínculo dentro do campeonato e redefinir PIN; jogador já vinculado ou pertencente a outro campeonato é rejeitado.
8. A troca registra auditoria e preserva votos já realizados; novo PIN autentica e o PIN anterior deixa de autenticar.
9. Usuário sem perfil admin não consegue listar ou alterar cadastros.
10. Encerramento, apuração e publicação seguem as regras das seções 13–15; o cadastro por PIN não permite publicar nem alterar votos.
