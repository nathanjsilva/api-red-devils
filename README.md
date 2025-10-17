# API Red Devils

API para gerenciamento de peladas e estatísticas de jogadores.

## Requisitos

- PHP 8.2+
- MySQL 8.0+
- Docker & Docker Compose

## Instalação

### 🏠 Desenvolvimento Local

1. Clone o repositório
2. Execute `docker-compose up -d`
3. Execute `docker-compose exec app composer install`
4. Execute `docker-compose exec app php artisan migrate`
5. Configure o arquivo `.env` com suas credenciais

### ☁️ Deploy na Oracle Cloud

Para fazer deploy na Oracle Cloud Infrastructure (tier gratuito):

1. **Siga o guia completo:** [docs/DEPLOY_ORACLE.md](docs/DEPLOY_ORACLE.md)
2. **Guia rápido:** [docs/GUIA_RAPIDO_ORACLE.md](docs/GUIA_RAPIDO_ORACLE.md)
3. **Documentação completa:** [docs/README.md](docs/README.md)

#### Comandos principais:
```bash
# No servidor Oracle Linux
git clone https://github.com/SEU_USUARIO/api-red-devils.git
cd api-red-devils
cp env.production.example .env
# Editar .env com suas configurações
chmod +x deploy.sh
./deploy.sh
```

#### Testar API:
```bash
# Windows (desenvolvimento)
docs/test-api.bat http://SEU_IP_PUBLICO

# Linux/Mac (produção)
chmod +x test-api.sh
./test-api.sh http://SEU_IP_PUBLICO
```

### 📖 Documentação Adicional

- **Exemplos de API:** [docs/API_EXAMPLES.md](docs/API_EXAMPLES.md)
- **Scripts de desenvolvimento:** [docs/](docs/)

## Uso

A API estará disponível em `http://localhost:8000`

### Autenticação

Use o endpoint `/api/login` para obter um token de acesso.

### Principais Endpoints

- `POST /api/login` - Login
- `GET /api/players` - Listar jogadores
- `POST /api/players` - Cadastrar jogador
- `GET /api/peladas` - Listar peladas
- `POST /api/peladas` - Criar pelada
- `POST /api/match-players` - Registrar estatísticas
- `GET /api/statistics/*` - Estatísticas e rankings

## Estrutura

- **Controllers**: Lógica da aplicação
- **Models**: Modelos de dados
- **Requests**: Validações
- **Resources**: Formatação de respostas
- **Migrations**: Estrutura do banco

## Produção

Para produção, certifique-se de:
- Configurar `APP_ENV=production`
- Definir `APP_DEBUG=false`
- Configurar credenciais corretas do banco
- Executar `composer install --no-dev --optimize-autoloader`

---

# 📚 Documentação Completa da API

**Base URL:** `http://168.75.95.247/api`

## 🔐 Autenticação

A API utiliza **Laravel Sanctum** para autenticação via tokens. Todas as rotas protegidas requerem o header:
```
Authorization: Bearer {seu_token}
```

---

## 🚀 ROTAS PÚBLICAS

### **Autenticação**

#### `POST /api/login`
Faz login de um jogador no sistema.

**URL:** `POST http://168.75.95.247/api/login`

**Headers:**
```
Content-Type: application/json
```

**Payload:**
```json
{
    "email": "jogador@email.com",
    "password": "senha123"
}
```

**Validações:**
- `email`: obrigatório, formato de email válido
- `password`: obrigatório, string

**Resposta de Sucesso (200):**
```json
{
    "access_token": "1|token_hash_aqui",
    "token_type": "Bearer",
    "player": {
        "id": 1,
        "name": "Nome do Jogador",
        "email": "jogador@email.com",
        "position": "linha",
        "phone": "11999999999",
        "nickname": "apelido",
        "is_admin": false,
        "created_at": "2025-10-16T21:30:00",
        "updated_at": "2025-10-16T21:30:00"
    }
}
```

**Resposta de Erro (401):**
```json
{
    "message": "Credenciais inválidas"
}
```

#### `POST /api/logout`
Faz logout do usuário atual (remove o token).

**URL:** `POST http://168.75.95.247/api/logout`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Resposta de Sucesso (200):**
```json
{
    "message": "Logout realizado com sucesso."
}
```

---

### **Cadastro de Jogadores**

