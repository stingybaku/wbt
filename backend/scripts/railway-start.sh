#!/usr/bin/env bash
set -euo pipefail

if [ ! -f .env ]; then
  cp .env.example .env
fi

# Railway can inject APP_KEY as an empty env var, which overrides .env.
# If that happens, promote a valid key from .env into the process env first.
if [ -z "${APP_KEY:-}" ]; then
  ENV_APP_KEY="$(grep -E '^APP_KEY=base64:[A-Za-z0-9+/=]+' .env | head -n1 | cut -d'=' -f2- || true)"
  if [ -n "${ENV_APP_KEY}" ]; then
    export APP_KEY="${ENV_APP_KEY}"
  fi
fi

# Railway must provide APP_KEY, but if it's missing, bootstrap a key into .env.
# This avoids hard failure on first boot when .env was created from .env.example.
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/=]+' .env; then
  php artisan key:generate --force --no-interaction
  ENV_APP_KEY="$(grep -E '^APP_KEY=base64:[A-Za-z0-9+/=]+' .env | head -n1 | cut -d'=' -f2- || true)"
  if [ -n "${ENV_APP_KEY}" ]; then
    export APP_KEY="${ENV_APP_KEY}"
  fi
fi

if [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ] && [ -z "${APP_URL:-}" ]; then
  export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
fi

if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

php artisan config:clear
php artisan package:discover --ansi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
