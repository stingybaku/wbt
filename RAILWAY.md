# Deploying WBT Forge to Railway

WBT Forge runs as two Railway services from the same GitHub repository:

| Service | Source | What it does |
|---|---|---|
| **backend** | `backend/Dockerfile` | Laravel API + queue worker |
| **frontend** | `frontend/Dockerfile` | React app served by nginx |

A **PostgreSQL** database is added as a Railway plugin attached to the backend service.

---

## 1. First deploy — order matters

Deploy in this order to avoid a chicken-and-egg problem with URLs:

```
PostgreSQL plugin → backend service → frontend service
```

---

## 2. Create the project on Railway

1. Go to [railway.app](https://railway.app) → **New Project** → **Deploy from GitHub repo**
2. Select the `wbt` repository

---

## 3. Backend service

Railway will detect `railway.json` and use `backend/Dockerfile` automatically.

### 3a. Add PostgreSQL

In the Railway project dashboard → **Add Plugin** → **PostgreSQL**. Railway injects `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD` automatically — the startup script maps these to Laravel's `DB_*` variables.

### 3b. Set environment variables

In the backend service → **Variables**, add:

| Variable | Value |
|---|---|
| `APP_NAME` | `WBT Forge` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Run `php artisan key:generate --show` locally and paste the result |
| `APP_URL` | Set after the backend is deployed (Railway assigns a domain) |
| `FRONTEND_URL` | Set after the frontend is deployed (controls CORS) |
| `RUN_MIGRATIONS` | `true` |
| `SESSION_DRIVER` | `file` |
| `CACHE_STORE` | `file` |
| `LOG_LEVEL` | `warning` |
| `ANTHROPIC_API_KEY` | Your Anthropic API key |
| `ANTHROPIC_MODEL` | `claude-sonnet-4-6` |
| `UNSPLASH_ACCESS_KEY` | Your Unsplash access key |
| `MAIL_MAILER` | `smtp` (or `mailgun` / `postmark`) |
| `MAIL_HOST` | Your SMTP host |
| `MAIL_PORT` | `587` |
| `MAIL_USERNAME` | Your SMTP username |
| `MAIL_PASSWORD` | Your SMTP password |
| `MAIL_FROM_ADDRESS` | `noreply@yourdomain.com` |
| `MAIL_FROM_NAME` | `WBT Forge` |

> **Tip for `APP_KEY`:** run this locally to generate one:
> ```bash
> cd backend && php artisan key:generate --show
> ```

### 3c. Set `APP_URL` after first deploy

Once Railway assigns a public domain to the backend (e.g. `wbt-backend.up.railway.app`):
- Set `APP_URL=https://wbt-backend.up.railway.app`

---

## 4. Frontend service

### 4a. Add a second service

In the Railway project → **New Service** → **GitHub Repo** → select the same `wbt` repo.

### 4b. Configure the service

In the frontend service settings:

- **Dockerfile path**: `frontend/Dockerfile`
- **Build context**: `/` (repo root — the Dockerfile copies from `frontend/`)

### 4c. Set build variable

The React app needs to know the backend URL **at build time** (Vite bakes it into the JS bundle):

In the frontend service → **Variables**:

| Variable | Value |
|---|---|
| `VITE_API_BASE_URL` | `https://wbt-backend.up.railway.app/api` |

> This must be set **before** the first build. If you change the backend URL later, trigger a redeploy of the frontend.

### 4d. Update `FRONTEND_URL` on the backend

Once Railway assigns the frontend its domain (e.g. `wbt-frontend.up.railway.app`):
- Go back to the **backend** service → **Variables**
- Set `FRONTEND_URL=https://wbt-frontend.up.railway.app`
- This allows the CORS middleware to accept requests from the frontend and builds correct invite links in emails

Redeploy the backend after setting this.

---

## 5. Database migrations

Migrations run automatically on every boot when `RUN_MIGRATIONS=true`. This is safe because all migrations are idempotent.

To run manually:
```bash
railway run --service backend php artisan migrate --force
```

---

## 6. Queue worker

The queue worker runs as a background process inside the backend container (started in `railway-start.sh`). No separate worker service is needed. It handles:
- `GenerationJob` — AI module generation
- Any future queued mail jobs

---

## 7. Health check

Railway uses `GET /up` (Laravel's built-in health endpoint) to verify the backend is alive. This is already configured in `railway.json`.

---

## 8. Local dev quick reference

```bash
# Backend
cd backend && composer dev

# Frontend
cd frontend && npm run dev
```

Required local env vars (`backend/.env`):
```
ANTHROPIC_API_KEY=
UNSPLASH_ACCESS_KEY=
FRONTEND_URL=http://localhost:5173
```

---

## 9. Environment variable summary

### Backend (Railway Variables)
| Variable | Required | Notes |
|---|---|---|
| `APP_KEY` | ✅ | Generate with `php artisan key:generate --show` |
| `APP_ENV` | ✅ | `production` |
| `APP_URL` | ✅ | Backend Railway domain |
| `FRONTEND_URL` | ✅ | Frontend Railway domain — controls CORS + email invite links |
| `RUN_MIGRATIONS` | ✅ | `true` |
| `ANTHROPIC_API_KEY` | ✅ | |
| `UNSPLASH_ACCESS_KEY` | Recommended | Images skipped if absent |
| `ANTHROPIC_MODEL` | Optional | Default: `claude-sonnet-4-6` |
| `MAIL_*` | ✅ (for invites) | SMTP credentials for viewer invite emails |
| `APP_DEBUG` | ✅ | `false` in production |

### Frontend (Railway Build Variables)
| Variable | Required | Notes |
|---|---|---|
| `VITE_API_BASE_URL` | ✅ | Backend URL + `/api`, e.g. `https://wbt-backend.up.railway.app/api` |
