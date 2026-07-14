# .ai/backend/LEIA-ME.md — Regras Obrigatórias do Backend

Leia isto sempre antes de alterar qualquer coisa dentro de `app/`, `routes/`, `database/`, `config/`, `bootstrap/`.

## Regras específicas

1. **Toda rota nova** deve ser adicionada em `routes/api.php`, dentro do grupo correto (`Route::middleware(['auth:sanctum','admin'])->prefix('admin')` para rotas administrativas, fora dele para rotas públicas). Nunca crie rotas admin sem o middleware `admin`.
2. **Controller de escrita/gestão (admin) sempre em `app/Http/Controllers/Admin/`** (namespace `App\Http\Controllers\Admin`); controller de leitura pública sempre no namespace raiz. Não volte a concentrar múltiplos domínios num único controller "genérico" — cada controller trata de um recurso.
3. **Validação sempre via Form Request** (`app/Http/Requests/`), seguindo o padrão existente (`Store*Request`, `Update*Request`), com métodos `rules()` e `messages()` em português.
4. **Resposta sempre via Resource** (`app/Http/Resources/`) quando o retorno for um model ou coleção de models já existente com Resource. Não monte arrays manuais de model quando um Resource equivalente já existe.
5. **Erros de recurso não encontrado**: usar `$this->errorResponse('mensagem', codigo)` (definido em `Controller.php`), não `abort()` nem exceptions genéricas, para manter o formato de resposta consistente (`{ "message": ..., "error": ... }`). Para erros de regra de negócio mais complexos que precisam de exception própria, siga o padrão de `InsufficientGoalkeepersException` (exception "renderable", com seu próprio `render()`) — veja [architecture.md](architecture.md).
6. **Use a camada de `app/Services/` para lógica de negócio compartilhada, multi-passo ou transacional** (ex.: `TeamOrganizerService`, `StatisticsService`). Para CRUD simples de um único registro, o controller pode falar direto com o Model — não crie um Service para isso. Não introduza uma camada adicional (Repositories, UseCases, DDD completo) sem que o usuário peça explicitamente; a camada de Services já existente é o nível de abstração-alvo deste projeto.
7. **Listagens devem ser paginadas.** Use o helper `$this->perPage($request)` (definido em `Controller.php`) e `Model::paginate($perPage)` / `$query->paginate($perPage)`. Não volte a retornar `->get()` sem paginação em endpoints de listagem.
8. **`Player` e `Pelada` usam soft delete** (`SoftDeletes`). Nunca use `forceDelete()` nesses models a partir de um controller/rota sem autorização explícita — o soft delete existe justamente para preservar o histórico de `match_players`/`teams` ao "apagar" um jogador ou uma pelada.
9. **`MatchPlayer.result` é obrigatório e é o único campo de resultado** (`is_winner` foi removido da tabela). Nunca reintroduza um campo boolean paralelo de vitória — se precisar expor um booleano de conveniência na resposta, derive-o do `result` no Resource, como já é feito em `MatchPlayerResource`.
10. **Migrations**: nunca rode `migrate:fresh`, `migrate:rollback`, ou edite migrations já aplicadas em produção. Alterações de coluna em MySQL neste projeto usam `DB::statement()` com SQL bruto (não `Schema::table()->change()`, que exigiria `doctrine/dbal`) — siga esse padrão em novas migrations que alterem colunas existentes.
11. **Mensagens de erro e de validação em português**, seguindo o tom já usado em todo o projeto (ex.: "Jogador não encontrado.").
12. Antes de mexer numa funcionalidade específica, leia o context correspondente em `.ai/backend/contexts/` (veja [feature-index.md](../feature-index.md)).
