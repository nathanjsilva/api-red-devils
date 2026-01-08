# Documentação do Fluxo do Sistema - API Red Devils

## Visão Geral

O sistema Red Devils gerencia jogadores, usuários e partidas de futebol. Ele possui uma arquitetura que separa **Usuários** (autenticação e permissões) de **Jogadores** (dados das partidas e estatísticas).

## Conceitos Fundamentais

### User (Usuário)
- Representa uma conta de acesso ao sistema
- Possui: `id`, `name`, `email`, `password`, `position` (linha/goleiro), `profile` (admin/common)
- Usado para autenticação via Laravel Sanctum
- Usuários comuns podem se cadastrar, mas não aparecem automaticamente como jogadores

### Player (Jogador)
- Representa um jogador nas partidas e estatísticas
- Possui: `id`, `name`, `email`, `password`, `position`, `phone`, `nickname`, `is_admin`, `user_id`
- O campo `user_id` vincula o Player a um User (pode ser null)
- **Apenas Players com `user_id` não-nulo aparecem nas listagens e estatísticas**

### Relacionamento User ↔ Player
- Um User pode ter no máximo um Player vinculado (relação 1:1)
- Um Player pode ter no máximo um User vinculado
- Players sem User vinculado não aparecem nas listagens públicas

## Fluxo de Funcionamento

### 1. Cadastro de Usuário Comum

**Endpoint:** `POST /api/users`

**Descrição:** Qualquer pessoa pode se cadastrar no sistema como usuário comum.

**Request Body:**
```json
{
  "name": "João Silva",
  "email": "joao@email.com",
  "password": "Senha123!",
  "position": "linha"
}
```

**Validações:**
- `name`: obrigatório, string, máximo 255 caracteres
- `email`: obrigatório, formato válido, único
- `password`: obrigatório, mínimo 8 caracteres, deve conter: 1 minúscula, 1 maiúscula, 1 número, 1 caractere especial
- `position`: obrigatório, deve ser "linha" ou "goleiro"

**Response (201):**
```json
{
  "id": 1,
  "name": "João Silva",
  "email": "joao@email.com",
  "position": "linha",
  "profile": "common",
  "player": null,
  "created_at": "2026-01-08 10:00:00",
  "updated_at": "2026-01-08 10:00:00"
}
```

**Importante:** Este usuário **NÃO aparece** automaticamente nas listagens de jogadores disponíveis para partidas. O Admin precisa criar um Player e vinculá-lo a este User.

---

### 2. Setup do Primeiro Admin

**Endpoint:** `POST /api/setup-first-admin`

**Descrição:** Cria o primeiro administrador do sistema (executado apenas uma vez).

**Request Body:**
```json
{
  "name": "Admin Principal",
  "email": "admin@reddevils.com",
  "password": "Admin123!",
  "position": "linha"
}
```

**Response (201):**
```json
{
  "message": "Primeiro administrador criado com sucesso!",
  "user": {
    "id": 1,
    "name": "Admin Principal",
    "email": "admin@reddevils.com",
    "position": "linha",
    "profile": "admin",
    "player": null
  }
}
```

---

### 3. Autenticação

**Endpoint:** `POST /api/login`

**Descrição:** Autentica um usuário no sistema (use User, não Player).

**Request Body:**
```json
{
  "email": "admin@reddevils.com",
  "password": "Admin123!"
}
```

**Response (200):**
```json
{
  "access_token": "1|token_hash_aqui",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Admin Principal",
    "email": "admin@reddevils.com",
    "position": "linha",
    "profile": "admin",
    "player": {
      "id": 1,
      "name": "Admin Principal",
      "nickname": "admin",
      ...
    }
  }
}
```

**Headers para rotas autenticadas:**
```
Authorization: Bearer {access_token}
```

---

### 4. Verificar Usuário Autenticado

**Endpoint:** `GET /api/me` (requer autenticação)

**Response (200):**
```json
{
  "id": 1,
  "name": "Admin Principal",
  "email": "admin@reddevils.com",
  "position": "linha",
  "profile": "admin",
  "player": {
    "id": 1,
    "name": "Admin Principal",
    "nickname": "admin",
    ...
  }
}
```

---

### 5. Cadastro de Jogador (Apenas Admin)

