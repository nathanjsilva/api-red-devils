# .ai/backend/architecture.md — Arquitetura do Backend

## Estilo arquitetural

Laravel "padrão" (MVC simplificado), **sem** camadas de Domain-Driven Design, Services ou Repositories. Fluxo direto:

```
Request HTTP
  → Route (routes/api.php)
  → Middleware (auth:sanctum, admin)
  → Form Request (validação, quando existir)
  → Controller (regra de aplicação + orquestração)
  → Model / Eloquent (persistência, relações)
  → Resource (serialização da resposta)
  → JSON Response
```

Não proponha introduzir camadas extras (Services, UseCases, Repositories, Actions) a menos que o usuário peça explicitamente. Se identificar duplicação de lógica entre controllers que justificaria extração, **sugira** ao usuário em vez de refatorar direto.

## Estrutura de pastas relevante

```
app/
├── Http/
│   ├── Controllers/     ← lógica de aplicação por domínio
│   │   ├── AuthController.php
│   │   ├── PlayerController.php      ← rotas públicas de leitura
│   │   ├── AdminController.php       ← CRUD de players/peladas/match-players + organize-teams (grande, concentra várias áreas)
│   │   ├── TeamController.php        ← organização de times (fields/players/organize/organized/with-statistics)
│   │   ├── StatisticsController.php  ← estatísticas e rankings
│   │   └── MatchPlayerController.php ← NÃO roteado hoje, ver feature-index.md
│   ├── Middleware/
│   │   └── AdminMiddleware.php       ← exige User autenticado com profile === 'admin'
│   ├── Requests/        ← Form Requests de validação (Store*/Update*)
│   └── Resources/       ← serialização de resposta (JsonResource)
├── Models/               ← Eloquent models (ver project-context.md)
└── Providers/
```

## Pontos notáveis da arquitetura atual

- `AdminController` concentra CRUD de `Player`, `Pelada` e `MatchPlayer`, além de `organizeTeams`. Se crescer mais, pode fazer sentido separar por domínio — mas isso é decisão do usuário, não faça sozinho.
- `TeamController` e o método `organizeTeams` de `AdminController` implementam **duas lógicas de organização de times diferentes**: uma automática (distribui goleiros/linha por índice) e outra manual (recebe `team_assignments` explícitos do cliente). Não assuma que são intercambiáveis — veja [contexts/teams.md](contexts/teams.md).
- `Controller::errorResponse()` é o único helper compartilhado entre controllers; é a convenção para respostas de erro.
- Sem camada de autorização via Policies/Gates — autorização é feita inteiramente pelo middleware `admin` a nível de rota.
- Sem eventos, jobs ou filas customizados atualmente.
