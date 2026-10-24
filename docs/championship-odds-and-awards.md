# Campeonato: odds e premiações

## Decisões e regras

- As odds são estimativas informativas; não há apostas nem margem de casa.
- A geração de confrontos pode ocorrer enquanto o campeonato ainda está em rascunho, depois que os cinco elencos forem validados. Isso mantém a configuração em rascunho e não inicia partidas.
- O administrador calcula ou recalcula todas as partidas agendadas em `POST /api/admin/championships/{championship}/odds`. A rota individual continua disponível em `POST /api/admin/championship-matches/{match}/odds`.
- Cada cálculo cria uma nova linha com `calculation_version`. O índice único `(match_id, calculation_version)` impede duplicar uma versão. A relação pública retorna a versão mais recente.
- Só partidas `scheduled` entram no cálculo. Partidas finalizadas ou canceladas mantêm seu histórico de odds sem alteração. Resultados concluídos e elencos alterados são registrados em assinaturas no parâmetro de cálculo; o público recebe um aviso quando uma estimativa agendada ficou desatualizada.
- O serviço carrega confrontos, elencos e histórico de uma vez por operação; resultados, eventos e participações são carregados em lote para evitar consultas por jogador ou partida.

## Modelo e dados usados

O serviço combina os dados já registrados no campeonato:

- Gols a favor e contra em partidas finalizadas.
- Vitórias, empates e derrotas anteriores da equipe.
- Confrontos diretos finalizados, com peso pequeno e suavizado.
- Nota do elenco: jogadores de linha usam `6 - stone_level`; goleiros usam `6 - stone_group`.
- Quando disponíveis, participações anteriores, gols sem gol contra, assistências e MVP para jogadores de linha; vitórias, gols sofridos e jogos sem sofrer gol para goleiros.

Não há vantagem fictícia de mando. Uma equipe sem histórico usa a mesma média neutra de gols que a adversária e uma forma individual neutra. A escalação e as notas dos jogadores ainda podem diferenciar elencos antes da primeira partida.

## Fórmulas

Se existem `n` partidas finalizadas, a média-base por equipe é:

```text
league_average = max(0.35, gols totais / (2 * n))
```

Sem nenhuma partida finalizada, a premissa inicial documentada é `1.25` gols por equipe.

As médias de ataque e defesa são suavizadas com três partidas fictícias na média-base, reduzindo oscilações em amostras pequenas:

```text
rate = (gols_observados + 3 * league_average) / (partidas_observadas + 3)
```

As notas são transformadas em fatores multiplicativos:

```text
field_quality = média(6 - stone_level)
goalkeeper_quality = 6 - stone_group
field_factor(q) = exp((q - 3) * 0.035)
keeper_factor(q) = exp((3 - q) * 0.07)
```

Quando há confronto direto, sua taxa suavizada recebe peso adicional de meia partida. A força recente das equipes também considera pontos por partida, em uma correção limitada a 15%:

```text
outcome_adjustment = clamp(1 + 0.12 * (home_points_share - away_points_share), 0.85, 1.15)
points_share = pontos / (3 * partidas)
```

A nota de jogadores de linha e goleiros gera fatores exponenciais próximos de 1. A forma individual, quando há participação registrada, usa:

```text
field_impact = clamp(
  0.20 * (points_share - 0.5)
  + 0.08 * gols_por_jogo
  + 0.06 * assistências_por_jogo
  + 0.08 * MVPs_por_jogo,
  -0.20, 0.20
)
keeper_impact = clamp(
  0.15 * (points_share - 0.5)
  + 0.06 * jogos_sem_sofrer_gol_por_jogo
  - 0.025 * gols_sofridos_por_jogo,
  -0.20, 0.20
)
form_factor = exp(média_dos_impactos_dos_jogadores_com_histórico)
```

Sem participações anteriores, `form_factor = 1`. Os gols esperados combinam média-base, ataque e defesa suavizados, notas do elenco, forma individual e o ajuste de resultados. Os lambdas são limitados ao intervalo `[0.15, 4]`:

```text
lambda_home = clamp(
  league_average
  * (home_attack / league_average)
  * (away_defense / league_average)
  * field_factor(home_field_quality)
  * home_field_form_factor
  * keeper_factor(away_goalkeeper_quality)
  * away_goalkeeper_form_factor
  * outcome_adjustment,
  0.15, 4
)

lambda_away = clamp(
  league_average
  * (away_attack / league_average)
  * (home_defense / league_average)
  * keeper_factor(home_goalkeeper_quality)
  * home_goalkeeper_form_factor
  * field_factor(away_field_quality)
  * away_field_form_factor
  / outcome_adjustment,
  0.15, 4
)
```

