# Context: Permissões / Área Administrativa

## Arquivos envolvidos

- `app/Http/Middleware/AdminMiddleware.php`
- `app/Models/User.php` (`isAdmin()`)
- `bootstrap/app.php` (registro do middleware `admin`)
- `routes/api.php` (grupo `Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')`)
- `database/seeders/AdminUserSeeder.php`
- Todos os controllers em `app/Http/Controllers/Admin/` (`PlayerController`, `PeladaController`, `MatchPlayerController`, `TeamController`) — cada rota desse namespace já está, por convenção, dentro do grupo `admin` (ver [feature-index.md](../../feature-index.md))

## Como funciona

- `AdminMiddleware::handle()` verifica `$request->user()` (resolvido pelo `auth:sanctum` que roda antes no mesmo grupo), confirma que é instância de `App\Models\User` e que `isAdmin()` (`profile === 'admin'`) é verdadeiro. Caso contrário, `403` com `{"message": "Acesso negado. Apenas administradores podem acessar esta funcionalidade."}`.
- Não existe granularidade de permissão além de admin/não-admin — é binário.
- `logout`/`me` continuam dentro do grupo `admin` **de propósito** (não foi alterado no refactor de arquitetura) — hoje só existe usuário admin no sistema, então não há regressão prática, mas é uma limitação conhecida: se um usuário autenticado não-admin existir no futuro, ele não vai conseguir deslogar nem ver o próprio perfil via API sem também ser admin.

## Como o primeiro admin é criado

- Via `database/seeders/AdminUserSeeder.php` (`php artisan db:seed`), não via endpoint HTTP. O `README.md` menciona uma rota `POST /api/setup-first-admin` que **não existe no código atual** — não implemente com base nessa suposição sem confirmar com o usuário se é para criar do zero.
- **Atenção**: o seeder cria/atualiza um usuário com credenciais fixas hardcoded no arquivo (`username: ADMIN`). Isso é uma limitação de segurança conhecida e **deliberadamente não alterada** neste refactor (fora de escopo) — não trate como resolvida, e não mexa nela sem pedido explícito do usuário.
- Da mesma forma, tokens Sanctum não têm expiração configurada (`config/sanctum.php` → `expiration => null`) — também fora de escopo do refactor atual, permanece como ponto de atenção para o futuro.

## Ao alterar

- Se o usuário pedir "permitir que jogadores comuns façam algo sem ser admin", isso exige: (1) decidir se a entidade autenticada continua sendo só `User`, (2) criar um nível de acesso intermediário (ex.: novo valor de `profile`, ou checar `auth:sanctum` sem `admin` em rotas específicas), e (3) mover essas rotas para fora do grupo `admin` em `routes/api.php`, tirando `logout`/`me` do meio do caminho. É uma mudança estrutural — explique o plano e peça confirmação antes de tocar em `routes/api.php` e no middleware.
- Não remova o middleware `admin` de nenhuma rota do grupo sem autorização explícita — são endpoints de escrita/gestão sensíveis.
- Toda nova rota de escrita/gestão deve ter seu controller criado em `app/Http/Controllers/Admin/` e registrada dentro do grupo `admin` — não crie controllers de escrita fora desse namespace (ver [backend/architecture.md](../architecture.md)).