#### `POST /api/players`
Cadastra um novo jogador no sistema (rota pública).

**URL:** `POST http://168.75.95.247/api/players`

**Headers:**
```
Content-Type: application/json
```

**Payload:**
```json
{
    "name": "Nome Completo",
    "email": "jogador@email.com",
    "password": "Senha123!",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "apelido"
}
```

**Validações:**
- `name`: obrigatório, único, máximo 255 caracteres
- `email`: obrigatório, formato válido, único
- `password`: obrigatório, mínimo 8 caracteres, deve conter: 1 minúscula, 1 maiúscula, 1 número, 1 caractere especial
- `position`: obrigatório, deve ser "linha" ou "goleiro"
- `phone`: obrigatório, único
- `nickname`: obrigatório, único, máximo 255 caracteres

**Resposta de Sucesso (201):**
```json
{
    "id": 1,
    "name": "Nome Completo",
    "email": "jogador@email.com",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "apelido",
    "is_admin": false,
    "created_at": "2025-10-16T21:30:00",
    "updated_at": "2025-10-16T21:30:00"
}
```

**Resposta de Erro (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["Este e-mail já está cadastrado."],
        "nickname": ["Este apelido já está em uso."]
    }
}
```

---

### **Setup do Sistema**

#### `POST /api/setup-first-admin`
Cria o primeiro administrador do sistema (apenas se não existir nenhum admin).

**Payload:**
```json
{
    "name": "Admin Principal",
    "email": "admin@reddevils.com",
    "password": "Admin123!",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "admin"
}
```

**Resposta:**
```json
{
    "message": "Primeiro administrador criado com sucesso!",
    "player": {
        "id": 1,
        "name": "Admin Principal",
        "email": "admin@reddevils.com",
        "position": "linha",
        "phone": "11999999999",
        "nickname": "admin",
        "is_admin": true
    }
}
```

---

## 🔒 ROTAS PROTEGIDAS (Requerem Autenticação)

### **Gerenciamento de Jogadores**

#### `GET /api/players`
Lista todos os jogadores cadastrados.

**URL:** `GET http://168.75.95.247/api/players`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
[
    {
        "id": 1,
        "name": "Jogador 1",
        "email": "jogador1@email.com",
        "position": "linha",
        "phone": "11999999999",
        "nickname": "jogador1",
        "is_admin": false,
        "created_at": "2025-10-16T21:30:00",
        "updated_at": "2025-10-16T21:30:00"
    },
    {
        "id": 2,
        "name": "Jogador 2",
        "email": "jogador2@email.com",
        "position": "goleiro",
        "phone": "11888888888",
        "nickname": "jogador2",
        "is_admin": true,
        "created_at": "2025-10-16T21:30:00",
        "updated_at": "2025-10-16T21:30:00"
    }
]
```

#### `GET /api/players/{id}`
Busca um jogador específico por ID.

**URL:** `GET http://168.75.95.247/api/players/1`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
    "id": 1,
    "name": "Jogador 1",
    "email": "jogador1@email.com",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "jogador1",
    "is_admin": false,
    "created_at": "2025-10-16T21:30:00",
    "updated_at": "2025-10-16T21:30:00"
}
```

**Resposta de Erro (404):**
```json
{
    "message": "Jogador não encontrado."
}
```

#### `PUT /api/players/{id}`
Atualiza dados de um jogador.

**URL:** `PUT http://168.75.95.247/api/players/1`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload (todos os campos são opcionais):**
```json
{
    "name": "Novo Nome",
    "email": "novo@email.com",
    "password": "NovaSenha123!",
    "position": "goleiro",
    "phone": "11888888888",
    "nickname": "novo_apelido"
}
```

**Resposta de Sucesso (200):**
```json
{
    "id": 1,
    "name": "Novo Nome",
    "email": "novo@email.com",
    "position": "goleiro",
    "phone": "11888888888",
    "nickname": "novo_apelido",
    "is_admin": false,
    "created_at": "2025-10-16T21:30:00",
    "updated_at": "2025-10-16T22:00:00"
}
```

#### `DELETE /api/players/{id}`
Remove um jogador do sistema.

