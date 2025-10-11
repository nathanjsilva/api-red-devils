@echo off
echo 🚀 Iniciando deploy da API Red Devils...

REM Verificar se estamos no diretório correto
if not exist "composer.json" (
    echo ❌ Erro: Execute este script no diretório raiz do projeto
    pause
    exit /b 1
)

REM Atualizar código do GitHub
echo 📥 Atualizando código do GitHub...
git pull origin main

REM Parar containers
echo 🛑 Parando containers...
docker-compose -f docker-compose.prod.yml down

REM Rebuild containers
echo 🔨 Rebuildando containers...
docker-compose -f docker-compose.prod.yml build --no-cache

REM Subir containers
echo ⬆️ Subindo containers...
docker-compose -f docker-compose.prod.yml up -d

REM Aguardar MySQL iniciar
echo ⏳ Aguardando MySQL iniciar...
timeout /t 30 /nobreak

REM Executar migrações
echo 🗄️ Executando migrações...
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

REM Limpar e cachear configurações
echo 🧹 Limpando cache...
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache

REM Otimizar autoloader
echo ⚡ Otimizando autoloader...
docker-compose -f docker-compose.prod.yml exec app composer install --no-dev --optimize-autoloader

REM Verificar status dos containers
echo 📊 Status dos containers:
docker-compose -f docker-compose.prod.yml ps

echo ✅ Deploy concluído com sucesso!
echo 🌐 API disponível em: http://localhost
echo 📋 Para testar: curl http://localhost/api/players
pause

