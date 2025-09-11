# 🧪 Testando a API Red Devils

Este arquivo contém exemplos práticos para testar a API.

## 🚀 Como Testar

### 1. Iniciar o Servidor
```bash
# Com Docker
docker-compose up -d

# Ou localmente
php artisan serve
```

### 2. Testar Endpoints

#### Criar Jogador (Público)
```bash
curl -X POST http://localhost:8080/api/players \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@test.com",
    "password": "123456",
    "position": "linha"
  }'
```

#### Fazer Login
```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "joao@test.com",
    "password": "123456"
  }'
```

#### Criar Pelada (Autenticado)
```bash
curl -X POST http://localhost:8080/api/peladas \
  -H "Authorization: Bearer [SEU_TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-25"
  }'
```

#### Registrar Estatísticas
```bash
curl -X POST http://localhost:8080/api/match-players \
  -H "Authorization: Bearer [SEU_TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{
    "player_id": 1,
    "pelada_id": 1,
    "goals": 2,
    "assists": 1,
    "is_winner": true
  }'
```

## 📊 Status dos Testes

### ✅ Implementado
- [x] Form Requests para validação
- [x] API Resources para respostas padronizadas
- [x] Correções de segurança
- [x] Validações de regras de negócio
- [x] Testes automatizados
- [x] Documentação Swagger
- [x] README completo
- [x] Exemplos de uso

### 🔄 Em Progresso
- [ ] Execução completa dos testes
- [ ] Geração da documentação Swagger
- [ ] Testes manuais da API

## 🎯 Próximos Passos

1. **Executar testes**: `php artisan test`
2. **Gerar Swagger**: `php artisan l5-swagger:generate`
3. **Testar API**: Usar os exemplos acima
4. **Deploy**: Configurar para produção

## 🐛 Problemas Conhecidos

- Alguns testes podem falhar devido a configurações de banco
- Swagger pode precisar de configuração adicional
- Terminal pode ter problemas de execução

## 💡 Soluções

- Verificar configuração do banco de dados
- Executar migrações: `php artisan migrate`
- Limpar cache: `php artisan cache:clear`
- Regenerar autoload: `composer dump-autoload`