**Endpoint:** `POST /api/admin/players` (requer autenticação + perfil admin)

**Descrição:** Admin cria um novo jogador. Pode vincular a um User existente ou criar sem vínculo.

**Request Body (com user_id - vincular a usuário existente):**
```json
{
  "name": "João Silva",
  "email": "joao@email.com",
  "position": "linha",
  "phone": "11999999999",
  "nickname": "joao_silva",
  "user_id": 2
}
```

**Request Body (sem user_id - criar jogador sem vínculo):**
```json
{
  "name": "Pedro Santos",
  "email": "pedro@email.com",
  "password": "Senha123!",
  "position": "goleiro",
  "phone": "11888888888",
  "nickname": "pedro_gol",
  "is_admin": false
}
```

**Validações:**
- `name`: obrigatório, único na tabela players
- `email`: opcional, único se fornecido
- `password`: opcional (obrigatório se não fornecer `user_id`), deve seguir regras de senha forte
- `position`: obrigatório, "linha" ou "goleiro"
- `phone`: obrigatório, único
- `nickname`: obrigatório, único
- `is_admin`: opcional, boolean
- `user_id`: opcional, deve existir na tabela users e não estar vinculado a outro player

**Response (201):**
```json
{
  "id": 1,
  "name": "João Silva",
  "email": "joao@email.com",
  "position": "linha",
  "phone": "11999999999",
  "nickname": "joao_silva",
  "is_admin": false,
  "user_id": 2,
  "user": {
    "id": 2,
    "name": "João Silva",
    "email": "joao@email.com",
    "position": "linha",
    "profile": "common"
  },
  "created_at": "2026-01-08 10:00:00",
  "updated_at": "2026-01-08 10:00:00"
}
```

**Regras de Negócio:**
- Se `user_id` é fornecido, o `password` pode ser omitido (o Player não precisa de senha própria)
- Se `user_id` não é fornecido, o `password` é obrigatório
- Um User só pode estar vinculado a um Player
- Um Player só pode estar vinculado a um User

---

### 6. Editar Jogador (Apenas Admin)

**Endpoint:** `PUT /api/admin/players/{id}` (requer autenticação + perfil admin)

**Descrição:** Admin edita um jogador existente. Pode vincular/desvincular um User.

**Request Body (exemplos):**

Vincular User a Player:
```json
{
  "user_id": 3
}
```

Desvincular User (definir como null):
```json
{
  "user_id": null
}
```

Alterar dados do jogador:
```json
{
  "nickname": "novo_apelido",
  "phone": "11777777777"
}
```

**Response (200):** Retorna o Player atualizado com relacionamento `user` carregado se existir.

---

### 7. Listar Usuários Disponíveis (Apenas Admin)

**Endpoint:** `GET /api/admin/users` (requer autenticação + perfil admin)

**Descrição:** Lista todos os usuários do sistema com informação se já estão vinculados a um Player.

**Response (200):**
```json
[
  {
    "id": 1,
    "name": "Admin Principal",
    "email": "admin@reddevils.com",
    "position": "linha",
    "profile": "admin",
    "player": null
  },
  {
    "id": 2,
    "name": "João Silva",
    "email": "joao@email.com",
    "position": "linha",
    "profile": "common",
    "player": {
      "id": 1,
      "name": "João Silva",
      "nickname": "joao_silva",
      ...
    }
  }
]
```

**Uso:** Este endpoint é útil para o Admin selecionar um usuário ao criar/editar um Player.

---

### 8. Listar Jogadores Disponíveis

**Endpoint:** `GET /api/players` (requer autenticação)

**Descrição:** Lista **apenas** os jogadores que estão vinculados a um User (`user_id` não é null). Esses são os jogadores que aparecem nas peladas e estatísticas.

**Response (200):**
```json
[
  {
    "id": 1,
    "name": "João Silva",
    "email": "joao@email.com",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "joao_silva",
    "is_admin": false,
    "user_id": 2,
    "user": {
      "id": 2,
      "name": "João Silva",
      "email": "joao@email.com",
      "profile": "common"
    }
  }
]
```

**Importante:** 
- Jogadores sem `user_id` (não vinculados) **não aparecem** nesta listagem
- Usuários que se cadastraram mas ainda não foram vinculados a um Player **não aparecem** nesta listagem

