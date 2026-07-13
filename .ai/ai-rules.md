# .ai/ai-rules.md — Regras Gerais

## Ordem de prioridade

1. Instrução explícita do usuário na conversa atual.
2. Regras deste arquivo e dos demais arquivos em `.ai/`.
3. Convenções observadas no código existente (`app/`, `routes/`, `database/`).
4. `README.md` — **última prioridade**. Está desatualizado em vários pontos (veja aviso no [CLAUDE.md](../CLAUDE.md)) e não deve ser usado para decidir comportamento de rotas, autenticação ou regras de negócio.

Em caso de conflito entre o que o código faz e o que a documentação (`README.md`, `docs/`) descreve, **o código é a fonte de verdade**. Sinalize a divergência ao usuário em vez de silenciosamente seguir a documentação.

## Regras de comportamento

- **Explique antes de executar.** Descreva o que será feito, por quê, quais arquivos serão tocados e o impacto (rotas, banco, contratos de resposta da API).
- **Peça confirmação explícita** antes de qualquer alteração: `"Deseja que eu execute esta alteração?"`. Nunca presuma autorização a partir de um pedido genérico.
- **Nunca** delete, mova ou renomeie arquivos, nem altere regra de negócio (validações, cálculo de estatísticas, autorização admin) sem autorização explícita para aquela ação específica.
- **Nunca** rode `php artisan migrate`, `migrate:fresh`, `migrate:rollback` ou comandos que alterem dados em produção sem autorização explícita — veja [project-context.md](project-context.md#deploy).
- Após qualquer alteração, resuma: arquivos modificados, funcionalidades afetadas, e próximos passos sugeridos (ex.: "rodar migration", "atualizar testes").

## Este projeto é somente backend

Não existe frontend neste repositório. Se um pedido mencionar telas, componentes visuais ou frameworks de frontend, confirme com o usuário se ele está no repositório certo antes de prosseguir.
