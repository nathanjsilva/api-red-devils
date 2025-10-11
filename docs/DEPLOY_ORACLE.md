# 🚀 Deploy da API Red Devils na Oracle Cloud

Guia completo para fazer o deploy da API na Oracle Cloud Infrastructure (OCI) usando o tier gratuito.

## 📋 Pré-requisitos

- ✅ Conta Oracle Cloud (tier gratuito)
- ✅ GitHub com código versionado
- ✅ Acesso SSH
- ✅ Domínio (opcional, mas recomendado)

## 🏗️ Passo 1: Preparar o Projeto

### 1.1 Criar arquivo .env para produção
```bash
# .env.production
APP_NAME="API Red Devils"
APP_ENV=production
APP_KEY=base64:SUA_CHAVE_AQUI
APP_DEBUG=false
APP_URL=https://seu-dominio.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=api_red_devils
DB_USERNAME=laravel
DB_PASSWORD=SUA_SENHA_FORTE_AQUI

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### 1.2 Atualizar docker-compose para produção
```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: api_red_devils_app
    working_dir: /var/www
    volumes:
      - ./:/var/www
      - ./storage:/var/www/storage
    networks:
      - api_red_devils_network
    depends_on:
      - mysql
    restart: unless-stopped
    environment:
      - APP_ENV=production

  nginx:
    image: nginx:alpine
    container_name: api_red_devils_nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf
      - ./ssl:/etc/nginx/ssl
    networks:
      - api_red_devils_network
    depends_on:
      - app
    restart: unless-stopped

  mysql:
    image: mysql:8.0
    container_name: api_red_devils_mysql
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - dbdata:/var/lib/mysql
    networks:
      - api_red_devils_network
    restart: unless-stopped
    command: --default-authentication-plugin=mysql_native_password

volumes:
  dbdata:

networks:
  api_red_devils_network:
    driver: bridge
```

### 1.3 Criar script de deploy
```bash
#!/bin/bash
# deploy.sh

echo "🚀 Iniciando deploy da API Red Devils..."

# Atualizar código do GitHub
git pull origin main

# Parar containers
docker-compose -f docker-compose.prod.yml down

# Rebuild containers
docker-compose -f docker-compose.prod.yml build --no-cache

# Subir containers
docker-compose -f docker-compose.prod.yml up -d

# Aguardar MySQL iniciar
echo "⏳ Aguardando MySQL iniciar..."
sleep 30

# Executar migrações
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Limpar cache
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache

# Otimizar autoloader
docker-compose -f docker-compose.prod.yml exec app composer install --no-dev --optimize-autoloader

