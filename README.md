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
