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

**Payload:**
```json
{
    "email": "jogador@email.com",
    "password": "senha123"
}
```

**Resposta:**
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
        "is_admin": false
    }
}
```

#### `POST /api/logout`
Faz logout do usuário atual (remove o token).

**Headers:** `Authorization: Bearer {token}`

**Resposta:**
```json
{
    "message": "Logout realizado com sucesso."
}
```

---

### **Cadastro de Jogadores**

#### `POST /api/players`
Cadastra um novo jogador no sistema (rota pública).

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

**Resposta:**
```json
{
    "id": 1,
    "name": "Nome Completo",
    "email": "jogador@email.com",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "apelido",
    "is_admin": false,
    "created_at": "2025-10-16T21:30:00.000000Z",
    "updated_at": "2025-10-16T21:30:00.000000Z"
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

**Headers:** `Authorization: Bearer {token}`

**Resposta:**
```json
[
    {
        "id": 1,
        "name": "Jogador 1",
        "email": "jogador1@email.com",
        "position": "linha",
        "phone": "11999999999",
        "nickname": "jogador1",
        "is_admin": false
    }
]
```

#### `GET /api/players/{id}`
Busca um jogador específico por ID.

**Headers:** `Authorization: Bearer {token}`

**Resposta:**
```json
{
    "id": 1,
    "name": "Jogador 1",
    "email": "jogador1@email.com",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "jogador1",
    "is_admin": false
}
```

#### `PUT /api/players/{id}`
Atualiza dados de um jogador.

**Headers:** `Authorization: Bearer {token}`

**Payload:**
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

**Resposta:**
```json
{
    "id": 1,
    "name": "Novo Nome",
    "email": "novo@email.com",
    "position": "goleiro",
    "phone": "11888888888",
    "nickname": "novo_apelido",
    "is_admin": false
}
```

#### `DELETE /api/players/{id}`
Remove um jogador do sistema.

**Headers:** `Authorization: Bearer {token}`

**Resposta:**
```json
{
    "message": "Jogador deletado com sucesso."
}
```

---

### **Gerenciamento de Peladas**

#### `GET /api/peladas`
Lista todas as peladas cadastradas.

**Headers:** `Authorization: Bearer {token}`

**Resposta:**
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

**Headers:** `Authorization: Bearer {token}`

#### `POST /api/peladas`
Cria uma nova pelada.

**Headers:** `Authorization: Bearer {token}`

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

#### `PUT /api/peladas/{id}`
Atualiza dados de uma pelada.

**Headers:** `Authorization: Bearer {token}`

#### `DELETE /api/peladas/{id}`
Remove uma pelada do sistema.

**Headers:** `Authorization: Bearer {token}`

---

### **Estatísticas de Jogadores nas Peladas**

#### `POST /api/match-players`
Registra estatísticas de um jogador em uma pelada.

**Headers:** `Authorization: Bearer {token}`

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

#### `PUT /api/match-players/{id}`
Atualiza estatísticas de um jogador em uma pelada.

**Headers:** `Authorization: Bearer {token}`

#### `DELETE /api/match-players/{id}`
Remove registro de estatísticas.

**Headers:** `Authorization: Bearer {token}`

---

### **Estatísticas e Rankings**

#### `GET /api/statistics/player/{playerId}/pelada/{peladaId}`
Obtém estatísticas de um jogador em uma pelada específica.

**Headers:** `Authorization: Bearer {token}`

**Resposta:**
```json
{
    "player": {
        "id": 1,
        "name": "Jogador 1",
        "nickname": "jogador1"
    },
    "pelada": {
        "id": 1,
        "date": "2025-10-20",
        "location": "Campo do João"
    },
    "statistics": {
        "goals": 2,
        "assists": 1,
        "goals_conceded": 0,
        "is_winner": true,
        "goal_participation": 3
    }
}
```

#### `GET /api/statistics/player/{playerId}/total`
Obtém estatísticas totais de um jogador.

**Headers:** `Authorization: Bearer {token}`

**Resposta:**
```json
{
    "player": {
        "id": 1,
        "name": "Jogador 1",
        "nickname": "jogador1"
    },
    "total_statistics": {
        "total_goals": 15,
        "total_assists": 8,
        "total_goals_conceded": 12,
        "total_matches": 10,
        "total_wins": 7,
        "win_rate": 70.0,
        "avg_goal_participation": 2.3
    }
}
```

#### `GET /api/statistics/rankings/wins`
Ranking de vitórias dos jogadores.

**Headers:** `Authorization: Bearer {token}`

#### `GET /api/statistics/rankings/goals`
Ranking de gols dos jogadores.

**Headers:** `Authorization: Bearer {token}`

#### `GET /api/statistics/rankings/assists`
Ranking de assistências dos jogadores.

**Headers:** `Authorization: Bearer {token}`

#### `GET /api/statistics/rankings/goal-participation`
Ranking de participação em gols (gols + assistências).

**Headers:** `Authorization: Bearer {token}`

#### `GET /api/statistics/rankings/goalkeepers`
Ranking de goleiros (menor média de gols sofridos = melhor).

**Headers:** `Authorization: Bearer {token}`

---

## 👑 ROTAS ADMINISTRATIVAS (Requerem Admin)

### **Gerenciamento de Jogadores (Admin)**

#### `POST /api/admin/players`
Cadastra um jogador (admin pode definir `is_admin`).

**Headers:** `Authorization: Bearer {admin_token}`

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

#### `PUT /api/admin/players/{id}`
Atualiza dados de um jogador (admin pode alterar `is_admin`).

**Headers:** `Authorization: Bearer {admin_token}`

#### `DELETE /api/admin/players/{id}`
Remove um jogador do sistema.

**Headers:** `Authorization: Bearer {admin_token}`

---

### **Gerenciamento de Peladas (Admin)**

#### `POST /api/admin/peladas`
Cria uma nova pelada.

**Headers:** `Authorization: Bearer {admin_token}`

#### `PUT /api/admin/peladas/{id}`
Atualiza dados de uma pelada.

**Headers:** `Authorization: Bearer {admin_token}`

#### `DELETE /api/admin/peladas/{id}`
Remove uma pelada do sistema.

**Headers:** `Authorization: Bearer {admin_token}`

---

### **Estatísticas (Admin)**

#### `POST /api/admin/match-players`
Registra estatísticas de um jogador em uma pelada.

**Headers:** `Authorization: Bearer {admin_token}`

#### `PUT /api/admin/match-players/{id}`
Atualiza estatísticas de um jogador em uma pelada.

**Headers:** `Authorization: Bearer {admin_token}`

#### `DELETE /api/admin/match-players/{id}`
Remove registro de estatísticas.

**Headers:** `Authorization: Bearer {admin_token}`

---

### **Organização de Times**

#### `POST /api/admin/peladas/{peladaId}/organize-teams`
Organiza times automaticamente para uma pelada.

**Headers:** `Authorization: Bearer {admin_token}`

**Payload:**
```json
{
    "player_ids": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
}
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
                    "name": "Jogador 1",
                    "position": "goleiro"
                }
            ]
        }
    ]
}
```

---

### **Gerenciamento de Permissões Admin**

#### `POST /api/admin/players/{id}/make-admin`
Transforma um jogador em administrador.

**Headers:** `Authorization: Bearer {admin_token}`

**Resposta:**
```json
{
    "message": "Jogador transformado em admin com sucesso.",
    "player": {
        "id": 1,
        "name": "Jogador 1",
        "is_admin": true
    }
}
```

#### `POST /api/admin/players/{id}/remove-admin`
Remove permissões de administrador de um jogador.

**Headers:** `Authorization: Bearer {admin_token}`

**Resposta:**
```json
{
    "message": "Permissões de admin removidas com sucesso.",
    "player": {
        "id": 1,
        "name": "Jogador 1",
        "is_admin": false
    }
}
```

**⚠️ Observação:** Não é possível remover o último administrador do sistema.

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

## 🔧 EXEMPLO DE USO COMPLETO

### 1. Criar primeiro admin:
```bash
curl -X POST http://seu-dominio.com/api/setup-first-admin \
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

### 2. Fazer login:
```bash
curl -X POST http://seu-dominio.com/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@reddevils.com",
    "password": "Admin123!"
  }'
```

### 3. Usar token nas requisições:
```bash
curl -X GET http://seu-dominio.com/api/players \
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