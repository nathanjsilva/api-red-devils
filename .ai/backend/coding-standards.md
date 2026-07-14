# .ai/backend/coding-standards.md — Padrões de Código PHP/Laravel

Padrões observados no código existente. Siga-os por consistência, mesmo quando não são os únicos "corretos" possíveis.

## Controllers

- Sem construtor/injeção de dependência de Models — os controllers usam Models estaticamente (`Player::find()`, `Pelada::create()`, etc.) direto no método. **Services, sim, são injetados via construtor** (property promotion, `private readonly`), como em `StatisticsController` e `Admin\TeamController`.
- Busca de recurso por id sempre segue o padrão:
  ```php
  $model = Model::find($id);
  if (!$model) {
      return $this->errorResponse('Mensagem em português.', 404);
  }
  ```
  Não trocar por `findOrFail()` a menos que o padrão de erro (`errorResponse`) seja preservado por um Exception Handler — hoje não é.
- Métodos são nomeados no padrão Laravel resourceful (`index`, `show`, `store`, `update`, `destroy`) nos controllers `Admin\*` e nos públicos simples (`PlayerController`, `PeladaController`). `TeamController`/`Admin\TeamController` e `StatisticsController` usam nomes descritivos (`organizeManual`, `getTeamFields`, `winsRanking`) porque não são CRUD resourceful — siga o padrão já usado no controller que estiver editando.
- Listagens usam paginação: `Model::query()->paginate($this->perPage($request))`, nunca `->get()` sem paginação.

## Services (`app/Services/`)

- Sem interface/contrato — são classes concretas simples, instanciadas via injeção de construtor no controller.
- Métodos públicos recebem Models já resolvidos (ex.: `organizeAutomatic(Pelada $pelada, array $playerIds)`), não ids soltos — a resolução de "existe ou não" (404) fica no controller, a lógica de negócio pura fica no Service.
- Operações que gravam múltiplos registros relacionados usam `DB::transaction(fn () => ...)` dentro do Service, não `DB::beginTransaction()`/`commit()`/`rollBack()` manual.
- Regra de negócio que precisa interromper o fluxo com um erro específico lança uma exception própria de `app/Exceptions/` (ver `InsufficientGoalkeepersException`), não retorna `null`/`false` para o controller interpretar.

## Form Requests

- `authorize()` sempre retorna `true` (autorização é feita via middleware de rota, não em Form Request).
- `rules()` documentado com PHPDoc `@return array<string, ...>` como os arquivos existentes.
- Regras de "update" usam `sometimes` em vez de `required` para permitir atualização parcial — mas **todo campo que faz sentido editar precisa estar em `rules()`**; um campo ausente das regras de um `Update*Request` é silenciosamente ignorado por `$request->validated()` (já foi uma lacuna real neste projeto — não reintroduza).
- `messages()` sempre em português, curtas e diretas. Não deixe mensagens "órfãs" (chave de mensagem sem a regra correspondente em `rules()`) — se a regra for removida, remova a mensagem junto.
- Validação cross-field (ex.: "goleiro precisa de goals_conceded", "jogador já está na pelada") vai em `withValidator()`, não em `rules()`.
- `result` é obrigatório em `StoreMatchPlayerRequest` (`required|in:win,loss,draw`) — não volte a torná-lo `nullable`, e não reintroduza `is_winner` como campo de input.

## Resources

- Toda resposta de model passa por um `JsonResource` correspondente (`PlayerResource`, `PeladaResource`, etc.).
- Datas formatadas explicitamente: `$this->created_at?->format('Y-m-d H:i:s')`.
- Relações carregadas condicionalmente com `whenLoaded()` — nunca força N+1 carregando relação direto no Resource. **Atenção**: o nome usado em `whenLoaded('nomeDaRelacao')` tem que bater exatamente com o nome usado no `with()`/`load()` do controller (já houve um bug real aqui: eager load de `players` enquanto o Resource checava `matchPlayers`, fazendo o campo sempre voltar vazio).
- Campos derivados (que não têm coluna própria no banco) são calculados no Resource a partir de outro campo, nunca duplicados como coluna — ex.: `is_winner` em `MatchPlayerResource` é `$this->result === 'win'`.

## Models

- `$fillable` explícito em todos os models (sem `$guarded = []`).
- Relações Eloquent como métodos públicos padrão (`hasMany`, `belongsTo`, `belongsToMany`), sem type-hint de retorno.
- `Player` e `Pelada` usam `use SoftDeletes;` — ao escrever queries que precisem incluir registros deletados (raro; hoje nenhum endpoint faz isso), use `withTrashed()`/`onlyTrashed()` explicitamente, nunca desative o soft delete globalmente.
- Sem accessors/mutators customizados hoje — se precisar adicionar, seguir a convenção Laravel 11 (`protected function nomeDoCampo(): Attribute`), como já é feito em `User::casts()`.

## Migrations

- Nome de arquivo no padrão Laravel: `YYYY_MM_DD_HHMMSS_descricao_em_snake_case.php`.
- Alterações de coluna em tabela existente usam `DB::statement()` com SQL bruto quando envolvem `MODIFY COLUMN`/`DROP COLUMN` (ver migrations de `match_players`) — este projeto não tem `doctrine/dbal` instalado, então `Schema::table()->change()` **não funciona** aqui. Sempre use SQL bruto para alterar/remover coluna.
- Migrations que fazem backfill de dados (ex.: popular uma coluna nova a partir de uma antiga antes de removê-la) fazem isso com `DB::table(...)->update(...)` dentro do próprio `up()`, antes do `ALTER TABLE` que torna a coluna `NOT NULL` ou remove a antiga — veja `2026_07_12_130100_consolidate_result_column_on_match_players_table.php` como modelo.

## Idioma

- Nomes de classes, métodos, variáveis: **inglês** (`Player`, `MatchPlayer`, `storePlayer`).
- Nomes de campos de negócio específicos do domínio: **português quando já existentes no banco** (`qtd_times`, `qtd_jogadores_por_time`, `qtd_goleiros`, `location`... mas `date`, `goals`, `assists` em inglês). Não traduza campos existentes; ao criar campos novos, siga o idioma do campo mais próximo semanticamente já existente na mesma tabela.
- Mensagens voltadas ao usuário final (erros, validação): **sempre português**.

## Testes

- `tests/TestCase.php` é o único arquivo de teste presente hoje — não há suíte de testes de feature/unit ainda escrita. Se o usuário pedir testes, siga convenção padrão do Laravel (`tests/Feature`, `tests/Unit`) e use os `database/factories/*Factory.php` já existentes (`PlayerFactory`, `PeladaFactory`, `MatchPlayerFactory`, `UserFactory`). `MatchPlayerFactory` usa `result` (não `is_winner`).

## Formatação

- `laravel/pint` está nas dependências de dev (`composer.json`) — rodar `./vendor/bin/pint` (ou `docker-compose exec app ./vendor/bin/pint`) antes de considerar uma alteração de PHP finalizada, se o usuário pedir formatação/lint.
