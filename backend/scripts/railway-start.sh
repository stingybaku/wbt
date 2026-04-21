#!/usr/bin/env bash
set -euo pipefail

if [ ! -f .env ]; then
  cp .env.example .env
fi

upsert_env() {
  local key="$1"
  local value="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    echo "${key}=${value}" >> .env
  fi
}

# Normalize Railway Postgres variables to Laravel DB_* variables.
if [ -n "${DATABASE_URL:-}" ] && [ -z "${DB_URL:-}" ]; then
  export DB_URL="${DATABASE_URL}"
fi
if [ -n "${PGHOST:-}" ] && [ -z "${DB_HOST:-}" ]; then
  export DB_HOST="${PGHOST}"
fi
if [ -n "${PGPORT:-}" ] && [ -z "${DB_PORT:-}" ]; then
  export DB_PORT="${PGPORT}"
fi
if [ -n "${PGDATABASE:-}" ] && [ -z "${DB_DATABASE:-}" ]; then
  export DB_DATABASE="${PGDATABASE}"
fi
if [ -n "${PGUSER:-}" ] && [ -z "${DB_USERNAME:-}" ]; then
  export DB_USERNAME="${PGUSER}"
fi
if [ -n "${PGPASSWORD:-}" ] && [ -z "${DB_PASSWORD:-}" ]; then
  export DB_PASSWORD="${PGPASSWORD}"
fi
if [ -n "${DB_URL:-}" ] || [ -n "${DB_HOST:-}" ]; then
  export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
fi

# Railway can inject APP_KEY as an empty env var, which overrides .env.
# If that happens, promote a valid key from .env into the process env first.
if [ -z "${APP_KEY:-}" ]; then
  ENV_APP_KEY="$(sed -n 's/^APP_KEY=//p' .env | head -n1 | tr -d '\r' || true)"
  if [ -n "${ENV_APP_KEY}" ]; then
    export APP_KEY="${ENV_APP_KEY}"
  fi
fi

# Railway must provide APP_KEY, but if it's missing, bootstrap a key into .env.
# This avoids hard failure on first boot when .env was created from .env.example.
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/=]+' .env; then
  php artisan key:generate --force --no-interaction
  ENV_APP_KEY="$(sed -n 's/^APP_KEY=//p' .env | head -n1 | tr -d '\r' || true)"
  if [ -n "${ENV_APP_KEY}" ]; then
    export APP_KEY="${ENV_APP_KEY}"
  fi
fi

# Last guardrail: never boot if the runtime key is still empty.
if [ -z "${APP_KEY:-}" ]; then
  echo "FATAL: APP_KEY is empty at runtime. Set APP_KEY in Railway Variables." >&2
  exit 1
fi

# Persist runtime APP_KEY to .env so all request workers read a non-empty key.
upsert_env "APP_KEY" "${APP_KEY}"

# Persist runtime DB settings to .env to avoid SQLite fallback in request workers.
if [ -n "${DB_CONNECTION:-}" ]; then upsert_env "DB_CONNECTION" "${DB_CONNECTION}"; fi
if [ -n "${DB_URL:-}" ]; then upsert_env "DB_URL" "${DB_URL}"; fi
if [ -n "${DB_HOST:-}" ]; then upsert_env "DB_HOST" "${DB_HOST}"; fi
if [ -n "${DB_PORT:-}" ]; then upsert_env "DB_PORT" "${DB_PORT}"; fi
if [ -n "${DB_DATABASE:-}" ]; then upsert_env "DB_DATABASE" "${DB_DATABASE}"; fi
if [ -n "${DB_USERNAME:-}" ]; then upsert_env "DB_USERNAME" "${DB_USERNAME}"; fi
if [ -n "${DB_PASSWORD:-}" ]; then upsert_env "DB_PASSWORD" "${DB_PASSWORD}"; fi

