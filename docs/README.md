# 📚 Documentação da API Red Devils

Esta pasta contém toda a documentação e arquivos auxiliares para desenvolvimento e deploy da API.

## 📁 Arquivos Disponíveis

### 🚀 Deploy e Produção
- **`DEPLOY_ORACLE.md`** - Guia completo para deploy na Oracle Cloud
- **`GUIA_RAPIDO_ORACLE.md`** - Resumo dos passos principais para deploy
- **`deploy.bat`** - Script de deploy para Windows (desenvolvimento)
- **`test-api.bat`** - Script de teste para Windows (desenvolvimento)

### 📖 Documentação da API
- **`API_EXAMPLES.md`** - Exemplos práticos de uso da API com curl
- **`README.md`** - Este arquivo (índice da documentação)

## 🎯 Como Usar

### Para Desenvolvimento Local
1. Use `deploy.bat` para fazer deploy local
2. Use `test-api.bat` para testar a API localmente

### Para Deploy em Produção
1. Siga o **`GUIA_RAPIDO_ORACLE.md`** para deploy rápido
2. Consulte **`DEPLOY_ORACLE.md`** para instruções detalhadas
3. Use os scripts `.sh` no servidor Linux

### Para Consultar a API
1. Veja **`API_EXAMPLES.md`** para exemplos de uso
2. Todos os endpoints estão documentados com curl

## 📋 Checklist de Deploy

### ✅ Antes do Deploy
- [ ] Código versionado no GitHub
- [ ] Conta Oracle Cloud criada
- [ ] Chaves SSH configuradas
- [ ] Domínio registrado (opcional)

### ✅ Durante o Deploy
- [ ] Instância Oracle criada
- [ ] Docker instalado
- [ ] Projeto clonado
- [ ] .env configurado
- [ ] Deploy executado
- [ ] API testada

### ✅ Após o Deploy
- [ ] SSL configurado (se usando domínio)
- [ ] Backup configurado
- [ ] Monitoramento ativo
- [ ] Documentação atualizada

## 🔗 Links Úteis

- **Oracle Cloud Console:** https://cloud.oracle.com
- **Laravel Documentation:** https://laravel.com/docs
- **Docker Documentation:** https://docs.docker.com
- **MySQL Documentation:** https://dev.mysql.com/doc

## 🆘 Suporte

Em caso de problemas:
1. Consulte a documentação específica
2. Verifique os logs do Docker
3. Teste os endpoints com os scripts fornecidos
4. Verifique a configuração do .env

---

**📝 Nota:** Esta documentação é específica para a API Red Devils e deve ser mantida atualizada conforme mudanças no projeto.
