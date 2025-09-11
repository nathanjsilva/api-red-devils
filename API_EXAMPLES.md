# 🚀 Exemplos de Uso da API Red Devils

Este documento contém exemplos práticos de como usar a API Red Devils.

## 🔐 Autenticação

### 1. Login
```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "joao@test.com",
    "password": "123456"
  }'
```

**Resposta:**
```json
{
  "access_token": "1|abcdef123456789",
  "token_type": "Bearer",
  "player": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@test.com",
    "position": "linha",
    "created_at": "2024-01-01 12:00:00",
    "updated_at": "2024-01-01 12:00:00"
  }
}
```

## 👥 Jogadores

### 1. Criar Jogador (Público)
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

### 2. Listar Jogadores (Autenticado)
```bash
curl -X GET http://localhost:8080/api/players \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 3. Buscar Jogador Específico
```bash
curl -X GET http://localhost:8080/api/players/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 4. Atualizar Jogador
```bash
curl -X PUT http://localhost:8080/api/players/1 \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva Atualizado",
    "position": "goleiro"
  }'
```

### 5. Deletar Jogador
```bash
curl -X DELETE http://localhost:8080/api/players/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

## ⚽ Peladas

### 1. Criar Pelada
```bash
curl -X POST http://localhost:8080/api/peladas \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-25"
  }'
```

### 2. Listar Peladas
```bash
curl -X GET http://localhost:8080/api/peladas \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 3. Buscar Pelada Específica
```bash
curl -X GET http://localhost:8080/api/peladas/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 4. Atualizar Pelada
```bash
curl -X PUT http://localhost:8080/api/peladas/1 \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-31"
  }'
```

### 5. Deletar Pelada
```bash
curl -X DELETE http://localhost:8080/api/peladas/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

## 📊 Estatísticas (Match Players)

### 1. Registrar Estatísticas de Jogador
```bash
curl -X POST http://localhost:8080/api/match-players \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "player_id": 1,
    "pelada_id": 1,
    "goals": 2,
    "assists": 1,
    "is_winner": true,
    "goals_conceded": null
  }'
```

### 2. Registrar Estatísticas de Goleiro
```bash
curl -X POST http://localhost:8080/api/match-players \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "player_id": 2,
    "pelada_id": 1,
    "goals": 0,
    "assists": 0,
    "is_winner": true,
    "goals_conceded": 1
  }'
```

### 3. Atualizar Estatísticas
```bash
curl -X PUT http://localhost:8080/api/match-players/1 \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "goals": 3,
    "assists": 2,
    "is_winner": true
  }'
```

### 4. Remover Estatísticas
```bash
curl -X DELETE http://localhost:8080/api/match-players/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

## 🧪 Testando com Postman

### Collection Import
1. Abra o Postman
2. Clique em "Import"
3. Cole o JSON da collection (disponível em `/docs/postman-collection.json`)

### Variáveis de Ambiente
Configure as seguintes variáveis no Postman:
- `base_url`: `http://localhost:8080/api`
- `token`: (será preenchido automaticamente após login)

## 🐛 Tratamento de Erros

### Erro de Validação (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Este e-mail já está cadastrado."],
    "position": ["A posição deve ser \"linha\" ou \"goleiro\"."]
  }
}
```

### Erro de Autenticação (401)
```json
{
  "message": "Credenciais inválidas"
}
```

### Erro de Recurso Não Encontrado (404)
```json
{
  "message": "Jogador não encontrado."
}
```

## 📝 Regras de Negócio

### Validações Especiais

1. **Jogador já na pelada**: Um jogador não pode ser registrado duas vezes na mesma pelada
2. **Goleiro obrigatório**: Goleiros devem ter o campo `goals_conceded` preenchido
3. **Data da pelada**: Não pode ser anterior a hoje
4. **Limites de estatísticas**: Gols e assistências limitados a 20

### Exemplos de Validação

#### ❌ Erro: Jogador já na pelada
```bash
curl -X POST http://localhost:8080/api/match-players \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "player_id": 1,
    "pelada_id": 1,
    "goals": 1
  }'
```

**Resposta:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "player_id": ["Este jogador já está registrado nesta pelada."]
  }
}
```

#### ❌ Erro: Goleiro sem goals_conceded
```bash
curl -X POST http://localhost:8080/api/match-players \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "player_id": 2,
    "pelada_id": 1,
    "goals": 0
  }'
```

**Resposta:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "goals_conceded": ["Goleiros devem ter o campo \"gols sofridos\" preenchido."]
  }
}
```

## 🔄 Fluxo Completo de Uso

### 1. Criar Jogadores
```bash
# Criar jogador linha
curl -X POST http://localhost:8080/api/players \
  -H "Content-Type: application/json" \
  -d '{"name": "João Silva", "email": "joao@test.com", "password": "123456", "position": "linha"}'

# Criar goleiro
curl -X POST http://localhost:8080/api/players \
  -H "Content-Type: application/json" \
  -d '{"name": "Pedro Santos", "email": "pedro@test.com", "password": "123456", "position": "goleiro"}'
```

### 2. Fazer Login
```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "joao@test.com", "password": "123456"}'
```

### 3. Criar Pelada
```bash
curl -X POST http://localhost:8080/api/peladas \
  -H "Authorization: Bearer [TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"date": "2024-12-25"}'
```

### 4. Registrar Estatísticas
```bash
# Estatísticas do jogador linha
curl -X POST http://localhost:8080/api/match-players \
  -H "Authorization: Bearer [TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"player_id": 1, "pelada_id": 1, "goals": 2, "assists": 1, "is_winner": true}'

# Estatísticas do goleiro
curl -X POST http://localhost:8080/api/match-players \
  -H "Authorization: Bearer [TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"player_id": 2, "pelada_id": 1, "goals": 0, "assists": 0, "is_winner": true, "goals_conceded": 1}'
```

### 5. Consultar Resultados
```bash
# Ver pelada com jogadores
curl -X GET http://localhost:8080/api/peladas/1 \
  -H "Authorization: Bearer [TOKEN]"
```

## 🎯 Dicas de Uso

1. **Sempre use HTTPS em produção**
2. **Mantenha os tokens seguros**
3. **Implemente rate limiting no frontend**
4. **Use paginação para listas grandes**
5. **Valide dados no frontend antes de enviar**

## 📚 Recursos Adicionais

- [Documentação Swagger](http://localhost:8080/api/documentation)
- [Laravel Sanctum Docs](https://laravel.com/docs/sanctum)
- [Laravel API Resources](https://laravel.com/docs/eloquent-resources)
