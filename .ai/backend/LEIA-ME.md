# .ai/backend/LEIA-ME.md — Regras Obrigatórias do Backend

Leia isto sempre antes de alterar qualquer coisa dentro de `app/`, `routes/`, `database/`, `config/`, `bootstrap/`.

## Regras específicas

1. **Toda rota nova** deve ser adicionada em `routes/api.php`, dentro do grupo correto (`Route::middleware(['auth:sanctum','admin'])->prefix('admin')` para rotas administrativas, fora dele para rotas públicas). Nunca crie rotas admin sem o middleware `admin`.
2. **Validação sempre via Form Request** (`app/Http/Requests/`), seguindo o padrão existente (`Store*Request`, `Update*Request`), com métodos `rules()` e `messages()` em português. Não valide manualmente dentro do controller a menos que já seja o padrão local (ex.: `TeamController::organizePlayers` usa `$request->validate()` inline — siga o padrão do arquivo que estiver editando).
3. **Resposta sempre via Resource** (`app/Http/Resources/`) quando o retorno for um model ou coleção de models já existente com Resource. Não monte arrays manuais de model quando um Resource equivalente já existe.
4. **Erros de recurso não encontrado**: usar `$this->errorResponse('mensagem', codigo)` (definido em `Controller.php`), não `abort()` nem exceptions genéricas, para manter o formato de resposta consistente (`{ "message": ..., "error": ... }`).
5. **Não crie uma camada de Services/Repositories/DDD.** Este projeto usa Laravel "padrão" (Controller → Model direto, com Requests para validação e Resources para serialização). Não introduza abstrações de arquitetura em camadas sem que o usuário peça explicitamente — veja [architecture.md](architecture.md).
6. **Migrations**: nunca rode `migrate:fresh`, `migrate:rollback`, ou edite migrations já aplicadas em produção. Uma nova migration não commitada já existe no working tree (goalkeeper/goals nullable) — não descarte nem sobrescreva sem confirmar com o usuário.
7. **Mensagens de erro e de validação em português**, seguindo o tom já usado em todo o projeto (ex.: "Jogador não encontrado.").
8. Antes de mexer numa funcionalidade específica, leia o context correspondente em `.ai/backend/contexts/` (veja [feature-index.md](../feature-index.md)).
