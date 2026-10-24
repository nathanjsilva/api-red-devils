# Contexto — Permissões e área administrativa

## Como a proteção funciona

- bootstrap/app.php registra o alias admin para AdminMiddleware.
- routes/api.php coloca as operações administrativas sob middleware auth:sanctum e depois admin.
- Sanctum identifica User pelo bearer token. AdminMiddleware chama User::isAdmin(), que verifica profile === admin.
- Falta de token resulta em 401 pelo fluxo de autenticação; usuário autenticado sem profile admin resulta em 403.
- Não há Policies/Gates ou níveis granulares de autorização confirmados.
- GET /admin/me e POST /admin/logout também ficam dentro do grupo admin.
- As rotas de cadastro/voto por PIN não usam Sanctum. O voto legado usa auth:sanctum, mas não exige o middleware admin adicional.

## Controllers administrativos

Os controllers comuns de escrita ficam em app/Http/Controllers/Admin:

- PlayerController — jogadores.
- PeladaController — peladas.
- MatchPlayerController — estatísticas por jogador/pelada.
- TeamController — organização de times.
- ChampionshipVoterController — gestão dos cadastros de votação PIN.

Exceção existente: ChampionshipController fica em app/Http/Controllers e implementa algumas leituras públicas e operações administrativas. As operações de escrita desse controller continuam no grupo protegido de routes/api.php. Não determine autorização apenas pelo namespace.

## Criação de usuário admin e riscos conhecidos

O primeiro usuário é criado pelo AdminUserSeeder chamado por DatabaseSeeder; não há endpoint de setup público confirmado. O seeder usa valores de bootstrap fixos no código e updateOrCreate pode restaurar esses valores quando ele roda. Não copie credenciais para documentação ou logs. Executar o seeder em ambiente acessível pode expor a conta administrativa.

As migrations iniciais definem profile como enum com admin. Não foi verificada a estrutura do banco de produção.

O Sanctum está configurado sem expiração temporal de token. São pontos de segurança observados, não corrigidos nesta auditoria. O renderer de exceções da API também inclui a mensagem original de exceções 5xx no campo error. Ver A1 e A2 em ../matriz-dependencias.md.

## Fluxo de sessão no frontend

O service Axios lê token Sanctum no localStorage e envia Authorization. O router Vue verifica requiresAuth/requiresAdmin usando dados locais; isso melhora navegação, mas o backend continua sendo a autoridade de autorização.

A sessão PIN do campeonato é separada: token aleatório é mantido no sessionStorage do navegador e enviado em X-Championship-Voter-Session. Ela não concede acesso às rotas admin.

## Antes de alterar permissões

1. Inspecione a rota em routes/api.php e os dois middlewares em ordem.
2. Confira User, migrations de users e frontend router/types. O backend atualmente só confirma profile admin no schema de migrations.
3. Mudança de acesso afeta autorização, contrato de erro, interceptor Axios e navegação.
4. Preserve rotas de escrita sob auth:sanctum + admin, salvo mudança explicitamente autorizada e revisada de ponta a ponta.
5. Para nova operação administrativa, use controller Admin namespace como convenção; trate ChampionshipController como exceção existente, não como padrão.
