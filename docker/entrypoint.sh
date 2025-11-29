#!/bin/sh
set -e

cd /var/www/html

# Ensure required dirs exist
mkdir -p storage temp protect/uploads
chown -R www-data:www-data storage temp protect/uploads || true

# If no .env exists (e.g. Dokploy deployment), generate it from environment variables
if [ ! -f .env ]; then
  : "${WEB_MODE:=production}"
  : "${WEB_URL:=https://thunkthao.online}"
  : "${SOCKET_PORT:=8001}"
  : "${WEB_NAME:=Thungthao}"
  : "${DB_DRIVER:=none}"
  : "${JWT_SECRET:=changeme}"

  cat > .env <<ENVEOF
WEB_MODE="${WEB_MODE}"
WEB_URL="${WEB_URL}"
SOCKET_PORT=${SOCKET_PORT}
WEB_NAME="${WEB_NAME}"

DB_DRIVER="${DB_DRIVER}"

MYSQL_HOST="${MYSQL_HOST}"
MYSQL_USER="${MYSQL_USER}"
MYSQL_PASSWORD="${MYSQL_PASSWORD}"
MYSQL_DATABASE="${MYSQL_DATABASE}"
MYSQL_PORT="${MYSQL_PORT}"
MYSQL_CHARSET="${MYSQL_CHARSET}"

SQLITE_PATH="${SQLITE_PATH}"

JWT_SECRET="${JWT_SECRET}"
ENVEOF

  chown www-data:www-data .env || true
fi

# Optionally ensure SQLite DB exists if configured via environment
if [ "$DB_DRIVER" = "sqlite" ] && [ -n "$SQLITE_PATH" ]; then
  mkdir -p "$(dirname "$SQLITE_PATH")"
  if [ ! -f "$SQLITE_PATH" ]; then
    echo "[entrypoint] Creating SQLite DB at $SQLITE_PATH"
    : > "$SQLITE_PATH" || true
    chown www-data:www-data "$SQLITE_PATH" || true
    chmod 664 "$SQLITE_PATH" || true
  fi
fi

exec "$@"