Os fatores de campo ajustam os gols esperados da própria equipe; o fator de goleiro ajusta os gols esperados do adversário.

Para cada lado, o modelo usa `P(k; lambda) = exp(-lambda) * lambda^k / k!` e calcula placares entre 0 e 8 gols. As probabilidades conjuntas são somadas em vitória da casa, empate ou vitória visitante. A matriz truncada é normalizada; as duas primeiras probabilidades são arredondadas a quatro casas e a terceira recebe o restante, mantendo soma exata de 1.

```text
odd_decimal = 1 / probability
```

As probabilidades são persistidas com quatro casas e as odds com três. A interface apresenta odds com duas casas. Exemplo matemático: `50% -> 2.00`, `20% -> 5.00`, `30% -> 3.33`.

## Amostra, horário e limitações

Cada registro guarda `created_at`, versão do método (atualmente 3), gols esperados, notas e `sample_matches`: total de partidas da liga, jogos de cada equipe, confrontos diretos e jogadores com histórico individual. A explicação da partida mostra amostras, qualidade dos elencos, caráter preliminar, versão e horário do cálculo. Uma estimativa é marcada `preliminary` enquanto qualquer equipe tiver menos de três partidas concluídas.

O modelo usa partidas concluídas disponíveis no campeonato sem ponderação temporal. Os eventos individuais não distinguem posição de campo além de jogador/goleiro; não existem dados de defesas do goleiro, xG ou força dos adversários fora deste campeonato. Em amostras pequenas, as notas e os priors têm peso relevante. O limite de 8 gols e as médias iniciais são escolhas simples adequadas aos poucos dados disponíveis, não uma previsão calibrada por uma grande base histórica.

## Premiações

As categorias abaixo permanecem disponíveis; todas têm descrição persistida e exibida no painel público, no fluxo de voto e no administrador.

| Categoria | Tom | Critério resumido |
|---|---|---|
| Bola de Ouro | Positiva | Regularidade, impacto, técnica e contribuição geral. |
| Revelação do Campeonato | Positiva | Evolução, surpresa e boas atuações acima da expectativa. |
| Garçom do Campeonato | Positiva | Criação de chances, assistências, visão e último passe. |
| Paredão do Campeonato | Positiva | Defesas difíceis, posicionamento, segurança e gols evitados. |
| Xerifão do Campeonato | Positiva | Desarmes, antecipação, cobertura, duelos e organização defensiva. |
| Artilheiro do Campeonato | Positiva / automática | Gols válidos em partidas finalizadas, excluindo gol contra e pênaltis da disputa. |
| Craque da Galera | Positiva | Talento, personalidade e momentos marcantes. |
| Bagre de Ouro | De resenha | Conjunto de lances atrapalhados, sem reduzir a avaliação a um erro isolado. |
| Decepção do Campeonato | Negativa | Desempenho, participação e influência abaixo da expectativa. |
| Pipoqueiro do Campeonato | De resenha | Presença em momentos de pressão e jogos decisivos. |
| Avenida do Campeonato | Negativa | Posicionamento, recomposição e proteção da área. |
| Chato do Campeonato | De resenha | Reclamações, discussões e provocações, mantendo a brincadeira respeitosa. |
| Prêmio Felipe Melo | De resenha | Intensidade, faltas, entradas duras e discussões em campo. |

`Resenha do Campeonato` e `Inimigo do Gol` são arquivadas (`is_active = false`) e ficam fora das listas, votação, resultados e relatório administrativo. Categorias e votos antigos não são apagados.

## Alterações técnicas

- `ChampionshipOddsService`: estimativa Poisson versionada, prévia antes do início, amostra, forma de elenco e assinaturas.
- `ChampionshipFixtureService`: geração antecipada validada sem mudar o estado de rascunho.
- `ChampionshipAwardCategory`: escopo `available` usado por listagens, voto, encerramento e publicação.
- Migration `2026_10_23_120000_refine_championship_awards_and_version_odds`: arquiva as duas categorias, atualiza critérios e atribui versões únicas às odds existentes preservando os valores antigos.
- Frontend `ChampionshipOddsExplanation.vue`: a explicação simples é acessível no painel público e administrativo.
