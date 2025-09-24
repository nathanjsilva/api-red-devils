# 🚀 Exemplos de Uso da API Red Devils

Este documento contém exemplos práticos de como usar a API Red Devils.

## 🔐 Autenticação

### 1. Login
```bash
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "joao@test.com",
    "password": "MinhaSenh@123"
  }'
```

**Resposta:**
```json
{
  "data": {
    "access_token": "1|abcdef123456789",
    "token_type": "Bearer",
    "player": {
      "id": 1,
      "name": "João Silva",
      "email": "joao@test.com",
      "position": "linha",
      "phone": "11999999999",
      "nickname": "João Gol",
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:00:00"
    }
  }
}
```

### 2. Logout
```bash
curl -X POST http://localhost/api/logout \
  -H "Authorization: Bearer 1|abcdef123456789"
```

**Resposta:**
```json
{
  "message": "Logout realizado com sucesso."
}
```

## 👥 Jogadores

### 1. Criar Jogador (Público)
```bash
curl -X POST http://localhost/api/players \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@test.com",
    "password": "MinhaSenh@123",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "João Gol"
  }'
```

### 2. Listar Jogadores (Autenticado)
```bash
curl -X GET http://localhost/api/players \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 3. Buscar Jogador Específico
```bash
curl -X GET http://localhost/api/players/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 4. Atualizar Jogador
```bash
curl -X PUT http://localhost/api/players/1 \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva Atualizado",
    "position": "goleiro",
    "nickname": "João Goleiro"
  }'
```

### 5. Deletar Jogador
```bash
curl -X DELETE http://localhost/api/players/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

## ⚽ Peladas

### 1. Criar Pelada
```bash
curl -X POST http://localhost/api/peladas \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-25",
    "location": "Campo do Bairro",
    "qtd_times": 2,
    "qtd_jogadores_por_time": 6,
    "qtd_goleiros": 2
  }'
```

### 2. Listar Peladas
```bash
curl -X GET http://localhost/api/peladas \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 3. Buscar Pelada Específica
```bash
curl -X GET http://localhost/api/peladas/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 4. Atualizar Pelada
```bash
curl -X PUT http://localhost/api/peladas/1 \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-31",
    "location": "Campo Central",
    "qtd_times": 4,
    "qtd_jogadores_por_time": 5,
    "qtd_goleiros": 4
  }'
```

### 5. Deletar Pelada
```bash
curl -X DELETE http://localhost/api/peladas/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

## 📊 Estatísticas (Match Players)

### 1. Registrar Estatísticas de Jogador
```bash
curl -X POST http://localhost/api/match-players \
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
curl -X POST http://localhost/api/match-players \
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
curl -X PUT http://localhost/api/match-players/1 \
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
curl -X DELETE http://localhost/api/match-players/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

## 📈 Estatísticas e Rankings

### 1. Estatísticas de Jogador em Pelada Específica
```bash
curl -X GET http://localhost/api/statistics/player/1/pelada/1 \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 2. Estatísticas Totais de Jogador
```bash
curl -X GET http://localhost/api/statistics/player/1/total \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 3. Ranking de Vitórias
```bash
curl -X GET http://localhost/api/statistics/rankings/wins \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 4. Ranking de Gols
```bash
curl -X GET http://localhost/api/statistics/rankings/goals \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 5. Ranking de Assistências
```bash
curl -X GET http://localhost/api/statistics/rankings/assists \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 6. Ranking de Participação em Gols
```bash
curl -X GET http://localhost/api/statistics/rankings/goal-participation \
  -H "Authorization: Bearer 1|abcdef123456789"
```

### 7. Ranking de Goleiros
```bash
curl -X GET http://localhost/api/statistics/rankings/goalkeepers \
  -H "Authorization: Bearer 1|abcdef123456789"
```

## 👨‍💼 Administração

### 1. Criar Jogador (Admin)
```bash
curl -X POST http://localhost/api/admin/players \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Admin Player",
    "email": "admin@test.com",
    "password": "MinhaSenh@123",
    "position": "linha",
    "phone": "11888888888",
    "nickname": "Admin"
  }'
```

### 2. Criar Pelada (Admin)
```bash
curl -X POST http://localhost/api/admin/peladas \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-25",
    "location": "Campo Admin",
    "qtd_times": 2,
    "qtd_jogadores_por_time": 6,
    "qtd_goleiros": 2
  }'
```

