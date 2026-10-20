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

Os goleiros também possuem uma Pedra de 1 a 5, usada no cadastro do campeonato e no sorteio.

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

## 6. Sorteio dos goleiros

O sorteio exige cinco equipes e cinco goleiros inscritos. Cada goleiro é atribuído a uma equipe e o resultado fica salvo no banco.

O sorteio só pode ocorrer antes do início do campeonato. Se já houver um sorteio, um novo sorteio exige confirmação administrativa. O resultado anterior permanece no histórico, com número do sorteio, equipes, goleiros, data e responsável.

A regra especial relacionada aos jogadores de linha Pedra 1.1 e 1.5 permanece configurável, porque a correspondência exata com os goleiros não foi definida no requisito original.

## 7. Fase classificatória

A primeira fase é disputada em turno único, no formato todos contra todos.

Com cinco equipes, o sistema gera exatamente dez jogos, distribuídos em cinco rodadas. Cada equipe enfrenta todas as outras uma vez, sem confrontos repetidos, e possui uma folga por rodada.

A pontuação é:

- Vitória: 3 pontos.
- Empate: 1 ponto.
- Derrota: 0 pontos.

A tabela calcula posição, jogos, vitórias, empates, derrotas, gols marcados, gols sofridos, saldo de gols, pontos e aproveitamento.

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

O MVP precisa estar entre os participantes da partida. A quantidade de MVPs é usada nas estatísticas e rankings.

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

Cada cálculo é salvo com suas probabilidades, odds e parâmetros utilizados. O cálculo é identificado como experimental enquanto não houver histórico suficiente para calibração.

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

Cada campeonato possui quinze categorias:

- Bola de Ouro.
- Revelação do Campeonato.
- Garçom do Campeonato.
- Paredão do Campeonato.
- Xerifão do Campeonato.
- Artilheiro do Campeonato.
- Craque da Galera.
- Bagre de Ouro.
- Decepção do Campeonato.
- Inimigo do Gol.
- Pipoqueiro do Campeonato.
- Avenida do Campeonato.
- Resenha do Campeonato.
- Chato do Campeonato.
- Prêmio Felipe Melo.

Garçom e Artilheiro são calculados automaticamente pelas estatísticas. As outras categorias são definidas por votação.

Paredão aceita somente goleiros. Xerifão exige um jogador inscrito com posição defensiva elegível. As demais categorias aceitam os participantes conforme as regras gerais de votação.

## 14. Votação

A votação exige que:

- O usuário esteja autenticado.
- A conta esteja vinculada a um jogador.
- O jogador vinculado esteja inscrito no campeonato.
- A categoria seja do tipo votação.
- A votação ainda não tenha sido encerrada globalmente pelo administrador.
- A categoria ainda não esteja publicada.
- O candidato pertença ao campeonato.
- O eleitor não vote em si mesmo.
- O usuário vote no máximo uma vez por categoria.

Os votos são persistidos individualmente. As categorias permanecem disponíveis até que um administrador encerre a votação globalmente após o encerramento esportivo. Depois disso, o administrador pode publicar os resultados, que mostram vencedor, quantidade de votos e percentual de votos válidos.

## 15. Auditoria e integridade

As operações administrativas críticas possuem registro de auditoria, incluindo início do campeonato, alterações de equipe, brasões, sorteios e desempates.

O sistema protege a integridade por meio de validações de limite, relacionamentos entre campeonato e participantes, unicidade de inscrições, unicidade de Pedras, vínculos exclusivos de jogadores às equipes e vínculo exclusivo entre conta e jogador.

O cadastro global de jogadores não é excluído nem alterado quando o participante é usado em um campeonato.

## 16. Fluxo operacional

O fluxo esperado é:

1. Criar o campeonato.
2. Ajustar nomes e brasões dos times.
3. Inscrever 25 jogadores de linha e 5 goleiros.
4. Distribuir os jogadores de linha nas cinco equipes.
5. Sortear os goleiros.
6. Validar e iniciar o campeonato.
7. Gerar e finalizar os dez jogos classificatórios.
8. Gerar os playoffs.
9. Finalizar semifinais, Final dos Desafiantes e Grande Final.
10. Encerrar a votação das premiações.
11. Publicar os resultados.
12. Consultar o campeão, campanha, estatísticas e premiações no painel público.