**URL:** `DELETE http://168.75.95.247/api/players/1`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
    "message": "Jogador deletado com sucesso."
}
```

---

### **Gerenciamento de Peladas**

#### `GET /api/peladas`
Lista todas as peladas cadastradas.

**URL:** `GET http://168.75.95.247/api/peladas`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
[
    {
        "id": 1,
        "date": "2025-10-20",
        "location": "Campo do João",
        "qtd_times": 4,
        "qtd_jogadores_por_time": 5,
        "qtd_goleiros": 4,
        "players": []
    }
]
```

#### `GET /api/peladas/{id}`
Busca uma pelada específica por ID.

**URL:** `GET http://168.75.95.247/api/peladas/1`

**Headers:**
```
Authorization: Bearer {token}
```

#### `GET /api/peladas/date/{date}`
Busca peladas por data específica.

**URL:** `GET http://168.75.95.247/api/peladas/date/2025-10-20`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
[
    {
        "id": 1,
        "date": "2025-10-20",
        "location": "Campo do João",
        "qtd_times": 4,
        "qtd_jogadores_por_time": 5,
        "qtd_goleiros": 4,
        "players": []
    }
]
```

**Resposta de Erro (404):**
```json
{
    "message": "Nenhuma pelada encontrada para esta data."
}
```

#### `POST /api/peladas`
Cria uma nova pelada.

**URL:** `POST http://168.75.95.247/api/peladas`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload:**
```json
{
    "date": "2025-10-20",
    "location": "Campo do João",
    "qtd_times": 4,
    "qtd_jogadores_por_time": 5,
    "qtd_goleiros": 4
}
```

**Validações:**
- `date`: obrigatório, formato data (YYYY-MM-DD)
- `location`: obrigatório, string
- `qtd_times`: obrigatório, inteiro, mínimo 2
- `qtd_jogadores_por_time`: obrigatório, inteiro, mínimo 1
- `qtd_goleiros`: obrigatório, inteiro, mínimo 2

#### `PUT /api/peladas/{id}`
Atualiza dados de uma pelada.

**URL:** `PUT http://168.75.95.247/api/peladas/1`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### `DELETE /api/peladas/{id}`
Remove uma pelada do sistema.

**URL:** `DELETE http://168.75.95.247/api/peladas/1`

**Headers:**
```
Authorization: Bearer {token}
```

---

### **Organização de Times**

#### `GET /api/teams/pelada/{peladaId}/fields`
Retorna os campos dos times baseado na quantidade configurada na pelada.

**URL:** `GET http://168.75.95.247/api/teams/pelada/1/fields`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
    "pelada": {
        "id": 1,
        "date": "2025-10-20",
        "location": "Campo do João",
        "qtd_times": 4,
        "qtd_jogadores_por_time": 5,
        "qtd_goleiros": 4
    },
    "team_fields": [
        {
            "field_name": "time_1",
            "label": "Time 1",
            "team_number": 1
        },
        {
            "field_name": "time_2",
            "label": "Time 2",
            "team_number": 2
        },
        {
            "field_name": "time_3",
            "label": "Time 3",
            "team_number": 3
        },
        {
            "field_name": "time_4",
            "label": "Time 4",
            "team_number": 4
        }
    ]
}
```

#### `GET /api/teams/pelada/{peladaId}/players`
Retorna os jogadores que participaram de uma pelada específica.

**URL:** `GET http://168.75.95.247/api/teams/pelada/1/players`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
    "pelada": {
        "id": 1,
        "date": "2025-10-20",
        "location": "Campo do João"
    },
    "players": [
        {
            "id": 1,
            "name": "Jogador 1",
            "nickname": "jogador1",
            "position": "linha",
            "phone": "11999999999",
            "is_goalkeeper": false
        },
        {
            "id": 2,
            "name": "Goleiro 1",
            "nickname": "goleiro1",
            "position": "goleiro",
            "phone": "11888888888",
            "is_goalkeeper": true
        }
    ]
}
```

#### `POST /api/teams/pelada/{peladaId}/organize`
Organiza jogadores nos times da pelada.

**URL:** `POST http://168.75.95.247/api/teams/pelada/1/organize`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload:**
```json
{
    "team_assignments": [
        {
            "team_number": 1,
            "player_ids": [1, 2, 3, 4, 5]
        },
        {
            "team_number": 2,
            "player_ids": [6, 7, 8, 9, 10]
        },
        {
            "team_number": 3,
            "player_ids": [11, 12, 13, 14, 15]
        },
        {
            "team_number": 4,
            "player_ids": [16, 17, 18, 19, 20]
        }
    ]
}
```