if [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
  export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
fi

if [ -n "${APP_URL:-}" ]; then
  export ASSET_URL="${ASSET_URL:-${APP_URL}}"
fi

if [ -n "${APP_URL:-}" ]; then upsert_env "APP_URL" "${APP_URL}"; fi
if [ -n "${ASSET_URL:-}" ]; then upsert_env "ASSET_URL" "${ASSET_URL}"; fi

# Persist FRONTEND_URL so CORS middleware reads it correctly after config:clear.
if [ -n "${FRONTEND_URL:-}" ]; then upsert_env "FRONTEND_URL" "${FRONTEND_URL}"; fi

# Persist mail settings if provided.
if [ -n "${MAIL_MAILER:-}" ]; then upsert_env "MAIL_MAILER" "${MAIL_MAILER}"; fi
if [ -n "${MAIL_HOST:-}" ]; then upsert_env "MAIL_HOST" "${MAIL_HOST}"; fi
if [ -n "${MAIL_PORT:-}" ]; then upsert_env "MAIL_PORT" "${MAIL_PORT}"; fi
if [ -n "${MAIL_USERNAME:-}" ]; then upsert_env "MAIL_USERNAME" "${MAIL_USERNAME}"; fi
if [ -n "${MAIL_PASSWORD:-}" ]; then upsert_env "MAIL_PASSWORD" "${MAIL_PASSWORD}"; fi
if [ -n "${MAIL_FROM_ADDRESS:-}" ]; then upsert_env "MAIL_FROM_ADDRESS" "${MAIL_FROM_ADDRESS}"; fi
if [ -n "${MAIL_FROM_NAME:-}" ]; then upsert_env "MAIL_FROM_NAME" "${MAIL_FROM_NAME}"; fi

# Persist queue connection.
if [ -n "${QUEUE_CONNECTION:-}" ]; then upsert_env "QUEUE_CONNECTION" "${QUEUE_CONNECTION}"; fi


# Persist Stripe keys.
if [ -n "${STRIPE_SECRET:-}" ]; then upsert_env "STRIPE_SECRET" "${STRIPE_SECRET}"; fi
if [ -n "${STRIPE_PUBLISHABLE_KEY:-}" ]; then upsert_env "STRIPE_PUBLISHABLE_KEY" "${STRIPE_PUBLISHABLE_KEY}"; fi
if [ -n "${STRIPE_WEBHOOK_SECRET:-}" ]; then upsert_env "STRIPE_WEBHOOK_SECRET" "${STRIPE_WEBHOOK_SECRET}"; fi


# Persist AI / media keys.
if [ -n "${ANTHROPIC_API_KEY:-}" ]; then upsert_env "ANTHROPIC_API_KEY" "${ANTHROPIC_API_KEY}"; fi
if [ -n "${ANTHROPIC_MODEL:-}" ]; then upsert_env "ANTHROPIC_MODEL" "${ANTHROPIC_MODEL}"; fi
if [ -n "${ANTHROPIC_MODEL_FAST:-}" ]; then upsert_env "ANTHROPIC_MODEL_FAST" "${ANTHROPIC_MODEL_FAST}"; fi
if [ -n "${UNSPLASH_ACCESS_KEY:-}" ]; then upsert_env "UNSPLASH_ACCESS_KEY" "${UNSPLASH_ACCESS_KEY}"; fi
if [ -n "${VOYAGE_API_KEY:-}" ]; then upsert_env "VOYAGE_API_KEY" "${VOYAGE_API_KEY}"; fi
if [ -n "${PEXELS_API_KEY:-}" ]; then upsert_env "PEXELS_API_KEY" "${PEXELS_API_KEY}"; fi
if [ -n "${RESEND_API_KEY:-}" ]; then upsert_env "RESEND_API_KEY" "${RESEND_API_KEY}"; fi

echo "Startup: APP_KEY is set (length ${#APP_KEY})."
echo "Startup: APP_URL=${APP_URL:-unset}"
echo "Startup: ASSET_URL=${ASSET_URL:-unset}"
echo "Startup: FRONTEND_URL=${FRONTEND_URL:-unset}"
echo "Startup: DB_CONNECTION=${DB_CONNECTION:-unset} DB_HOST=${DB_HOST:-unset} DB_DATABASE=${DB_DATABASE:-unset}"

if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

php artisan config:clear
php artisan cache:clear || true
php artisan package:discover --ansi

php artisan queue:work --tries=1 --timeout=720 --sleep=3 --max-time=3600 &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
