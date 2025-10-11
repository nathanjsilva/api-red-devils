#!/bin/bash

echo "🚀 Iniciando deploy da API Red Devils..."

# Verificar se estamos no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Erro: Execute este script no diretório raiz do projeto"
    exit 1
fi

# Atualizar código do GitHub
echo "📥 Atualizando código do GitHub..."
git pull origin main

# Parar containers
echo "🛑 Parando containers..."
docker-compose -f docker-compose.prod.yml down

# Rebuild containers
echo "🔨 Rebuildando containers..."
docker-compose -f docker-compose.prod.yml build --no-cache

# Subir containers
echo "⬆️ Subindo containers..."
docker-compose -f docker-compose.prod.yml up -d

# Aguardar MySQL iniciar
echo "⏳ Aguardando MySQL iniciar..."
sleep 30

# Verificar se MySQL está rodando
echo "🔍 Verificando status do MySQL..."
until docker-compose -f docker-compose.prod.yml exec mysql mysqladmin ping -h localhost --silent; do
    echo "⏳ Aguardando MySQL..."
    sleep 5
done

echo "✅ MySQL está rodando!"

# Executar migrações
echo "🗄️ Executando migrações..."
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Limpar e cachear configurações
echo "🧹 Limpando cache..."
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache

# Otimizar autoloader
echo "⚡ Otimizando autoloader..."
docker-compose -f docker-compose.prod.yml exec app composer install --no-dev --optimize-autoloader

# Verificar status dos containers
echo "📊 Status dos containers:"
docker-compose -f docker-compose.prod.yml ps

echo "✅ Deploy concluído com sucesso!"
echo "🌐 API disponível em: http://$(curl -s ifconfig.me)"
echo "📋 Para testar: curl http://$(curl -s ifconfig.me)/api/players"