echo "✅ Deploy concluído com sucesso!"
echo "🌐 API disponível em: https://seu-dominio.com"
```

### 1.4 Atualizar nginx para produção
```nginx
# nginx/production.conf
server {
    listen 80;
    server_name seu-dominio.com www.seu-dominio.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name seu-dominio.com www.seu-dominio.com;
    root /var/www/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

## ☁️ Passo 2: Configurar Oracle Cloud Infrastructure

### 2.1 Criar conta Oracle Cloud
1. Acesse [Oracle Cloud](https://cloud.oracle.com)
2. Clique em "Try for free"
3. Preencha os dados (cartão de crédito necessário, mas não cobrado no tier gratuito)
4. Confirme o email

### 2.2 Criar instância de Compute
1. **Acesse o Console OCI**
2. **Menu → Compute → Instances**
3. **Clique em "Create Instance"**
4. **Configure:**
   - Name: `api-red-devils-server`
   - Image: `Oracle Linux 8`
   - Shape: `VM.Standard.E2.1.Micro` (tier gratuito)
   - SSH Keys: Adicione sua chave pública SSH
   - Networking: VCN with Internet Connectivity
   - Subnet: Public Subnet

### 2.3 Configurar Security Lists
1. **Menu → Networking → Virtual Cloud Networks**
2. **Selecione sua VCN**
3. **Security Lists → Default Security List**
4. **Adicione regras:**
   - **Ingress Rule 1:**
     - Source: `0.0.0.0/0`
     - IP Protocol: `TCP`
     - Destination Port Range: `22` (SSH)
   - **Ingress Rule 2:**
     - Source: `0.0.0.0/0`
     - IP Protocol: `TCP`
     - Destination Port Range: `80` (HTTP)
   - **Ingress Rule 3:**
     - Source: `0.0.0.0/0`
     - IP Protocol: `TCP`
     - Destination Port Range: `443` (HTTPS)

## 🖥️ Passo 3: Configurar Servidor

### 3.1 Conectar via SSH
```bash
ssh opc@SEU_IP_PUBLICO
```

### 3.2 Atualizar sistema
```bash
sudo dnf update -y
sudo dnf install -y git curl wget
```

### 3.3 Instalar Docker
```bash
# Instalar Docker
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf install -y docker-ce docker-ce-cli containerd.io

# Iniciar Docker
sudo systemctl start docker
sudo systemctl enable docker

# Adicionar usuário ao grupo docker
sudo usermod -aG docker opc

# Instalar Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

### 3.4 Configurar firewall
```bash
sudo firewall-cmd --permanent --add-port=80/tcp
sudo firewall-cmd --permanent --add-port=443/tcp
sudo firewall-cmd --reload
```

### 3.5 Clonar repositório
```bash
cd /home/opc
git clone https://github.com/SEU_USUARIO/api-red-devils.git
cd api-red-devils
```

## 🗄️ Passo 4: Configurar Banco de Dados

### 4.1 Criar arquivo .env
```bash
cp .env.example .env.production
nano .env.production
```

### 4.2 Configurar variáveis
```bash
# Gerar APP_KEY
php artisan key:generate --env=production

# Configurar banco
DB_DATABASE=api_red_devils
DB_USERNAME=laravel
DB_PASSWORD=SENHA_SUPER_FORTE_123!
```

### 4.3 Subir aplicação
```bash
# Tornar script executável
chmod +x deploy.sh

# Executar deploy
./deploy.sh
```

## 🌐 Passo 5: Configurar Domínio (Opcional)

### 5.1 Registrar domínio
- Use serviços como Namecheap, GoDaddy, etc.
- Aponte DNS para o IP da sua instância Oracle

### 5.2 Configurar SSL com Let's Encrypt
```bash
# Instalar Certbot
sudo dnf install -y certbot python3-certbot-nginx

# Gerar certificado
sudo certbot certonly --standalone -d seu-dominio.com -d www.seu-dominio.com

# Copiar certificados para nginx
sudo cp /etc/letsencrypt/live/seu-dominio.com/fullchain.pem /home/opc/api-red-devils/ssl/cert.pem
sudo cp /etc/letsencrypt/live/seu-dominio.com/privkey.pem /home/opc/api-red-devils/ssl/key.pem
sudo chown opc:opc /home/opc/api-red-devils/ssl/*

# Configurar renovação automática
sudo crontab -e
# Adicionar: 0 12 * * * /usr/bin/certbot renew --quiet
```

## 🧪 Passo 6: Testar API

### 6.1 Testar endpoints básicos
```bash
# Testar health check
curl http://SEU_IP_PUBLICO/api/players

# Testar com domínio
curl https://seu-dominio.com/api/players
```

### 6.2 Criar jogador de teste
```bash
curl -X POST https://seu-dominio.com/api/players \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Teste Oracle",
    "email": "teste@oracle.com",
    "password": "MinhaSenh@123",
    "position": "linha",
    "phone": "11999999999",
    "nickname": "Oracle Test"
  }'
```

### 6.3 Testar login
```bash
curl -X POST https://seu-dominio.com/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste@oracle.com",
    "password": "MinhaSenh@123"
  }'
```

## 🔧 Passo 7: Configurações de Produção

### 7.1 Configurar backup automático
```bash
# Criar script de backup
cat > backup.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker-compose -f docker-compose.prod.yml exec mysql mysqldump -u root -p$DB_PASSWORD $DB_DATABASE > backup_$DATE.sql
gzip backup_$DATE.sql
# Upload para Oracle Object Storage ou outro serviço
EOF

chmod +x backup.sh

# Agendar backup diário
crontab -e
# Adicionar: 0 2 * * * /home/opc/api-red-devils/backup.sh
```

### 7.2 Configurar monitoramento
```bash
# Instalar htop para monitoramento
sudo dnf install -y htop

# Criar script de monitoramento
cat > monitor.sh << 'EOF'
#!/bin/bash
echo "=== Status dos Containers ==="
docker-compose -f docker-compose.prod.yml ps

echo "=== Uso de Disco ==="
df -h

echo "=== Uso de Memória ==="
free -h

echo "=== Uso de CPU ==="
top -bn1 | grep "Cpu(s)"
EOF

chmod +x monitor.sh
```

## 📊 Passo 8: Verificar Funcionamento

### 8.1 Checklist de verificação
- [ ] ✅ Instância Oracle criada e acessível
- [ ] ✅ Docker instalado e funcionando
- [ ] ✅ Código clonado do GitHub
- [ ] ✅ Banco de dados configurado
- [ ] ✅ API respondendo via HTTP/HTTPS
- [ ] ✅ SSL configurado (se usando domínio)
- [ ] ✅ Backup configurado
- [ ] ✅ Monitoramento básico ativo

### 8.2 URLs para testar
```bash
# Endpoints públicos
GET  https://seu-dominio.com/api/players
POST https://seu-dominio.com/api/players
POST https://seu-dominio.com/api/login

# Endpoints autenticados (usar token do login)
GET  https://seu-dominio.com/api/peladas
GET  https://seu-dominio.com/api/statistics/rankings/goals
```

## 🚨 Troubleshooting

### Problemas comuns:

1. **Erro de conexão com banco:**
   ```bash
   # Verificar se MySQL está rodando
   docker-compose -f docker-compose.prod.yml ps
   
   # Ver logs
   docker-compose -f docker-compose.prod.yml logs mysql
   ```

2. **Erro 502 Bad Gateway:**
   ```bash
   # Verificar se app está rodando
   docker-compose -f docker-compose.prod.yml logs app
   ```

3. **Erro de permissão:**
   ```bash
   # Corrigir permissões
   sudo chown -R opc:opc /home/opc/api-red-devils
   chmod -R 755 /home/opc/api-red-devils
   ```

4. **SSL não funciona:**
   ```bash
   # Verificar certificados
   ls -la ssl/
   
   # Testar SSL
   openssl s_client -connect seu-dominio.com:443
   ```

## 💰 Custos do Tier Gratuito

- ✅ **Compute**: 2 instâncias VM.Standard.E2.1.Micro (1GB RAM, 1 OCPU)
- ✅ **Storage**: 200GB de block storage
- ✅ **Networking**: 10TB de transferência de dados por mês
- ✅ **Database**: 20GB de Autonomous Database (opcional)
- ✅ **Validade**: 30 dias (renovável)

## 🎯 Próximos Passos

1. **Configurar CI/CD** com GitHub Actions
2. **Implementar logs centralizados**
3. **Configurar alertas de monitoramento**
4. **Otimizar performance** com Redis
5. **Implementar rate limiting** mais robusto

---

## 📞 Suporte

Em caso de problemas:
1. Verifique os logs: `docker-compose -f docker-compose.prod.yml logs`
2. Consulte a documentação da Oracle Cloud
3. Verifique o status dos serviços: `./monitor.sh`

**🎉 Parabéns! Sua API está rodando na Oracle Cloud!**

