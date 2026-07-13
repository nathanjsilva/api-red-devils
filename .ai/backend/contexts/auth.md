# Context: Autenticação

## Arquivos envolvidos

- `app/Http/Controllers/AuthController.php`
- `app/Models/User.php`
- `app/Http/Middleware/AdminMiddleware.php`
- `app/Http/Resources/AuthResource.php`, `UserResource.php`
- `config/sanctum.php`, `config/auth.php`
- `routes/api.php` (rotas `login`, `admin/logout`, `admin/me`)

## Fluxo

1. `POST /api/login` (pública) — recebe `username` + `password`, autentica contra a tabela `users` (não `players`), gera token via `Laravel\Sanctum\HasApiTokens::createToken()`.
2. Resposta: `AuthResource` → `{ access_token, token_type, user: UserResource }`.
3. Requisições autenticadas enviam `Authorization: Bearer {token}`.
4. `GET /api/admin/me` e `POST /api/admin/logout` estão dentro do grupo `middleware(['auth:sanctum', 'admin'])` — ou seja, **exigem admin**, não apenas autenticação. Não existe hoje um "logout de usuário comum" fora desse grupo.
5. `AdminMiddleware` verifica `$user->isAdmin()` (`profile === 'admin'`); se falhar, 403 com mensagem em português.

## Regras de negócio

- Autenticação é sempre por `User`, nunca por `Player`. Um `Player` não faz login.
- Não existe registro público de `User` — veja `database/seeders/AdminUserSeeder.php` para como o primeiro admin é criado (via seeder, não endpoint HTTP).
- `password` é `hashed` via cast do Eloquent (`User::casts()`), não manipular hash manualmente.

## Divergências conhecidas com README.md

- O `README.md` descreve login por `email`, registro público de jogador (`POST /api/players`), e uma rota `POST /api/setup-first-admin` — **nada disso existe no código atual**. Não implemente nem documente como se existisse sem confirmar antes com o usuário se é uma funcionalidade nova a ser criada ou apenas doc desatualizada.

## Ao alterar

- Se for adicionar um novo nível de permissão (ex.: usuário autenticado não-admin), isso implica revisar todas as rotas do grupo `admin` em `routes/api.php`, já que hoje tudo que não é público está atrás de `admin`. Avise o usuário do escopo antes de começar.