**Validações:**
- `team_assignments`: obrigatório, array
- `team_assignments.*.team_number`: obrigatório, inteiro entre 1 e quantidade de times da pelada
- `team_assignments.*.player_ids`: obrigatório, array de IDs de jogadores
- Todos os jogadores devem ter participado da pelada

**Resposta de Sucesso (200):**
```json
{
    "message": "Times organizados com sucesso.",
    "teams": [
        {
            "id": 1,
            "name": "Time 1",
            "team_number": 1,
            "players": [
                {
                    "id": 1,
                    "name": "Jogador 1",
                    "nickname": "jogador1",
                    "position": "linha"
                },
                {
                    "id": 2,
                    "name": "Goleiro 1",
                    "nickname": "goleiro1",
                    "position": "goleiro"
                }
            ]
        }
    ]
}
```

**Resposta de Erro (400):**
```json
{
    "message": "Times já foram organizados para esta pelada."
}
```

#### `GET /api/teams/pelada/{peladaId}/organized`
Retorna os times já organizados de uma pelada.

**URL:** `GET http://168.75.95.247/api/teams/pelada/1/organized`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
    "pelada": {
        "id": 1,
        "date": "2025-10-20",
        "location": "Campo do João"
    },
    "teams": [
        {
            "id": 1,
            "name": "Time 1",
            "players": [
                {
                    "id": 1,
                    "name": "Jogador 1",
                    "nickname": "jogador1",
                    "position": "linha"
                }
            ]
        }
    ]
}
```

---

### **Estatísticas de Jogadores nas Peladas**

#### `POST /api/match-players`
Registra estatísticas de um jogador em uma pelada.

**URL:** `POST http://168.75.95.247/api/match-players`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Payload:**
```json
{
    "player_id": 1,
    "pelada_id": 1,
    "goals": 2,
    "assists": 1,
    "goals_conceded": 0,
    "is_winner": true
}
```

**Validações:**
- `player_id`: obrigatório, deve existir na tabela players
- `pelada_id`: obrigatório, deve existir na tabela peladas
- `goals`: obrigatório, inteiro >= 0
- `assists`: obrigatório, inteiro >= 0
- `goals_conceded`: opcional, inteiro >= 0 (apenas para goleiros)
- `is_winner`: obrigatório, boolean

**Resposta de Sucesso (201):**
```json
{
    "id": 1,
    "player_id": 1,
    "pelada_id": 1,
    "player": {
        "id": 1,
        "name": "Jogador 1",
        "nickname": "jogador1",
        "position": "linha"
    },
    "pelada": {
        "id": 1,
        "date": "2025-10-20",
        "location": "Campo do João"
    },
    "goals": 2,
    "assists": 1,
    "goals_conceded": 0,
    "is_winner": true,
    "created_at": "2025-10-16T21:30:00",
    "updated_at": "2025-10-16T21:30:00"
}
```

#### `PUT /api/match-players/{id}`
Atualiza estatísticas de um jogador em uma pelada.

**URL:** `PUT http://168.75.95.247/api/match-players/1`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### `DELETE /api/match-players/{id}`
Remove registro de estatísticas.

**URL:** `DELETE http://168.75.95.247/api/match-players/1`

**Headers:**
```
Authorization: Bearer {token}
```

---

### **Estatísticas e Rankings**

#### `GET /api/statistics/pelada/{peladaId}`
Obtém estatísticas de uma pelada específica, separando jogadores de linha e goleiros.

**URL:** `GET http://168.75.95.247/api/statistics/pelada/1`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
    "pelada": {
        "id": 1,
        "date": "2025-10-20",
        "location": "Campo do João",
        "qtd_times": 4,
        "qtd_jogadores_por_time": 5,
        "qtd_goleiros": 4
    },
    "statistics": {
        "field_players": [
            {
                "player": {
                    "id": 1,
                    "name": "Jogador 1",
                    "nickname": "jogador1",
                    "position": "linha"
                },
                "statistics": {
                    "goals": 2,
                    "assists": 1,
                    "is_winner": true,
                    "goal_participation": 3
                }
            }
        ],
        "goalkeepers": [
            {
                "player": {
                    "id": 2,
                    "name": "Goleiro 1",
                    "nickname": "goleiro1",
                    "position": "goleiro"
                },
                "statistics": {
                    "goals": 0,
                    "assists": 1,
                    "is_winner": true,
                    "goal_participation": 1,
                    "goals_conceded": 2
                }
            }
        ],
        "total_players": 20,
        "total_goals": 15,
        "total_assists": 8,
        "winners_count": 10
    }
}
```

#### `GET /api/statistics/player/{playerId}/pelada/{peladaId}`
Obtém estatísticas de um jogador em uma pelada específica.

**URL:** `GET http://168.75.95.247/api/statistics/player/1/pelada/1`

