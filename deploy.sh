#!/usr/bin/env bash

set -Eeuo pipefail

APP_BRANCH="${APP_BRANCH:-main}"
SKIP_GIT_SYNC=0

for arg in "$@"; do
  case "$arg" in
    --skip-git)
      SKIP_GIT_SYNC=1
      ;;
    *)
      echo "Argumento invalido: $arg"
      exit 1
      ;;
  esac
done

if [ ! -f "composer.json" ]; then
  echo "Erro: execute este script na raiz do projeto."
  exit 1
fi

if docker compose version >/dev/null 2>&1; then
  COMPOSE_CMD="docker compose"
elif docker-compose version >/dev/null 2>&1; then
  COMPOSE_CMD="docker-compose"
else
  echo "Erro: Docker Compose nao encontrado."
  exit 1
fi

run_compose() {
  $COMPOSE_CMD -f docker-compose.prod.yml "$@"
}

mysql_state() {
  docker inspect -f '{{.State.Status}}' api_red_devils_mysql 2>/dev/null || true
}

mysql_health() {
  docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' api_red_devils_mysql 2>/dev/null || true
}

echo "Iniciando deploy da API Red Devils..."

if [ "$SKIP_GIT_SYNC" -eq 0 ]; then
  echo "Sincronizando codigo com origin/$APP_BRANCH..."
  git fetch origin "$APP_BRANCH"
  git checkout "$APP_BRANCH"
  git reset --hard "origin/$APP_BRANCH"
else
  echo "Sincronizacao Git ignorada; usando codigo ja presente no servidor."
fi

if [ ! -f ".env" ]; then
  echo "Erro: arquivo .env nao encontrado."
  exit 1
fi

set -a
. ./.env
set +a

echo "Parando stack atual..."
run_compose down --remove-orphans

echo "Subindo banco de dados..."
run_compose up -d --build mysql

echo "Aguardando MySQL responder..."
MYSQL_WAIT_ATTEMPTS=24
attempt=1
until run_compose exec -T mysql mysqladmin ping -h 127.0.0.1 -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent >/dev/null 2>&1; do
  state="$(mysql_state)"
  health="$(mysql_health)"

  if [ "$state" = "restarting" ] || [ "$state" = "exited" ] || [ "$state" = "dead" ]; then
    echo "Erro: container MySQL entrou em estado '$state' (health: $health)."
    echo "Ultimos logs do MySQL:"
    run_compose logs mysql --tail 100 || true
    exit 1
  fi

  if [ "$attempt" -ge "$MYSQL_WAIT_ATTEMPTS" ]; then
    echo "Erro: MySQL nao respondeu apos $((MYSQL_WAIT_ATTEMPTS * 5)) segundos (state: ${state:-desconhecido}, health: ${health:-desconhecido})."
    echo "Status atual da stack:"
    run_compose ps || true
    echo "Ultimos logs do MySQL:"
    run_compose logs mysql --tail 100 || true
    exit 1
  fi

  echo "MySQL ainda nao respondeu (tentativa $attempt/$MYSQL_WAIT_ATTEMPTS, state: ${state:-desconhecido}, health: ${health:-desconhecido})."
  attempt=$((attempt + 1))
  sleep 5
done

echo "Instalando dependencias PHP..."
run_compose run --rm app sh -lc "git config --global --add safe.directory /var/www && composer install --no-dev --optimize-autoloader"

echo "Subindo aplicacao e Nginx..."
run_compose up -d --build app nginx

echo "Ajustando permissoes e cache..."
run_compose exec -T app sh -lc "chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwx storage bootstrap/cache"
run_compose exec -T app php artisan migrate --force
run_compose exec -T app php artisan optimize:clear
run_compose exec -T app php artisan config:cache
run_compose exec -T app php artisan route:cache

echo "Status final dos containers:"
run_compose ps

echo "Deploy concluido com sucesso."
