# 📋 Instruções para Git - API Red Devils

## 🚀 Como subir as alterações para o GitHub

### 1. Verificar status atual
```bash
git status
```

### 2. Adicionar arquivos
```bash
# Adicionar todos os arquivos novos/modificados
git add .

# OU adicionar arquivos específicos
git add .gitignore
git add README.md
git add docs/
git add docker-compose.prod.yml
git add nginx/production.conf
git add deploy.sh
git add test-api.sh
git add env.production.example
```

### 3. Fazer commit
```bash
git commit -m "feat: Adiciona configuração para deploy na Oracle Cloud

- Cria pasta docs/ com documentação completa
- Adiciona scripts de deploy para produção
- Configura Docker Compose para produção
- Adiciona configuração Nginx para SSL
- Cria scripts de teste da API
- Atualiza .gitignore para produção
- Organiza documentação em pasta separada"
```

### 4. Subir para o GitHub
```bash
git push origin main
```

## 📁 Estrutura do Projeto

### ✅ **Arquivos para Produção (raiz)**
- `docker-compose.prod.yml` - Docker Compose para produção
- `nginx/production.conf` - Configuração Nginx com SSL
- `deploy.sh` - Script de deploy para Linux
- `test-api.sh` - Script de teste para Linux
- `env.production.example` - Exemplo de .env para produção
- `.gitignore` - Ignora arquivos desnecessários

### 📚 **Documentação (docs/)**
- `docs/DEPLOY_ORACLE.md` - Guia completo de deploy
- `docs/GUIA_RAPIDO_ORACLE.md` - Guia rápido
- `docs/API_EXAMPLES.md` - Exemplos de uso da API
- `docs/deploy.bat` - Script de deploy para Windows
- `docs/test-api.bat` - Script de teste para Windows
- `docs/README.md` - Índice da documentação

## 🔒 Arquivos Ignorados pelo Git

O `.gitignore` foi configurado para ignorar:
- `.env` e `.env.production` (contém senhas)
- `ssl/` (certificados SSL)
- `*.sql` (backups de banco)
- `temp_*.json` (arquivos temporários)
- `vendor/` (dependências do Composer)
- `node_modules/` (dependências do NPM)

## 🎯 Próximos Passos

Após subir para o GitHub:

1. **No servidor Oracle:**
   ```bash
   git clone https://github.com/SEU_USUARIO/api-red-devils.git
   cd api-red-devils
   cp env.production.example .env
   # Editar .env com suas configurações
   chmod +x deploy.sh
   ./deploy.sh
   ```

2. **Testar a API:**
   ```bash
   chmod +x test-api.sh
   ./test-api.sh http://SEU_IP_PUBLICO
   ```

## 📝 Comandos Git Úteis

```bash
# Ver histórico de commits
git log --oneline

# Ver diferenças
git diff

# Ver arquivos não rastreados
git status --ignored

# Desfazer último commit (se necessário)
git reset --soft HEAD~1

# Ver branch atual
git branch

# Criar nova branch
git checkout -b feature/nova-funcionalidade
```

---

**✅ Agora o projeto está organizado e pronto para produção!**