**Headers:**
```
Authorization: Bearer {token}
```

#### `GET /api/statistics/player/{playerId}/total`
Obtém estatísticas totais de um jogador.

**URL:** `GET http://168.75.95.247/api/statistics/player/1/total`

**Headers:**
```
Authorization: Bearer {token}
```

#### `GET /api/statistics/rankings/wins`
Ranking de vitórias dos jogadores.

**URL:** `GET http://168.75.95.247/api/statistics/rankings/wins`

**Headers:**
```
Authorization: Bearer {token}
```

#### `GET /api/statistics/rankings/goals`
Ranking de gols dos jogadores.

**URL:** `GET http://168.75.95.247/api/statistics/rankings/goals`

**Headers:**
```
Authorization: Bearer {token}
```

#### `GET /api/statistics/rankings/assists`
Ranking de assistências dos jogadores.

**URL:** `GET http://168.75.95.247/api/statistics/rankings/assists`

**Headers:**
```
Authorization: Bearer {token}
```

#### `GET /api/statistics/rankings/goal-participation`
Ranking de participação em gols (gols + assistências).

**URL:** `GET http://168.75.95.247/api/statistics/rankings/goal-participation`

**Headers:**
```
Authorization: Bearer {token}
```

#### `GET /api/statistics/rankings/goalkeepers`
Ranking de goleiros (menor média de gols sofridos = melhor).

**URL:** `GET http://168.75.95.247/api/statistics/rankings/goalkeepers`

**Headers:**
```
Authorization: Bearer {token}
```

## 👑 ROTAS ADMINISTRATIVAS (Requerem Admin)

> **⚠️ Importante:** Todas as rotas administrativas requerem que o usuário tenha `is_admin: true` e o token de autenticação.

### **Gerenciamento de Jogadores (Admin)**

#### `POST /api/admin/players`
Cadastra um jogador (admin pode definir `is_admin`).

**URL:** `POST http://168.75.95.247/api/admin/players`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Payload:**
```json
{
    "name": "Novo Jogador",
    "email": "novo@email.com",
    "password": "Senha123!",
    "position": "linha",
    "phone": "11777777777",
    "nickname": "novo_jogador",
    "is_admin": false
}
```

**Validações:**
- `name`: obrigatório, único, máximo 255 caracteres
- `email`: opcional, formato válido, único
- `password`: obrigatório, mínimo 8 caracteres com critérios de segurança
- `position`: obrigatório, "linha" ou "goleiro"
- `phone`: obrigatório, único
- `nickname`: obrigatório, único, máximo 255 caracteres
- `is_admin`: opcional, boolean

**Resposta de Sucesso (201):**
```json
{
    "id": 3,
    "name": "Novo Jogador",
    "email": "novo@email.com",
    "position": "linha",
    "phone": "11777777777",
    "nickname": "novo_jogador",
    "is_admin": false,
    "created_at": "2025-10-16T21:30:00",
    "updated_at": "2025-10-16T21:30:00"
}
```

#### `PUT /api/admin/players/{id}`
Atualiza dados de um jogador (admin pode alterar `is_admin`).

**URL:** `PUT http://168.75.95.247/api/admin/players/1`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Payload (todos os campos são opcionais):**
```json
{
    "name": "Nome Atualizado",
    "email": "novo@email.com",
    "password": "NovaSenha123!",
    "position": "goleiro",
    "phone": "11666666666",
    "nickname": "novo_apelido",
    "is_admin": true
}
```

#### `DELETE /api/admin/players/{id}`
Remove um jogador do sistema.

**URL:** `DELETE http://168.75.95.247/api/admin/players/1`

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Resposta de Sucesso (200):**
```json
{
    "message": "Jogador deletado com sucesso."
}
```

---

### **Gerenciamento de Peladas (Admin)**