### 3. Organizar Times
```bash
curl -X POST http://localhost/api/admin/peladas/1/organize-teams \
  -H "Authorization: Bearer 1|abcdef123456789" \
  -H "Content-Type: application/json" \
  -d '{
    "player_ids": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
  }'
```

**Resposta:**
```json
{
  "message": "Times organizados com sucesso.",
  "teams": [
    {
      "id": 1,
      "name": "Time 1",
      "players": [
        {
          "id": 1,
          "name": "João Silva",
          "position": "goleiro"
        },
        {
          "id": 2,
          "name": "Pedro Santos",
          "position": "linha"
        }
      ]
    },
    {
      "id": 2,
      "name": "Time 2",
      "players": [
        {
          "id": 3,
          "name": "Maria Silva",
          "position": "goleiro"
        },
        {
          "id": 4,
          "name": "Ana Santos",
          "position": "linha"
        }
      ]
    }
  ]
}
```

## 🐛 Tratamento de Erros

### Erro de Validação (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Este e-mail já está cadastrado."],
    "password": ["A senha deve conter pelo menos 8 caracteres, incluindo maiúscula, minúscula, número e símbolo."]
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
4. **Senha forte**: Deve conter pelo menos 8 caracteres, incluindo maiúscula, minúscula, número e símbolo
5. **Campos únicos**: Email, telefone e nickname devem ser únicos

### Exemplos de Validação

#### ❌ Erro: Senha fraca
```bash
curl -X POST http://localhost/api/players \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@test.com",
    "password": "123",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "João"
  }'
```

**Resposta:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "password": ["A senha deve conter pelo menos 8 caracteres, incluindo maiúscula, minúscula, número e símbolo."]
  }
}
```

#### ❌ Erro: Goleiro sem goals_conceded
```bash
curl -X POST http://localhost/api/match-players \
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
curl -X POST http://localhost/api/players \
  -H "Content-Type: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@test.com",
    "password": "MinhaSenh@123",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "João Gol"
  }'

# Criar goleiro
curl -X POST http://localhost/api/players \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Pedro Santos",
    "email": "pedro@test.com",
    "password": "MinhaSenh@123",
    "position": "goleiro",
    "phone": "11888888888",
    "nickname": "Pedro Goleiro"
  }'
```

### 2. Fazer Login
```bash
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "joao@test.com", "password": "MinhaSenh@123"}'
```

### 3. Criar Pelada
```bash
curl -X POST http://localhost/api/peladas \
  -H "Authorization: Bearer [TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-25",
    "location": "Campo do Bairro",
    "qtd_times": 2,
    "qtd_jogadores_por_time": 6,
    "qtd_goleiros": 2
  }'
```

### 4. Registrar Estatísticas
```bash
# Estatísticas do jogador linha
curl -X POST http://localhost/api/match-players \
  -H "Authorization: Bearer [TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"player_id": 1, "pelada_id": 1, "goals": 2, "assists": 1, "is_winner": true}'

# Estatísticas do goleiro
curl -X POST http://localhost/api/match-players \
  -H "Authorization: Bearer [TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"player_id": 2, "pelada_id": 1, "goals": 0, "assists": 0, "is_winner": true, "goals_conceded": 1}'
```

### 5. Organizar Times (Admin)
```bash
curl -X POST http://localhost/api/admin/peladas/1/organize-teams \
  -H "Authorization: Bearer [TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"player_ids": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]}'
```

### 6. Consultar Rankings
```bash
# Ver ranking de gols
curl -X GET http://localhost/api/statistics/rankings/goals \
  -H "Authorization: Bearer [TOKEN]"

# Ver ranking de goleiros
curl -X GET http://localhost/api/statistics/rankings/goalkeepers \
  -H "Authorization: Bearer [TOKEN]"
```

## 🎯 Dicas de Uso

1. **Sempre use HTTPS em produção**
2. **Mantenha os tokens seguros**
3. **Use senhas fortes** (mínimo 8 caracteres com maiúscula, minúscula, número e símbolo)
4. **Valide dados no frontend antes de enviar**
5. **Organize times apenas quando necessário** (não pode ser desfeito)

## 📚 Recursos Adicionais

- [Laravel Sanctum Docs](https://laravel.com/docs/sanctum)
- [Laravel API Resources](https://laravel.com/docs/eloquent-resources)
- [Laravel Validation](https://laravel.com/docs/validation)