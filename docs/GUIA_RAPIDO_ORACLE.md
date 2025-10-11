# 🚀 Guia Rápido - Deploy Oracle Cloud

## ⚡ Resumo dos Passos

### 1️⃣ **Criar Conta Oracle Cloud**
- Acesse: https://cloud.oracle.com
- Clique "Try for free"
- Preencha dados (cartão necessário, mas não cobrado)

### 2️⃣ **Criar Instância (VM)**
1. **Console OCI** → **Compute** → **Instances** → **Create Instance**
2. **Configurar:**
   - Name: `api-red-devils`
   - Image: `Oracle Linux 8`
   - Shape: `VM.Standard.E2.1.Micro` (gratuito)
   - SSH Keys: Adicione sua chave pública
   - Networking: VCN with Internet Connectivity

### 3️⃣ **Configurar Firewall**
1. **Networking** → **Virtual Cloud Networks**
2. **Security Lists** → **Default Security List**
3. **Adicionar regras:**
   - Port 22 (SSH)
   - Port 80 (HTTP)  
   - Port 443 (HTTPS)
   - Source: `0.0.0.0/0`

### 4️⃣ **Conectar no Servidor**
```bash
ssh opc@SEU_IP_PUBLICO
```

### 5️⃣ **Instalar Dependências**
```bash
# Atualizar sistema
sudo dnf update -y

# Instalar Docker
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf install -y docker-ce docker-ce-cli containerd.io
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker opc

# Instalar Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Configurar firewall
sudo firewall-cmd --permanent --add-port=80/tcp
sudo firewall-cmd --permanent --add-port=443/tcp
sudo firewall-cmd --reload
```

### 6️⃣ **Clonar e Configurar Projeto**
```bash
cd /home/opc
git clone https://github.com/SEU_USUARIO/api-red-devils.git
cd api-red-devils

# Configurar .env
cp env.production.example .env
nano .env

# Gerar APP_KEY
php artisan key:generate
```

### 7️⃣ **Configurar .env para Produção**
```bash
# Editar .env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://SEU_IP_PUBLICO

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=api_red_devils
DB_USERNAME=laravel
DB_PASSWORD=SUA_SENHA_FORTE_123!
```

### 8️⃣ **Fazer Deploy**
```bash
# Tornar script executável
chmod +x deploy.sh

# Executar deploy
./deploy.sh
```

### 9️⃣ **Testar API**
```bash
# Testar endpoint público
curl http://SEU_IP_PUBLICO/api/players

# Criar jogador de teste
curl -X POST http://SEU_IP_PUBLICO/api/players \
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

## 🎯 URLs Finais

- **API Base:** `http://SEU_IP_PUBLICO/api`
- **Jogadores:** `http://SEU_IP_PUBLICO/api/players`
- **Login:** `http://SEU_IP_PUBLICO/api/login`
- **Rankings:** `http://SEU_IP_PUBLICO/api/statistics/rankings/goals`

## 🔧 Comandos Úteis

```bash
# Ver logs
docker-compose -f docker-compose.prod.yml logs

# Status dos containers
docker-compose -f docker-compose.prod.yml ps

# Restart da aplicação
docker-compose -f docker-compose.prod.yml restart

# Backup do banco
docker-compose -f docker-compose.prod.yml exec mysql mysqldump -u root -p$DB_PASSWORD $DB_DATABASE > backup.sql
```

## 🚨 Troubleshooting

**MySQL não conecta:**
```bash
docker-compose -f docker-compose.prod.yml logs mysql
```

**App não responde:**
```bash
docker-compose -f docker-compose.prod.yml logs app
```

**Erro de permissão:**
```bash
sudo chown -R opc:opc /home/opc/api-red-devils
```

## 💡 Dicas Importantes

1. **Senha forte** para o banco (mínimo 12 caracteres)
2. **APP_KEY** deve ser gerada no servidor
3. **Firewall** deve permitir portas 80 e 443
4. **IP público** será fornecido pela Oracle
5. **SSH** deve estar configurado corretamente

---

## 🎉 Resultado Final

Após seguir todos os passos, você terá:
- ✅ API rodando na Oracle Cloud
- ✅ Banco MySQL configurado
- ✅ Endpoints funcionando
- ✅ Deploy automatizado
- ✅ Monitoramento básico

**URL da sua API:** `http://SEU_IP_PUBLICO/api`

