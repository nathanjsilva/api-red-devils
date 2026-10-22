# Teste manual E2E do campeonato

Os comandos abaixo só funcionam com `APP_ENV=local`, `APP_URL` apontando para
`localhost`/`127.0.0.1`, MySQL local chamado `laravel` e o opt-in temporário
`CHAMPIONSHIP_E2E_SEED_ENABLED=true`. Eles também recusam execução no ambiente
`testing` e em produção. O `ChampionshipE2ESeeder` não é chamado pelo
`DatabaseSeeder`.

## Preparar o elenco

1. Suba o ambiente local (`docker compose up -d`).
2. Pelo admin, crie um campeonato com nome iniciado por `[E2E]`, por exemplo
   `[E2E] Campeonato manual 2026-10`. Deixe-o em rascunho. A criação normal do
   módulo deve criar os cinco times e as categorias de premiação.
3. Anote o ID do campeonato exibido na resposta da API ou na URL.
4. Na pasta `api-red-devils`, rode o comando uma única vez. Em PowerShell:

   ```powershell
   docker compose exec -e CHAMPIONSHIP_E2E_SEED_ENABLED=true app php artisan championship:e2e-seed ID
   ```

   Substitua `ID` pelo número do campeonato. A primeira execução cadastra 25
   jogadores de linha, cinco goleiros, 30 inscrições e 30 vínculos com os times.
   Os apelidos começam com `E2E-C<ID>-`. Repetir o comando não duplica dados; a
   saída informa zero novos registros quando o elenco já está pronto.

O comando não cria campeonatos, times, partidas, resultados, eventos, estatísticas,
classificações, votos, premiações ou progresso. Ele só aceita campeonato `[E2E]`
em rascunho, vazio de partidas, com cinco times e sem inscrições de jogadores
reais. Uma validação final exige cinco goleiros (um por time) e cinco jogadores
de linha por time, um de cada grupo de pedra.

## Preservar os dados E2E

Nesta execução, mantenha o campeonato `[E2E]`, as inscrições e os jogadores fictícios criados. Anote o ID do campeonato no relatório e **não rode o comando de limpeza ao concluir**. Os dados permitem repetir a validação manual e consultar os resultados depois.

O comando abaixo permanece disponível para uma solicitação futura de limpeza isolada; ele não faz parte do fluxo atual e não deve ser executado sem pedido específico:

Use o ID do mesmo campeonato. A opção `--confirm` é obrigatória:

```powershell
docker compose exec -e CHAMPIONSHIP_E2E_SEED_ENABLED=true app php artisan championship:e2e-cleanup ID --confirm
```

A limpeza remove o campeonato `[E2E]`, seus dados subordinados e apenas os
jogadores com apelido `E2E-C<ID>-` que não tenham referências fora dele. Se um
jogador fictício tiver sido ligado a um usuário, a uma pelada ou a outro
campeonato, a limpeza aborta sem apagar nada. Desvincule esse jogador de forma
manual e rode o comando novamente. Dados de outros campeonatos não são removidos.

Não use `migrate:fresh`, `db:wipe` ou `db:seed` para preparar os dados E2E.

## Execução local preservada — 2026-10-21

O campeonato `[E2E] Campeonato persistente 2026-10` (ID 3) foi criado no banco
local `laravel` após a exclusão dos campeonatos anteriores. O elenco E2E contém
30 jogadores, 30 inscrições e 30 vínculos com os times; o campeonato tem cinco
times, 15 categorias e permanece em rascunho, sem partidas.

Pela API local, foram verificados o cadastro e a identificação de votante, um voto
em Bola de Ouro, a listagem administrativa, a redefinição de PIN, a invalidação
da sessão anterior e a preservação do voto após nova identificação. O banco
terminou com um votante e um voto neste campeonato. Esses dados devem permanecer;
não executar `championship:e2e-cleanup` para esta execução.

## Checklist manual ponta a ponta

- [ ] Como visitante, abrir a página pública, ver times, elenco, tabela vazia,
  calendário e categorias sem acesso às rotas administrativas.
- [ ] No admin, conferir os cinco times, 25 jogadores de linha (cinco por pedra)
  e cinco goleiros (um por time); tentar inscrever jogador duplicado, goleiro em
  posição incorreta, pedra/nota incompatível e exceder limites.
- [ ] Alterar nome de time e escudo; conferir a atualização na parte pública.
- [ ] Distribuir novamente o elenco e tentar repetir jogador, pedra ou participante
  de outro campeonato; conferir que uma distribuição inválida não apaga a anterior.
- [ ] Iniciar com elenco incompleto para conferir o bloqueio; depois iniciar com
  o elenco completo e validar a trilha de auditoria.
- [ ] Gerar os dez jogos em cinco rodadas; tentar gerar novamente e conferir que
  não duplica confrontos.
- [ ] Lançar placares de vitória, empate e derrota; cancelar um jogo; validar pontos,
  gols, saldo e desempates, inclusive times empatados e critério administrativo.
- [ ] Registrar gols, gols contra e assistências; editar/remover eventos e conferir
  recálculo do placar. Tentar usar atleta de outro time, fora da partida ou autoassistência.
- [ ] Marcar participantes e MVP; tentar atleta fora dos times e IDs repetidos;
  confirmar estatísticas de gols, assistências, participações e MVP.
- [ ] Solicitar playoffs antes de terminar todos os dez jogos e confirmar bloqueio;
  completar a fase, gerar semifinais e conferir cruzamentos/congelamento da tabela.
- [ ] Finalizar semifinais, validar final e jogo challenger; testar empate sem pênaltis,
  pênaltis empatados e vencedor definido.
- [ ] Conferir probabilidades/cotações antes do jogo e as estatísticas/rankings por
  fase na interface pública.
- [ ] Cadastrar votantes por nome com PIN de seis dígitos; tentar confirmação divergente,
  cadastro duplicado, PIN incorreto e bloqueio após cinco erros.
- [ ] Identificar novamente por nome/PIN, conferir status individual, votar em categorias
  diferentes, rejeitar auto-voto e duplicidade e confirmar que outras categorias continuam disponíveis.
- [ ] Corrigir vínculo e redefinir PIN como administrador; conferir auditoria, sessão
  invalidada e preservação das categorias já votadas.
- [ ] Tentar gestão de votantes sem perfil admin, voto sem sessão e voto após encerramento;
  validar que os fluxos são bloqueados.
- [ ] Vincular usuários legados a atletas e conferir que votos existentes continuam contando
  sem permitir um segundo voto da mesma inscrição; conferir percentuais e publicação.
- [ ] Encerrar campeonato e conferir estado final, campeão, auditoria e consistência
  das páginas públicas.
- [x] Preservar o campeonato E2E e os apelidos `E2E-C<ID>-` no banco local ao terminar.
  Não executar a limpeza ao final desta rodada.