---

## Fluxo Completo de Uso

### Cenário 1: Novo Usuário que Quer Jogar

1. **Usuário se cadastra:**
   ```
   POST /api/users
   {
     "name": "Maria Santos",
     "email": "maria@email.com",
     "password": "Senha123!",
     "position": "goleiro"
   }
   ```
   - Usuário é criado com `profile: "common"`
   - Usuário **não aparece** nas listagens de jogadores

2. **Admin cria Player e vincula:**
   ```
   POST /api/admin/players
   {
     "name": "Maria Santos",
     "position": "goleiro",
     "phone": "11777777777",
     "nickname": "maria_gol",
     "user_id": 3  // ID do usuário criado acima
   }
   ```
   - Player é criado e vinculado ao User
   - Agora o Player **aparece** nas listagens (`GET /api/players`)
   - Usuário pode fazer login e ver seu Player vinculado

### Cenário 2: Admin Cria Jogador sem Usuário

1. **Admin cria Player direto:**
   ```
   POST /api/admin/players
   {
     "name": "José Silva",
     "email": "jose@email.com",
     "password": "Senha123!",
     "position": "linha",
     "phone": "11666666666",
     "nickname": "jose_linha"
   }
   ```
   - Player é criado sem `user_id`
   - Player **não aparece** nas listagens públicas (`GET /api/players`)
   - Admin pode depois vincular a um User se necessário

2. **Admin vincula a um User existente:**
   ```
   PUT /api/admin/players/5
   {
     "user_id": 4
   }
   ```
   - Agora o Player **aparece** nas listagens

---

## Controle de Acesso

### Perfis de Usuário

- **`profile: "admin"`**: Administrador do sistema
  - Pode cadastrar/editar/deletar jogadores
  - Pode relacionar usuários a jogadores
  - Pode gerenciar peladas e estatísticas
  - Acessa rotas `/api/admin/*`

- **`profile: "common"`**: Usuário comum
  - Pode se cadastrar no sistema
  - Pode fazer login e ver seu próprio perfil
  - **NÃO pode** cadastrar/editar jogadores
  - **NÃO pode** acessar rotas administrativas

### Middleware de Autenticação

Todas as rotas protegidas (exceto `/api/login`, `/api/users`, `/api/setup-first-admin`, `/api/forgot-password`, `/api/reset-password`) requerem:

```
Authorization: Bearer {access_token}
```

### Middleware de Admin

Rotas administrativas (`/api/admin/*`) requerem:
1. Autenticação válida (token)
2. Usuário autenticado com `profile: "admin"`

---

## Estrutura de Dados

### User
```json
{
  "id": 1,
  "name": "Nome do Usuário",
  "email": "email@exemplo.com",
  "position": "linha|goleiro",
  "profile": "admin|common",
  "player": Player | null,
  "created_at": "2026-01-08 10:00:00",
  "updated_at": "2026-01-08 10:00:00"
}
```

### Player
```json
{
  "id": 1,
  "name": "Nome do Jogador",
  "email": "email@exemplo.com",
  "position": "linha|goleiro",
  "phone": "11999999999",
  "nickname": "apelido",
  "is_admin": false,
  "user_id": 1,
  "user": User | null,
  "created_at": "2026-01-08 10:00:00",
  "updated_at": "2026-01-08 10:00:00"
}
```

---

## Regras Importantes

1. **Apenas Players com `user_id` não-nulo aparecem nas listagens e estatísticas**
2. **Um User só pode estar vinculado a um Player**
3. **Um Player só pode estar vinculado a um User**
4. **Usuários comuns não podem cadastrar jogadores**
5. **Apenas Admin pode relacionar User a Player**
6. **Autenticação é feita via User, não via Player**

---

## Endpoints Principais

### Públicos
- `POST /api/users` - Cadastro de usuário comum
- `POST /api/login` - Login
- `POST /api/setup-first-admin` - Criar primeiro admin
- `POST /api/forgot-password` - Recuperar senha
- `POST /api/reset-password` - Redefinir senha

### Autenticados
- `GET /api/me` - Dados do usuário autenticado
- `POST /api/logout` - Logout
- `GET /api/players` - Lista jogadores vinculados
- `GET /api/players/{id}` - Detalhes do jogador
- `GET /api/peladas` - Lista peladas
- `GET /api/statistics/*` - Estatísticas

