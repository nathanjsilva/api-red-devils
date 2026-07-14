# .ai/backend/architecture.md — Arquitetura do Backend

## Estilo arquitetural

Laravel com uma camada leve de **Services** para regras de negócio compartilhadas ou transacionais. Não é DDD nem Repository Pattern — é o mínimo necessário para tirar lógica de negócio não-trivial de dentro dos controllers. Fluxo:

```
Request HTTP
  → Route (routes/api.php)
  → Middleware (auth:sanctum, admin)
  → Form Request (validação, quando existir)
  → Controller (orquestração: 404s, chamadas a Service/Model)
  → Service (regra de negócio multi-passo/transacional, quando existir) OU Model/Eloquent direto (CRUD simples)
  → Resource (serialização da resposta)
  → JSON Response
```

**Quando usar Service vs. Model direto:** se a operação é um CRUD simples de um único registro (criar/atualizar/remover um `Player`, uma `Pelada`), o controller fala direto com o Model — não crie um Service para isso. Um Service se justifica quando: (a) a lógica é usada por mais de um fluxo (ex.: organização manual e automática de times), (b) envolve múltiplos passos com necessidade de transação, ou (c) é uma consulta complexa reutilizável (ex.: cálculo de rankings). Não crie Services "por precaução" para operações triviais.

## Estrutura de pastas relevante

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php          ← login/logout/me
│   │   ├── PlayerController.php        ← público: index (paginado), show
│   │   ├── PeladaController.php        ← público: index (paginado), show, byDate
│   │   ├── TeamController.php          ← público: fields/players/organized/players-with-statistics (somente leitura)
│   │   ├── StatisticsController.php    ← público: delega tudo para StatisticsService
│   │   └── Admin/                      ← TODO controller aqui exige auth:sanctum + admin
│   │       ├── PlayerController.php    ← store/update/destroy
│   │       ├── PeladaController.php    ← store/update/destroy
│   │       ├── MatchPlayerController.php ← store/update/destroy/upsertByPlayerAndPelada
│   │       └── TeamController.php      ← organizeManual/organizeAutomatic, delega para TeamOrganizerService
│   ├── Middleware/
│   │   └── AdminMiddleware.php         ← exige User autenticado com profile === 'admin'
│   ├── Requests/                       ← Form Requests de validação (Store*/Update*)
│   └── Resources/                      ← serialização de resposta (JsonResource)
├── Services/
│   ├── TeamOrganizerService.php        ← organização manual e automática de times (transacional)
│   └── StatisticsService.php           ← cálculo de rankings e estatísticas
├── Exceptions/
│   └── InsufficientGoalkeepersException.php  ← exception "renderable" (define seu próprio render(), retorna 400)
└── Models/                             ← Eloquent models (ver project-context.md)
```

## Convenção de exceptions de domínio

Quando uma regra de negócio precisa interromper um fluxo com um erro específico (não um 404 simples de "recurso não encontrado", que continua sendo tratado inline no controller com `$this->errorResponse(...)`), prefira uma exception própria em `app/Exceptions/` que define seu próprio método `render(Request $request)` retornando o JSON no formato padrão do projeto (`{ "message": ..., "error": ... }`). Isso evita `try/catch` espalhado pelos controllers — a exception se resolve sozinha via o mecanismo de exception rendering do Laravel. Veja `InsufficientGoalkeepersException` como modelo.

## Pontos notáveis da arquitetura atual

- **Toda rota de escrita/gestão está sob `Admin\*`** — não existe mais um controller único concentrando várias áreas (o antigo `AdminController` "God controller" foi decomposto por domínio).
- **Duas formas de organizar times** continuam existindo (manual via `team_assignments` explícitos, e automática via `player_ids` + distribuição do sistema) — isso é intencional (são dois casos de uso reais), mas agora **compartilham `TeamOrganizerService`**, então a lógica de limpar times antigos, transação e distribuição de goleiros é uma só, usada pelos dois fluxos.
- `Controller::errorResponse()` e `Controller::perPage()` (base `app/Http/Controllers/Controller.php`) são os helpers compartilhados entre controllers — para erros e paginação, respectivamente.
- Sem camada de autorização via Policies/Gates — autorização é feita inteiramente pelo middleware `admin` a nível de rota.
- Sem eventos, jobs ou filas customizados atualmente.
- `Player` e `Pelada` usam `SoftDeletes` — ao adicionar queries novas nesses models, lembre que registros deletados já ficam automaticamente fora do resultado (comportamento padrão do Eloquent), então não é necessário filtrar manualmente.
