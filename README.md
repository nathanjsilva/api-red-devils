# API Red Devils

API para gerenciamento de peladas e estatísticas de jogadores.

## Requisitos

- PHP 8.2+
- MySQL 8.0+
- Docker & Docker Compose

## Instalação

1. Clone o repositório
2. Execute `docker-compose up -d`
3. Execute `docker-compose exec app composer install`
4. Execute `docker-compose exec app php artisan migrate`
5. Configure o arquivo `.env` com suas credenciais

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
