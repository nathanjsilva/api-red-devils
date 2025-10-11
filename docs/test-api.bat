@echo off
echo 🧪 Testando API Red Devils...

REM Verificar se a URL foi fornecida
if "%~1"=="" (
    echo ❌ Uso: test-api.bat http://SEU_IP_PUBLICO
    echo    Exemplo: test-api.bat http://123.456.789.123
    pause
    exit /b 1
)

set API_URL=%~1
echo 🌐 Testando API em: %API_URL%

REM Teste 1: Verificar se a API está respondendo
echo 📋 Teste 1: Verificando se a API está online...
curl -s -f "%API_URL%/api/players" >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ API está online!
) else (
    echo ❌ API não está respondendo
    pause
    exit /b 1
)

REM Teste 2: Criar jogador de teste
echo 📋 Teste 2: Criando jogador de teste...
for /f %%i in ('powershell -command "Get-Date -UFormat %%s"') do set TIMESTAMP=%%i
set PLAYER_NAME=Teste Oracle %TIMESTAMP%
set PLAYER_EMAIL=teste%TIMESTAMP%@oracle.com
set PLAYER_PHONE=1199999%TIMESTAMP%
set PLAYER_NICKNAME=Teste%TIMESTAMP%

curl -s -X POST "%API_URL%/api/players" ^
  -H "Content-Type: application/json" ^
  -d "{\"name\": \"%PLAYER_NAME%\", \"email\": \"%PLAYER_EMAIL%\", \"password\": \"MinhaSenh@123\", \"position\": \"linha\", \"phone\": \"%PLAYER_PHONE%\", \"nickname\": \"%PLAYER_NICKNAME%\"}" > temp_response.json

findstr /c:"id" temp_response.json >nul
if %errorlevel% equ 0 (
    echo ✅ Jogador criado com sucesso!
    for /f "tokens=2 delims=:" %%a in ('findstr /c:"\"id\":" temp_response.json') do set PLAYER_ID=%%a
    set PLAYER_ID=%PLAYER_ID:,=%
    set PLAYER_ID=%PLAYER_ID: =%
    echo    ID do jogador: %PLAYER_ID%
) else (
    echo ❌ Erro ao criar jogador
    type temp_response.json
    pause
    exit /b 1
)

REM Teste 3: Fazer login
echo 📋 Teste 3: Testando login...
curl -s -X POST "%API_URL%/api/login" ^
  -H "Content-Type: application/json" ^
  -d "{\"email\": \"%PLAYER_EMAIL%\", \"password\": \"MinhaSenh@123\"}" > temp_login.json

findstr /c:"access_token" temp_login.json >nul
if %errorlevel% equ 0 (
    echo ✅ Login realizado com sucesso!
    for /f "tokens=2 delims=:" %%a in ('findstr /c:"\"access_token\":" temp_login.json') do set TOKEN=%%a
    set TOKEN=%TOKEN: =%
    set TOKEN=%TOKEN:"=%
    set TOKEN=%TOKEN:,=%
    echo    Token obtido: %TOKEN:~0,20%...
) else (
    echo ❌ Erro no login
    type temp_login.json
    pause
    exit /b 1
)

REM Teste 4: Verificar ranking
echo 📋 Teste 4: Verificando ranking de gols...
curl -s -X GET "%API_URL%/api/statistics/rankings/goals" ^
  -H "Authorization: Bearer %TOKEN%" > temp_ranking.json

findstr /c:"total_goals" temp_ranking.json >nul
if %errorlevel% equ 0 (
    echo ✅ Ranking funcionando!
) else (
    echo ❌ Erro no ranking
    type temp_ranking.json
    pause
    exit /b 1
)

REM Limpar arquivos temporários
del temp_response.json temp_login.json temp_ranking.json

echo.
echo 🎉 TODOS OS TESTES PASSARAM!
echo ✅ API está funcionando perfeitamente!
echo 🌐 URL da API: %API_URL%
echo 📊 Endpoints testados:
echo    - POST /api/players (criar jogador)
echo    - POST /api/login (autenticação)
echo    - GET /api/statistics/rankings/goals (ranking)
echo.
echo 🚀 Sua API está pronta para uso!
pause

