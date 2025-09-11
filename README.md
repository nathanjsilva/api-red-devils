# 🏈 API Red Devils

API para gerenciamento de peladas de futebol desenvolvida com Laravel 11.

## 📋 Sobre o Projeto

A API Red Devils é uma aplicação RESTful que permite gerenciar jogadores, peladas e suas estatísticas. Desenvolvida com as melhores práticas do Laravel, incluindo autenticação via Sanctum, validação robusta e testes automatizados.

## 🚀 Funcionalidades

- **Gestão de Jogadores**: CRUD completo com posições (linha/goleiro)
- **Gestão de Peladas**: Criação e gerenciamento de eventos
- **Estatísticas**: Registro de gols, assistências, vitórias e gols sofridos
- **Autenticação**: Sistema seguro com Laravel Sanctum
- **Validação**: Form Requests com regras de negócio
- **Testes**: Cobertura completa com PHPUnit
- **Documentação**: API documentada com Swagger/OpenAPI

## 🛠️ Tecnologias

- **Laravel 11** - Framework PHP
- **PHP 8.2** - Linguagem de programação
- **MySQL 8.0** - Banco de dados
- **Laravel Sanctum** - Autenticação API
- **Docker** - Containerização
- **PHPUnit** - Testes automatizados
- **Swagger** - Documentação da API

## 📦 Instalação

### Pré-requisitos

- Docker e Docker Compose
- PHP 8.2+ (se executando localmente)
- Composer (se executando localmente)

### Com Docker (Recomendado)

1. Clone o repositório:
```bash
git clone <repository-url>
cd api-red-devils
```

2. Execute com Docker:
```bash
docker-compose up -d
```

3. Instale as dependências:
```bash
docker-compose exec app composer install
```

4. Configure o ambiente:
```bash
docker-compose exec app cp .env.example .env
docker-compose exec app php artisan key:generate
```

5. Execute as migrações:
```bash
docker-compose exec app php artisan migrate
```

### Local (sem Docker)

1. Clone e configure o projeto:
```bash
git clone <repository-url>
cd api-red-devils
composer install
cp .env.example .env
php artisan key:generate
```

2. Configure o banco de dados no `.env`

3. Execute as migrações:
```bash
php artisan migrate
```

## 🧪 Testes

Execute os testes com:

```bash
# Com Docker
docker-compose exec app php artisan test

# Local
php artisan test
```

## 📚 Documentação da API

A documentação completa da API está disponível via Swagger:

- **URL**: `http://localhost:8080/api/documentation`
- **Formato**: OpenAPI 3.0

### Endpoints Principais

#### Autenticação
- `POST /api/login` - Login de jogador

#### Jogadores
- `GET /api/players` - Listar jogadores (autenticado)
- `POST /api/players` - Criar jogador (público)
- `GET /api/players/{id}` - Buscar jogador (autenticado)
- `PUT /api/players/{id}` - Atualizar jogador (autenticado)
- `DELETE /api/players/{id}` - Deletar jogador (autenticado)

#### Peladas
- `GET /api/peladas` - Listar peladas (autenticado)
- `POST /api/peladas` - Criar pelada (autenticado)
- `GET /api/peladas/{id}` - Buscar pelada (autenticado)
- `PUT /api/peladas/{id}` - Atualizar pelada (autenticado)
- `DELETE /api/peladas/{id}` - Deletar pelada (autenticado)

#### Estatísticas
- `POST /api/match-players` - Registrar estatísticas (autenticado)
- `PUT /api/match-players/{id}` - Atualizar estatísticas (autenticado)
- `DELETE /api/match-players/{id}` - Remover estatísticas (autenticado)

## 🔐 Autenticação

A API utiliza Laravel Sanctum para autenticação via token Bearer:

1. Faça login em `/api/login` com email e senha
2. Use o token retornado no header `Authorization: Bearer {token}`
3. Todas as rotas protegidas requerem autenticação

## 📊 Estrutura do Banco

### Tabelas Principais

- **players**: Jogadores do sistema
- **peladas**: Eventos/jogos
- **match_players**: Estatísticas de jogadores por pelada

### Relacionamentos

- Um jogador pode participar de várias peladas
- Uma pelada pode ter vários jogadores
- Cada participação tem estatísticas específicas

## 🏗️ Arquitetura

O projeto segue as melhores práticas do Laravel:

- **Controllers**: Lógica de negócio e controle de fluxo
- **Models**: Relacionamentos e regras de domínio
- **Form Requests**: Validação de dados de entrada
- **Resources**: Padronização de respostas da API
- **Factories**: Geração de dados para testes
- **Migrations**: Versionamento do banco de dados

## 🚀 Deploy

### Produção

1. Configure as variáveis de ambiente
2. Execute as migrações
3. Configure o servidor web (Nginx/Apache)
4. Configure SSL/HTTPS

### Docker Production

```bash
docker-compose -f docker-compose.prod.yml up -d
```

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📝 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

## 👥 Equipe

- **Desenvolvedor**: [Nathan de Jesus Silva]
- **Email**: [nathan.ads.100@gmail.com]

## 📞 Suporte

Para suporte, envie um email para [nathan.ads.100@gmail.com] ou abra uma issue no GitHub.

---

**Desenvolvido com ❤️ usando Laravel**
