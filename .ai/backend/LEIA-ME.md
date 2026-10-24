# LEIA-ME — Regras de trabalho no backend

Leia este documento antes de alterar app/, routes/, database/, config/ ou bootstrap/. A prioridade é a solicitação explícita atual, seguida pelas regras gerais em ../ai-rules.md, contextos e implementação existente.

## Arquitetura confirmada

- API Laravel 11 em api-red-devils; o frontend é o repositório app-red-devils e consome HTTP.
- Rotas da API estão em routes/api.php e recebem o prefixo /api pelo bootstrap.
- Escritas e gestão ficam no grupo auth:sanctum + admin. Leitura pública permanece fora dele.
- O fluxo típico usa Controller, Service quando a lógica é multi-etapa/compartilhada/transacional e Eloquent.
- Não há Repository, Policy ou Gate confirmado.

## Convenções para código novo

1. Adicione rotas conforme o nível de acesso correto; nunca crie escrita administrativa fora do grupo protegido.
2. Use controllers em App\Http\Controllers\Admin para novas operações de escrita/admin. ChampionshipController é uma exceção legada: suas escritas já são protegidas no grupo admin.
3. Prefira Form Request para validações reutilizáveis ou extensas. Parte do Auth e do Campeonato usa validação inline; não trate essa exceção como obrigação para módulos novos.
4. Use JsonResource para models/coleções que já têm Resource correspondente. O Campeonato retorna JSON direto em vários métodos; confirme o contrato antes de mudar.
5. Para operações simples, controller pode acessar Model diretamente. Use Service para regra multi-etapa, compartilhada, agregada ou transacional.
6. Use paginação em novas listagens públicas/admin. Algumas coleções existentes, como campeonatos, categorias e rankings do campeonato, não são paginadas.
7. Mensagens visíveis e de validação devem estar em português.
8. Use transação para gravar vários registros que precisam permanecer consistentes.
9. Player e Pelada usam SoftDeletes. Não use forceDelete sem autorização explícita; considere também o efeito de ocultar relações e estatísticas em consultas comuns.
10. MatchPlayer.result é obrigatório e é a única fonte de resultado. is_winner existe só como campo derivado em MatchPlayerResource.
11. Não execute migrations que alterem dados nem comandos destrutivos no banco sem autorização. Nunca use migrate:fresh, rollback ou dados de produção para testes.
12. Leia o context listado em ../feature-index.md e a matriz de matriz-dependencias.md antes de mudar regra ou persistência.

## Formatos de resposta e erros

Controller::errorResponse e Controller::perPage são helpers disponíveis. Exceções da API são transformadas em JSON em bootstrap/app.php. Alguns endpoints retornam Resource/paginator, outros arrays ou modelos diretos.

O renderer atual inclui mensagem de exceção no campo error mesmo para 5xx. Consulte o achado A1 em matriz-dependencias.md; não divulgue mensagens internas em novos logs, respostas de erro ou documentação.

## Migrations

- Preserve migrations já aplicadas; use nova migration aditiva para evolução de schema.
- Migrations do projeto usam MySQL e há operações ALTER TABLE com sintaxe MySQL. SQLite não é compatível com todas as migrations existentes.
- Verifique índices e FKs, mas não presuma que o banco impõe relações compostas de mesmo campeonato; parte da integridade está nas validações do controller/service.
- Não registre tokens, PINs, hashes ou credenciais em texto em documentação.

## Verificações de alteração

Antes de concluir mudança de API, compare rota, validação, autorização, controller, Service, Model, migration, serialização, frontend consumidor e testes correspondentes. Atualize o context afetado e a matriz quando regra, contrato ou efeito colateral mudar.

A suíte Feature existe em tests/Feature. Não foi executada durante a auditoria documental; infraestrutura de teste está configurada para MySQL em phpunit.xml.
