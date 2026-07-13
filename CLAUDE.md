# CLAUDE.md — API Red Devils

## LEITURA OBRIGATÓRIA ANTES DE QUALQUER AÇÃO

Esta instrução se aplica a **todo e qualquer pedido** feito no chat ou no terminal — sem exceção.

Antes de criar, alterar, remover, mover ou refatorar qualquer arquivo, leia os arquivos abaixo na ordem indicada.

Este projeto é **apenas backend** (API Laravel). Não existe pasta `frontend/` — todo o `.ai/` trata do backend.

---

### Sempre — toda ação

| # | Arquivo | Por quê |
|---|---------|---------|
| 1 | [.ai/ai-rules.md](.ai/ai-rules.md) | Regras gerais e ordem de prioridade |
| 2 | [.ai/project-context.md](.ai/project-context.md) | Stack, modelos de dados, endpoints |
| 3 | [.ai/feature-index.md](.ai/feature-index.md) | Índice de funcionalidades e caminhos dos contexts |
| 4 | [.ai/backend/LEIA-ME.md](.ai/backend/LEIA-ME.md) | Regras obrigatórias específicas do backend |
| 5 | [.ai/backend/architecture.md](.ai/backend/architecture.md) | Camadas, fluxo de dependências |
| 6 | [.ai/backend/coding-standards.md](.ai/backend/coding-standards.md) | Padrões de código PHP/Laravel usados neste repo |
| 7 | Context da funcionalidade em [.ai/backend/contexts/](.ai/backend/contexts/) | Fluxos, regras de negócio, arquivos envolvidos |

---

## REGRAS OBRIGATÓRIAS

1. **Explique antes de executar** — informe o que será feito, o objetivo, os arquivos que serão alterados e os impactos.
2. **Sempre pergunte:** _"Deseja que eu execute esta alteração?"_ — nunca assuma autorização.
3. **Nunca altere regra de negócio, apague, mova ou renomeie arquivos sem autorização explícita.**
4. **Após qualquer alteração**, apresente resumo com: arquivos modificados, funcionalidades afetadas e próximos passos sugeridos.
5. **Nunca altere ou execute nada relacionado a deploy/produção** (`deploy.sh`, `docker-compose.prod.yml`, `nginx/production.conf`, secrets do GitHub Actions) sem autorização explícita — veja [.ai/project-context.md](.ai/project-context.md#deploy).
6. **`README.md` está desatualizado em relação ao código atual** (ex.: descreve login por `email`/registro público de jogador, que não existem mais). Nunca use o `README.md` como fonte de verdade sobre rotas ou regras — confie em `routes/api.php` e nos arquivos de `.ai/`. Ao notar novas divergências, avise o usuário.

---

## Estrutura de Contexto

```
.ai/
├── backend/                ← regras e contextos do backend (Laravel 11 / PHP 8.2)
│   ├── LEIA-ME.md
│   ├── architecture.md
│   ├── coding-standards.md
│   └── contexts/
│       ├── auth.md
│       ├── players.md
│       ├── peladas.md
│       ├── teams.md
│       ├── match-players.md
│       ├── statistics.md
│       └── admin.md
├── ai-rules.md             ← regras gerais e ordem de prioridade
├── project-context.md      ← contexto geral, stack, modelos, endpoints, deploy
└── feature-index.md        ← índice de todas as funcionalidades
```
