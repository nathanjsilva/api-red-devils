#!/bin/bash

# Script para testar a API após deploy
echo "🧪 Testando API Red Devils..."

# Verificar se a URL foi fornecida
if [ -z "$1" ]; then
    echo "❌ Uso: ./test-api.sh http://SEU_IP_PUBLICO"
    echo "   Exemplo: ./test-api.sh http://123.456.789.123"
    exit 1
fi

API_URL=$1
echo "🌐 Testando API em: $API_URL"

# Teste 1: Verificar se a API está respondendo
echo "📋 Teste 1: Verificando se a API está online..."
if curl -s -f "$API_URL/api/players" > /dev/null; then
    echo "✅ API está online!"
else
    echo "❌ API não está respondendo"
    exit 1
fi

# Teste 2: Criar jogador de teste
echo "📋 Teste 2: Criando jogador de teste..."
TIMESTAMP=$(date +%s)
RESPONSE=$(curl -s -X POST "$API_URL/api/players" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Teste Oracle $TIMESTAMP\",
    \"email\": \"teste$TIMESTAMP@oracle.com\",
    \"password\": \"MinhaSenh@123\",
    \"position\": \"linha\",
    \"phone\": \"1199999$TIMESTAMP\",
    \"nickname\": \"Teste$TIMESTAMP\"
  }")

if echo "$RESPONSE" | grep -q "id"; then
    echo "✅ Jogador criado com sucesso!"
    PLAYER_ID=$(echo "$RESPONSE" | grep -o '"id":[0-9]*' | cut -d':' -f2)
    echo "   ID do jogador: $PLAYER_ID"
else
    echo "❌ Erro ao criar jogador"
    echo "   Resposta: $RESPONSE"
    exit 1
fi

# Teste 3: Fazer login
echo "📋 Teste 3: Testando login..."
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/api/login" \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"teste$TIMESTAMP@oracle.com\",
    \"password\": \"MinhaSenh@123\"
  }")

if echo "$LOGIN_RESPONSE" | grep -q "access_token"; then
    echo "✅ Login realizado com sucesso!"
    TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
    echo "   Token obtido: ${TOKEN:0:20}..."
else
    echo "❌ Erro no login"
    echo "   Resposta: $LOGIN_RESPONSE"
    exit 1
fi

# Teste 4: Criar pelada
echo "📋 Teste 4: Criando pelada de teste..."
PELADA_RESPONSE=$(curl -s -X POST "$API_URL/api/peladas" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"date\": \"$(date -d '+7 days' +%Y-%m-%d)\",
    \"location\": \"Campo Teste\",
    \"qtd_times\": 2,
    \"qtd_jogadores_por_time\": 6,
    \"qtd_goleiros\": 2
  }")

if echo "$PELADA_RESPONSE" | grep -q "id"; then
    echo "✅ Pelada criada com sucesso!"
    PELADA_ID=$(echo "$PELADA_RESPONSE" | grep -o '"id":[0-9]*' | cut -d':' -f2)
    echo "   ID da pelada: $PELADA_ID"
else
    echo "❌ Erro ao criar pelada"
    echo "   Resposta: $PELADA_RESPONSE"
    exit 1
fi

# Teste 5: Registrar estatísticas
echo "📋 Teste 5: Registrando estatísticas..."
STATS_RESPONSE=$(curl -s -X POST "$API_URL/api/match-players" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"player_id\": $PLAYER_ID,
    \"pelada_id\": $PELADA_ID,
    \"goals\": 2,
    \"assists\": 1,
    \"is_winner\": true
  }")

if echo "$STATS_RESPONSE" | grep -q "id"; then
    echo "✅ Estatísticas registradas com sucesso!"
else
    echo "❌ Erro ao registrar estatísticas"
    echo "   Resposta: $STATS_RESPONSE"
    exit 1
fi

# Teste 6: Verificar ranking
echo "📋 Teste 6: Verificando ranking de gols..."
RANKING_RESPONSE=$(curl -s -X GET "$API_URL/api/statistics/rankings/goals" \
  -H "Authorization: Bearer $TOKEN")

if echo "$RANKING_RESPONSE" | grep -q "total_goals"; then
    echo "✅ Ranking funcionando!"
else
    echo "❌ Erro no ranking"
    echo "   Resposta: $RANKING_RESPONSE"
    exit 1
fi

echo ""
echo "🎉 TODOS OS TESTES PASSARAM!"
echo "✅ API está funcionando perfeitamente!"
echo "🌐 URL da API: $API_URL"
echo "📊 Endpoints testados:"
echo "   - POST /api/players (criar jogador)"
echo "   - POST /api/login (autenticação)"
echo "   - POST /api/peladas (criar pelada)"
echo "   - POST /api/match-players (estatísticas)"
echo "   - GET /api/statistics/rankings/goals (ranking)"
echo ""
echo "🚀 Sua API está pronta para uso!"

