# WBT Forge

**Turn your existing documents into interactive training — no LMS required.**

WBT Forge is an AI-assisted web app that converts source material organizations already
have — PDFs, Word docs, PowerPoint decks, or a public URL — into a polished, interactive
Web-Based Training (WBT) module in minutes. Authors review and edit the generated course,
then share it with learners via a simple link. No learning-management-system purchase,
no instructional-design team, no SCORM packaging required to get started.

> *"The fastest way to turn PDFs and slides into real training."*

---

## Who it's for

- **Small and mid-sized organizations** with training content but no LMS or instructional
  designers (onboarding, safety, compliance, process docs).
- **Training consultants and freelance instructional designers** serving SMB clients who
  need to turn client material into deliverable courses fast.
- **NGOs and non-profits** running volunteer or staff onboarding on a tight budget.
- **HR / Ops teams** inside larger companies who want a fast prototype before committing
  to a full LMS rollout.

## What it does today

### 1. Content in, course out
- Upload a PDF, DOCX, or PPTX (up to 10MB), or paste a public URL.
- Author sets duration (10–30 min), learning objectives, and assessment question count —
  or lets the AI decide.
- **Claude (Anthropic)** generates a structured course as validated JSON: learning
  objectives, 10+ screens, and an assessment — with automatic retries if the output
  doesn't pass schema validation.
- Relevant photography is auto-attached to screens via **Unsplash** and **Pexels**, with
  proper attribution shown to the author and, when required, the learner.

### 2. Review before you publish
- A dedicated review editor lets the author edit every screen (text, images, alt text,
  quiz choices, accordion/click-reveal content) and flag anything that needs a second look
  before it goes out.

### 3. A real player, not a PDF
- Interactive screen types: narrative text, image + text, accordion, click-to-reveal card
  grids, and single/multi-choice quiz questions with feedback.
- Fully responsive, dark-mode design system, distinct from the polished, isolated player
  UI learners actually see.

### 4. Distribute without an LMS
- Owners invite learners by email; learners open a magic link, confirm their email (or
  name, for certificates), and start the course — no account, no LMS login.
- Per-learner progress is tracked (screen position, resume-on-return, completion time) and
  visible to the course owner in a live dashboard.
- On completion, a **PDF certificate** with a unique verification code is generated,
  emailed to the learner, and downloadable by the owner. Anyone can validate a certificate
  code on a public verification page — no account needed.

### 5. Monetization built in
- Three-tier plan model (one-time purchase, and two subscription tiers) with per-plan
  module and learner-seat quotas, enforced server-side.
- **Stripe Checkout** for payment, self-serve registration with email verification,
  password reset, and an admin panel (Filament) for managing users, plans, projects,
  modules, and platform content without touching code.

## Why it's differentiated

- **Speed to first draft**: minutes from source document to a reviewable course, versus
  hours-to-days with traditional authoring tools (Articulate, Captivate) or manually
  building slides.
- **No LMS tax**: distribution is a link and an email gate — organizations without LMS
  budget or infrastructure can still deploy training today.
- **Human-in-the-loop by design**: AI drafts, a human reviews and edits before anything
  reaches a learner — positioned against "black box" AI generators.
- **Real completion accountability**: per-learner progress tracking and verifiable
  certificates give owners something to show for training that a static document can't.

## Architecture & tech stack

WBT Forge ships in two deployable configurations:

- **All-in-one on Railway** — a React SPA and a Laravel 12 API, each as a Railway
  service, backed by Railway-managed PostgreSQL. Simplest path to a live demo or small
  production deployment. See [`RAILWAY.md`](./RAILWAY.md).
- **AWS + Vercel (hackathon-compliant stack)** — the React front end deployed to
  **Vercel**, the Laravel API on Railway, and **AWS Aurora PostgreSQL Serverless v2**
  (with `pgvector`) as the primary database. Vercel also runs two Edge Functions (social
  unfurl for shared invite links, a health probe) and a Node function that queries Aurora
  directly via Vercel OIDC + RDS IAM auth for public certificate verification — no stored
  DB password. See [`ARCHITECTURE.md`](./ARCHITECTURE.md) and [`DEPLOY.md`](./DEPLOY.md)
  for the full diagram and setup steps.

**Core stack:** React + Vite + TypeScript (frontend) · Laravel 12 + PHP 8.2 (API) ·
PostgreSQL (Aurora or Railway-managed) · Anthropic Claude API (generation) · Unsplash &
Pexels APIs (imagery) · Stripe (billing) · Filament (admin panel) · Sanctum (auth).

## What's not built yet

- **SCORM / xAPI export** — for organizations that do have an LMS and want to import a
  module into it. Stub exists; not packaged.
- **Aggregate analytics dashboard** — per-learner progress exists today; a cross-project
  completion/analytics view does not.
- **AI-assisted content refinement** ("improve this screen") and **native PDF-to-Claude**
  (image-aware generation from the file directly, rather than extracted text).
- **Multi-tenant organizations, localization, branching scenarios, TTS narration,
  theme marketplace** — all on the post-MVP roadmap.

## Local development

```bash
# Backend (Laravel API) — from /backend
composer dev

# Frontend (React) — from /frontend
npm run dev
```

Backend requires `ANTHROPIC_API_KEY` at minimum; see [`backend/README.md`](./backend/README.md),
[`frontend/README.md`](./frontend/README.md), and the
[PRD](./product_requirements_document_prd_ai_assisted_web_based_training_generator.md) for
full environment variable references, data model, and detailed feature specs.

## Deploying

- Railway (single-provider): [`RAILWAY.md`](./RAILWAY.md)
- AWS Aurora + Vercel (hackathon stack): [`DEPLOY.md`](./DEPLOY.md), diagram in
  [`ARCHITECTURE.md`](./ARCHITECTURE.md)
