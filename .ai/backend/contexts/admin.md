# Context: Permissões / Área Administrativa

## Arquivos envolvidos

- `app/Http/Middleware/AdminMiddleware.php`
- `app/Models/User.php` (`isAdmin()`)
- `bootstrap/app.php` (registro do middleware `admin`, se aplicável — conferir alias)
- `routes/api.php` (grupo `Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')`)
- `database/seeders/AdminUserSeeder.php`

## Como funciona

- `AdminMiddleware::handle()` verifica `$request->user()` (resolvido pelo `auth:sanctum` que roda antes no mesmo grupo), confirma que é instância de `App\Models\User` e que `isAdmin()` (`profile === 'admin'`) é verdadeiro. Caso contrário, `403` com `{"message": "Acesso negado. Apenas administradores podem acessar esta funcionalidade."}`.
- Não existe granularidade de permissão além de admin/não-admin — é binário.
- **Todo o grupo `admin` de `routes/api.php` cobre praticamente toda a escrita da API** (players, peladas, teams, match-players) além de `logout`/`me`. Não há hoje uma camada intermediária de "usuário autenticado comum".

## Como o primeiro admin é criado

- Via `database/seeders/AdminUserSeeder.php` (`php artisan db:seed`), não via endpoint HTTP. O `README.md` menciona uma rota `POST /api/setup-first-admin` que **não existe no código atual** — não implemente com base nessa suposição sem confirmar com o usuário se é para criar do zero.

## Ao alterar

- Se o usuário pedir "permitir que jogadores comuns façam algo sem ser admin", isso exige: (1) decidir se a entidade autenticada continua sendo só `User`, (2) criar um nível de acesso intermediário (ex.: novo valor de `profile`, ou checar `auth:sanctum` sem `admin` em rotas específicas), e (3) mover essas rotas para fora do grupo `admin` em `routes/api.php`. É uma mudança estrutural — explique o plano e peça confirmação antes de tocar em `routes/api.php` e no middleware.
- Não remova o middleware `admin` de nenhuma rota do grupo sem autorização explícita — são endpoints de escrita/gestão sensíveis.
