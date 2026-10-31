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

if [ -z "${APP_KEY:-}" ]; then
  ENV_APP_KEY="$(sed -n 's/^APP_KEY=//p' .env | head -n1 | tr -d '\r' || true)"
  if [ -n "${ENV_APP_KEY}" ]; then
    export APP_KEY="${ENV_APP_KEY}"
  fi
fi

if [ -z "${APP_KEY:-}" ]; then
  echo "FATAL: APP_KEY is empty. Set APP_KEY in Railway Variables." >&2
  exit 1
fi

upsert_env "APP_KEY" "${APP_KEY}"

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
if [ -n "${APP_URL:-}" ]; then upsert_env "APP_URL" "${APP_URL}"; fi
if [ -n "${FRONTEND_URL:-}" ]; then upsert_env "FRONTEND_URL" "${FRONTEND_URL}"; fi
if [ -n "${QUEUE_CONNECTION:-}" ]; then upsert_env "QUEUE_CONNECTION" "${QUEUE_CONNECTION}"; fi

if [ -n "${STRIPE_SECRET:-}" ]; then upsert_env "STRIPE_SECRET" "${STRIPE_SECRET}"; fi
if [ -n "${STRIPE_PUBLISHABLE_KEY:-}" ]; then upsert_env "STRIPE_PUBLISHABLE_KEY" "${STRIPE_PUBLISHABLE_KEY}"; fi
if [ -n "${STRIPE_WEBHOOK_SECRET:-}" ]; then upsert_env "STRIPE_WEBHOOK_SECRET" "${STRIPE_WEBHOOK_SECRET}"; fi

if [ -n "${CLOUDFLARE_R2_ACCESS_KEY_ID:-}" ]; then upsert_env "CLOUDFLARE_R2_ACCESS_KEY_ID" "${CLOUDFLARE_R2_ACCESS_KEY_ID}"; fi
if [ -n "${CLOUDFLARE_R2_SECRET_ACCESS_KEY:-}" ]; then upsert_env "CLOUDFLARE_R2_SECRET_ACCESS_KEY" "${CLOUDFLARE_R2_SECRET_ACCESS_KEY}"; fi
if [ -n "${CLOUDFLARE_R2_BUCKET:-}" ]; then upsert_env "CLOUDFLARE_R2_BUCKET" "${CLOUDFLARE_R2_BUCKET}"; fi
if [ -n "${CLOUDFLARE_R2_ENDPOINT:-}" ]; then upsert_env "CLOUDFLARE_R2_ENDPOINT" "${CLOUDFLARE_R2_ENDPOINT}"; fi
if [ -n "${CLOUDFLARE_R2_PUBLIC_URL:-}" ]; then upsert_env "CLOUDFLARE_R2_PUBLIC_URL" "${CLOUDFLARE_R2_PUBLIC_URL}"; fi

if [ -n "${ANTHROPIC_API_KEY:-}" ]; then upsert_env "ANTHROPIC_API_KEY" "${ANTHROPIC_API_KEY}"; fi
if [ -n "${ANTHROPIC_MODEL:-}" ]; then upsert_env "ANTHROPIC_MODEL" "${ANTHROPIC_MODEL}"; fi
if [ -n "${ANTHROPIC_MODEL_FAST:-}" ]; then upsert_env "ANTHROPIC_MODEL_FAST" "${ANTHROPIC_MODEL_FAST}"; fi
if [ -n "${UNSPLASH_ACCESS_KEY:-}" ]; then upsert_env "UNSPLASH_ACCESS_KEY" "${UNSPLASH_ACCESS_KEY}"; fi
if [ -n "${VOYAGE_API_KEY:-}" ]; then upsert_env "VOYAGE_API_KEY" "${VOYAGE_API_KEY}"; fi
if [ -n "${PEXELS_API_KEY:-}" ]; then upsert_env "PEXELS_API_KEY" "${PEXELS_API_KEY}"; fi
if [ -n "${RESEND_API_KEY:-}" ]; then upsert_env "RESEND_API_KEY" "${RESEND_API_KEY}"; fi
if [ -n "${MAIL_MAILER:-}" ]; then upsert_env "MAIL_MAILER" "${MAIL_MAILER}"; fi
if [ -n "${MAIL_HOST:-}" ]; then upsert_env "MAIL_HOST" "${MAIL_HOST}"; fi
if [ -n "${MAIL_PORT:-}" ]; then upsert_env "MAIL_PORT" "${MAIL_PORT}"; fi
if [ -n "${MAIL_USERNAME:-}" ]; then upsert_env "MAIL_USERNAME" "${MAIL_USERNAME}"; fi
if [ -n "${MAIL_PASSWORD:-}" ]; then upsert_env "MAIL_PASSWORD" "${MAIL_PASSWORD}"; fi
if [ -n "${MAIL_FROM_ADDRESS:-}" ]; then upsert_env "MAIL_FROM_ADDRESS" "${MAIL_FROM_ADDRESS}"; fi
if [ -n "${MAIL_FROM_NAME:-}" ]; then upsert_env "MAIL_FROM_NAME" "${MAIL_FROM_NAME}"; fi

php artisan config:clear
php artisan cache:clear || true

echo "Worker: starting queue:work"
echo "Worker: DB_CONNECTION=${DB_CONNECTION:-unset} DB_HOST=${DB_HOST:-unset}"

# Minimal HTTP server so Railway healthchecks pass (worker has no real HTTP traffic)
mkdir -p /tmp/health
echo '<?php http_response_code(200); echo "OK";' > /tmp/health/index.php
php -S "0.0.0.0:${PORT:-8080}" -t /tmp/health &>/dev/null &

# Laravel scheduler (routes/console.php), e.g. generation:reap-stuck. One
# fresh `schedule:run` per minute, the cron equivalent, so a crashed run
# can't take the scheduler down with it. Only this container runs it, so
# scheduled tasks never fire twice.
(
  while true; do
    php artisan schedule:run --no-interaction || echo "Worker: schedule:run exited with $?" >&2
    sleep 60
  done
) &

# queue:work exits on its own by design: with status 0 after --max-jobs (and
# after `php artisan queue:restart`), and with 12 after --memory. A Railway
# ON_FAILURE restart policy does not restart a clean exit, so with a bare
# `exec` the worker stopped for good after 50 jobs and generations queued
# with nothing to run them until the next deploy. Restart it here instead.
#
# SIGTERM (Railway stopping the container) is forwarded to queue:work, which
# then finishes its current job before exiting - the same graceful shutdown
# `exec` gave us - and the loop ends rather than restarting it.
stopping=0
child=0
trap 'stopping=1; if [ "$child" -ne 0 ]; then kill -TERM "$child" 2>/dev/null || true; fi' TERM INT

while [ "$stopping" -eq 0 ]; do
  php artisan queue:work \
    --tries=1 \
    --timeout=720 \
    --sleep=3 \
    --max-jobs=50 \
    --memory=256 &
  child=$!

  status=0
  wait "$child" || status=$?

  if [ "$stopping" -eq 1 ]; then
    # wait returns as soon as the trap fires; wait again for the job to finish.
    wait "$child" 2>/dev/null || true
    break
  fi

  echo "Worker: queue:work exited with status ${status}; restarting in 2s." >&2
  sleep 2
done

echo "Worker: stopped."
