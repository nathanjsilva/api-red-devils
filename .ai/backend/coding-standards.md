# .ai/backend/coding-standards.md — Padrões de Código PHP/Laravel

Padrões observados no código existente. Siga-os por consistência, mesmo quando não são os únicos "corretos" possíveis.

## Controllers

- Sem construtor/injeção de dependência de serviços — os controllers atuais usam Models estaticamente (`Player::find()`, `Pelada::create()`, etc.) direto no método.
- Busca de recurso por id sempre segue o padrão:
  ```php
  $model = Model::find($id);
  if (!$model) {
      return $this->errorResponse('Mensagem em português.', 404);
  }
  ```
  Não trocar por `findOrFail()` a menos que o padrão de erro (`errorResponse`) seja preservado por um Exception Handler — hoje não é.
- Métodos são nomeados no padrão Laravel resourceful mas nem sempre usam nomes resourceful (`index`, `show`, `store`, `update`, `destroy`) — `AdminController` usa nomes descritivos (`storePlayer`, `listPeladas`, `organizeTeams`). Siga o nome já usado no controller que estiver editando.

## Form Requests

- `authorize()` sempre retorna `true` (autorização é feita via middleware de rota, não em Form Request).
- `rules()` documentado com PHPDoc `@return array<string, ...>` como os arquivos existentes.
- Regras de "update" usam `sometimes` em vez de `required` para permitir atualização parcial.
- `messages()` sempre em português, curtas e diretas.
- Validação cross-field (ex.: "goleiro precisa de goals_conceded", "jogador já está na pelada") vai em `withValidator()`, não em `rules()`.

## Resources

- Toda resposta de model passa por um `JsonResource` correspondente (`PlayerResource`, `PeladaResource`, etc.).
- Datas formatadas explicitamente: `$this->created_at?->format('Y-m-d H:i:s')`.
- Relações carregadas condicionalmente com `whenLoaded()` — nunca força N+1 carregando relação direto no Resource.

## Models

- `$fillable` explícito em todos os models (sem `$guarded = []`).
- Relações Eloquent como métodos públicos padrão (`hasMany`, `belongsTo`, `belongsToMany`), sem type-hint de retorno.
- Sem accessors/mutators customizados hoje — se precisar adicionar, seguir a convenção Laravel 11 (`protected function nomeDoCampo(): Attribute`), como já é feito em `User::casts()`.

## Migrations

- Nome de arquivo no padrão Laravel: `YYYY_MM_DD_HHMMSS_descricao_em_snake_case.php`.
- Alterações de coluna em tabela existente usam `DB::statement()` com SQL bruto quando envolvem `MODIFY COLUMN` com `COMMENT` (ver migration de `match_players` mais recente) — não é o padrão idiomático do Schema Builder, mas é o que já está em uso neste repo.

## Idioma

- Nomes de classes, métodos, variáveis: **inglês** (`Player`, `MatchPlayer`, `storePlayer`).
- Nomes de campos de negócio específicos do domínio: **português quando já existentes no banco** (`qtd_times`, `qtd_jogadores_por_time`, `qtd_goleiros`, `location`... mas `date`, `goals`, `assists` em inglês). Não traduza campos existentes; ao criar campos novos, siga o idioma do campo mais próximo semanticamente já existente na mesma tabela.
- Mensagens voltadas ao usuário final (erros, validação): **sempre português**.

## Testes

- `tests/TestCase.php` é o único arquivo de teste presente hoje — não há suíte de testes de feature/unit ainda escrita. Se o usuário pedir testes, siga convenção padrão do Laravel (`tests/Feature`, `tests/Unit`) e use os `database/factories/*Factory.php` já existentes (`PlayerFactory`, `PeladaFactory`, `MatchPlayerFactory`, `UserFactory`).

## Formatação

- `laravel/pint` está nas dependências de dev (`composer.json`) — rodar `./vendor/bin/pint` (ou `docker-compose exec app ./vendor/bin/pint`) antes de considerar uma alteração de PHP finalizada, se o usuário pedir formatação/lint.