#### `POST /api/admin/peladas`
Cria uma nova pelada.

**URL:** `POST http://168.75.95.247/api/admin/peladas`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Payload:**
```json
{
    "date": "2025-10-20",
    "location": "Campo do João",
    "qtd_times": 4,
    "qtd_jogadores_por_time": 5,
    "qtd_goleiros": 4
}
```

#### `PUT /api/admin/peladas/{id}`
Atualiza dados de uma pelada.

**URL:** `PUT http://168.75.95.247/api/admin/peladas/1`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

#### `DELETE /api/admin/peladas/{id}`
Remove uma pelada do sistema.

**URL:** `DELETE http://168.75.95.247/api/admin/peladas/1`

**Headers:**
```
Authorization: Bearer {admin_token}
```

---

### **Estatísticas (Admin)**

#### `POST /api/admin/match-players`
Registra estatísticas de um jogador em uma pelada.

**URL:** `POST http://168.75.95.247/api/admin/match-players`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Payload:**
```json
{
    "player_id": 1,
    "pelada_id": 1,
    "goals": 2,
    "assists": 1,
    "goals_conceded": 0,
    "is_winner": true
}
```

#### `PUT /api/admin/match-players/{id}`
Atualiza estatísticas de um jogador em uma pelada.

**URL:** `PUT http://168.75.95.247/api/admin/match-players/1`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

#### `DELETE /api/admin/match-players/{id}`
Remove registro de estatísticas.

**URL:** `DELETE http://168.75.95.247/api/admin/match-players/1`

**Headers:**
```
Authorization: Bearer {admin_token}
```

---

### **Organização de Times (Admin)**

#### `POST /api/admin/peladas/{peladaId}/organize-teams`
Organiza times automaticamente para uma pelada.

**URL:** `POST http://168.75.95.247/api/admin/peladas/1/organize-teams`

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**Payload:**
```json
{
    "player_ids": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
}
```

**Validações:**
- `player_ids`: obrigatório, array com IDs dos jogadores
- Deve ter pelo menos a quantidade necessária de jogadores
- Deve ter goleiros suficientes conforme configurado na pelada

**Resposta de Sucesso (200):**
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
                    "name": "Jogador 1",
                    "nickname": "jogador1",
                    "position": "goleiro"
                },
                {
                    "id": 2,
                    "name": "Jogador 2",
                    "nickname": "jogador2",
                    "position": "linha"
                }
            ]
        }
    ]
}
```

**Resposta de Erro (400):**
```json
{
    "message": "Número insuficiente de goleiros."
}
```

---

### **Gerenciamento de Permissões Admin**

#### `POST /api/admin/players/{id}/make-admin`
Transforma um jogador em administrador.

**URL:** `POST http://168.75.95.247/api/admin/players/1/make-admin`

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Resposta de Sucesso (200):**
```json
{
    "message": "Jogador transformado em admin com sucesso.",
    "player": {
        "id": 1,
        "name": "Jogador 1",
        "email": "jogador1@email.com",
        "position": "linha",
        "phone": "11999999999",
        "nickname": "jogador1",
        "is_admin": true,
        "created_at": "2025-10-16T21:30:00",
        "updated_at": "2025-10-16T22:00:00"
    }
}
```

#### `POST /api/admin/players/{id}/remove-admin`
Remove permissões de administrador de um jogador.

**URL:** `POST http://168.75.95.247/api/admin/players/1/remove-admin`

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Resposta de Sucesso (200):**
```json
{
    "message": "Permissões de admin removidas com sucesso.",
    "player": {
        "id": 1,
        "name": "Jogador 1",
        "email": "jogador1@email.com",
        "position": "linha",
        "phone": "11999999999",
        "nickname": "jogador1",
        "is_admin": false,
        "created_at": "2025-10-16T21:30:00",
        "updated_at": "2025-10-16T22:00:00"
    }
}
```

**Resposta de Erro (400):**
```json
{
    "message": "Não é possível remover o último administrador do sistema."
}
```

> **⚠️ Observação:** Não é possível remover o último administrador do sistema.

---

## 📋 CÓDIGOS DE RESPOSTA HTTP

- **200** - Sucesso
- **201** - Criado com sucesso
- **400** - Erro de validação ou requisição inválida
- **401** - Não autorizado (token inválido)
- **403** - Acesso negado (não é admin)
- **404** - Recurso não encontrado
- **422** - Erro de validação dos dados