### Admin
- `GET /api/admin/users` - Lista usuários disponíveis
- `POST /api/admin/players` - Criar jogador
- `PUT /api/admin/players/{id}` - Editar jogador
- `DELETE /api/admin/players/{id}` - Deletar jogador
- `POST /api/admin/peladas` - Criar pelada
- `PUT /api/admin/peladas/{id}` - Editar pelada
- `DELETE /api/admin/peladas/{id}` - Deletar pelada

---

## Exemplos de Uso no Frontend

### 1. Tela de Cadastro
```javascript
// Cadastro de novo usuário
const response = await fetch('/api/users', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: 'João Silva',
    email: 'joao@email.com',
    password: 'Senha123!',
    position: 'linha'
  })
});

const user = await response.json();
// Mostrar mensagem: "Cadastro realizado! Aguarde o admin vincular seu perfil a um jogador."
```

### 2. Tela de Login
```javascript
const response = await fetch('/api/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'joao@email.com',
    password: 'Senha123!'
  })
});

const { access_token, user } = await response.json();
localStorage.setItem('token', access_token);

if (user.profile === 'admin') {
  // Redirecionar para dashboard admin
} else {
  // Redirecionar para dashboard comum
}

if (user.player) {
  // Usuário está vinculado a um jogador
} else {
  // Mostrar aviso: "Seu perfil ainda não foi vinculado a um jogador"
}
```

### 3. Tela Admin - Listar Usuários Disponíveis
```javascript
const token = localStorage.getItem('token');
const response = await fetch('/api/admin/users', {
  headers: { 'Authorization': `Bearer ${token}` }
});

const users = await response.json();

// Filtrar usuários não vinculados
const availableUsers = users.filter(u => !u.player);

// Mostrar lista para vincular ao criar/editar jogador
```

### 4. Tela Admin - Criar Jogador e Vincular Usuário
```javascript
const token = localStorage.getItem('token');

// Criar jogador vinculado a um usuário
const response = await fetch('/api/admin/players', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    name: 'João Silva',
    position: 'linha',
    phone: '11999999999',
    nickname: 'joao_silva',
    user_id: 2  // ID do usuário selecionado
  })
});

const player = await response.json();
// Agora o jogador aparece nas listagens públicas
```

### 5. Verificar se Usuário está Vinculado
```javascript
const token = localStorage.getItem('token');
const response = await fetch('/api/me', {
  headers: { 'Authorization': `Bearer ${token}` }
});

const user = await response.json();

if (user.player) {
  console.log('Usuário vinculado ao jogador:', user.player.nickname);
  // Mostrar estatísticas, histórico de partidas, etc.
} else {
  console.log('Usuário ainda não vinculado a um jogador');
  // Mostrar mensagem informativa
}
```

---

## Notas para Desenvolvimento Frontend

1. **Armazenar token:** Use `localStorage` ou `sessionStorage` para armazenar o `access_token` retornado no login
2. **Enviar token:** Sempre inclua o header `Authorization: Bearer {token}` nas requisições autenticadas
3. **Tratar erros 401:** Se receber 401 (Unauthorized), redirecione para login
4. **Tratar erros 403:** Se receber 403 (Forbidden), o usuário não tem permissão de admin
5. **Verificar perfil:** Use `user.profile === 'admin'` para mostrar/ocultar funcionalidades administrativas
6. **Verificar vinculação:** Use `user.player !== null` para verificar se o usuário está vinculado a um jogador

---

## FAQ

**P: Um usuário pode ter mais de um jogador?**
R: Não. A relação é 1:1 (um User para um Player).

**P: Um jogador pode existir sem usuário?**
R: Sim, mas ele não aparecerá nas listagens públicas. Apenas jogadores com `user_id` não-nulo aparecem.

**P: Como faço para um usuário comum aparecer nas partidas?**
R: O admin precisa criar um Player e vincular ao User através do campo `user_id`.

**P: Posso fazer login com um Player?**
R: Não. A autenticação é feita apenas via User. O Player pode ter senha própria, mas não é usado para login.

**P: Como sei se sou admin?**
R: Verifique `user.profile === 'admin'` após o login ou na resposta de `/api/me`.
