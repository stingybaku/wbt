# Frontend (Vite + React + TypeScript)

## Run locally

1. Install dependencies:

```bash
cd frontend
npm install
```

2. Configure API URL:

```bash
cp .env.example .env
```

3. Start dev server:

```bash
npm run dev
```

The app runs on `http://127.0.0.1:5173` and calls Laravel API using `VITE_API_BASE_URL`.

## Current scope

- Login against `POST /api/auth/login`
- List projects from `GET /api/projects`
- Create project with `POST /api/projects`