---

## 🚨 TRATAMENTO DE ERROS

Todas as rotas retornam erros no formato:

```json
{
    "message": "Descrição do erro",
    "errors": {
        "campo": ["Mensagem de erro específica"]
    }
}
```

---

## 🔧 EXEMPLOS DE USO COMPLETO

### **Fluxo 1: Setup Inicial do Sistema**

#### 1. Criar primeiro admin:
```bash
curl -X POST http://168.75.95.247/api/setup-first-admin \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Admin Principal",
    "email": "admin@reddevils.com",
    "password": "Admin123!",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "admin"
  }'
```

#### 2. Fazer login:
```bash
curl -X POST http://168.75.95.247/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@reddevils.com",
    "password": "Admin123!"
  }'
```

**Resposta:**
```json
{
    "access_token": "1|token_hash_aqui",
    "token_type": "Bearer",
    "player": {
        "id": 1,
        "name": "Admin Principal",
        "email": "admin@reddevils.com",
        "is_admin": true
    }
}
```

### **Fluxo 2: Gerenciamento de Pelada e Times**

#### 1. Criar uma pelada:
```bash
curl -X POST http://168.75.95.247/api/admin/peladas \
  -H "Authorization: Bearer 1|seu_token_aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2025-10-20",
    "location": "Campo do João",
    "qtd_times": 4,
    "qtd_jogadores_por_time": 5,
    "qtd_goleiros": 4
  }'
```

#### 2. Buscar pelada por data:
```bash
curl -X GET http://168.75.95.247/api/peladas/date/2025-10-20 \
  -H "Authorization: Bearer 1|seu_token_aqui"
```

#### 3. Obter campos dos times:
```bash
curl -X GET http://168.75.95.247/api/teams/pelada/1/fields \
  -H "Authorization: Bearer 1|seu_token_aqui"
```

#### 4. Obter jogadores da pelada:
```bash
curl -X GET http://168.75.95.247/api/teams/pelada/1/players \
  -H "Authorization: Bearer 1|seu_token_aqui"
```

#### 5. Organizar times:
```bash
curl -X POST http://168.75.95.247/api/teams/pelada/1/organize \
  -H "Authorization: Bearer 1|seu_token_aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "team_assignments": [
        {
            "team_number": 1,
            "player_ids": [1, 2, 3, 4, 5]
        },
        {
            "team_number": 2,
            "player_ids": [6, 7, 8, 9, 10]
        }
    ]
  }'
```

### **Fluxo 3: Estatísticas de Pelada**

#### 1. Registrar estatísticas de jogadores:
```bash
curl -X POST http://168.75.95.247/api/admin/match-players \
  -H "Authorization: Bearer 1|seu_token_aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "player_id": 1,
    "pelada_id": 1,
    "goals": 2,
    "assists": 1,
    "goals_conceded": 0,
    "is_winner": true
  }'
```

#### 2. Obter estatísticas da pelada:
```bash
curl -X GET http://168.75.95.247/api/statistics/pelada/1 \
  -H "Authorization: Bearer 1|seu_token_aqui"
```

### **Fluxo 4: Gerenciamento de Admins**

#### 1. Promover jogador a admin:
```bash
curl -X POST http://168.75.95.247/api/admin/players/2/make-admin \
  -H "Authorization: Bearer 1|seu_token_aqui"
```

#### 2. Verificar se é admin:
```bash
curl -X GET http://168.75.95.247/api/players/2 \
  -H "Authorization: Bearer 1|seu_token_aqui"
```

---

## 📝 NOTAS IMPORTANTES

1. **Autenticação:** Todas as rotas protegidas requerem o token no header `Authorization: Bearer {token}`
2. **Admin:** Apenas usuários com `is_admin: true` podem acessar rotas `/admin/*`
3. **Validação:** Todos os campos são validados conforme as regras definidas
4. **Segurança:** Senhas são criptografadas automaticamente
5. **Tokens:** Tokens expiram conforme configuração do Sanctum
6. **Único Admin:** O sistema sempre mantém pelo menos um administrador

---

**🎯 Sistema completo para gerenciamento de peladas e estatísticas de jogadores!**
**🎯 Sistema completo para gerenciamento de peladas e estatísticas de jogadores!**