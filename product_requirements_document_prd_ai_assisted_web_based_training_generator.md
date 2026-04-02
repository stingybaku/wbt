# Product Requirements Document (PRD)

## Product Name (Working)
**WBT Forge** (working title)

## Document Status
Post-MVP — Active Development

## Author
Julio (Product / Engineering)

## Last Updated
2026-04-01

---

## 1. Purpose & Vision

Small and mid-sized organizations often have training content (PDFs, Word docs, PowerPoints, internal URLs) but lack the tools, expertise, or infrastructure (LMS, instructional designers) to convert that content into professional, interactive training modules.

**WBT Forge** enables users to transform existing authorized content into a polished **Web-Based Training (WBT)** module in minutes—without requiring an LMS—while still offering optional SCORM/xAPI export for compatibility.

The product prioritizes:
- Speed
- Simplicity
- Quality-controlled AI output
- LMS-free deployment

---

## 2. Target Users

### Primary (MVP Focus)
- Small organizations without an LMS
- Training consultants serving SMB clients
- NGOs and non-profits
- SMB HR / Operations teams (onboarding, compliance, safety)

### Secondary (Later)
- Freelance instructional designers
- EdTech startups
- Departments inside larger organizations for rapid prototyping

---

## 3. Problem Statement

- Existing authoring tools (Articulate, Captivate) are expensive, complex, and time-consuming.
- LMS deployment adds infrastructure, cost, and admin overhead.
- AI tools exist but lack structure, instructional rigor, and deployable outputs.

Users need a tool that:
- Accepts raw content they already have
- Produces structured, interactive training
- Can be deployed immediately via the web
- Does not require deep technical or instructional design knowledge

---

## 4. Goals & Success Metrics

### MVP Goals
- Convert content → draft WBT in under 5 minutes
- Clear, validated, editable output (JSON-driven)
- AI-generated content feels accurate, engaging, and visually appealing
- Invite viewers to access the course without an LMS

### Success Metrics
- Time to first generated draft module
- % of generated modules reviewed (flagged/annotated)
- # of screens edited post-generation (lower is better)
- # of viewer invites sent per project

---

## 5. Implemented Features

### 5.1 Content Input ✅
- File upload: PDF, DOCX, PPTX
- URL input (public web pages)
- File size limit: 10MB
- Mandatory user attestation: *"I confirm I have the rights to use this content."*
- Text extracted server-side (smalot/pdfparser, phpoffice/phpword, phpoffice/phppresentation)
- Content capped at 50,000 characters before being passed to the AI

> **Note:** Password-protected PDFs are not supported. URL sources are subject to the target server allowing server-side HTTP requests.

---

### 5.2 Generation Controls ✅

Users must provide or choose:
- **Duration** — 10–30 minutes (default: 20)
- **Learning Objectives** — user-defined or AI-generated
- **Assessment Questions** — user-defined count or AI-decided
- **Output Size** — minimum 10 screens

Generation is handled asynchronously via `GenerationJob`. The job calls the Claude API (`GenerationService`), retries up to 3 times on schema validation failure, and updates module status to `generated` or `failed`. The frontend polls every 3 seconds.

---

### 5.3 Output Structure ✅

The system generates a validated JSON module containing:
- Metadata (title, audience, duration, source summary)
- Learning objectives
- Screens: `text`, `image_text`, `accordion`, `click_reveal`, `question`
- Interactions: single choice, multiple choice, accordion, click-reveal (with optional images)
- Optional assessment block
- Accessibility metadata
- Unsplash attribution

The JSON is the **single source of truth** for rendering, editing, and exporting.

---

### 5.4 Media & Images ✅

- **Auto-enrichment at generation time**: After the AI produces the module JSON, `GenerationJob` calls the Unsplash API for each screen (using the screen title as query) and populates real images — replacing placeholder URLs
- **Click-reveal item images**: Same pipeline applied to click-reveal card items
- **Cover image**: First real image found in the generated screens is stored as `cover_image_url` on the `modules` table for use in the project list and detail view
- **Enrichment logging**: Summary log entry per job (succeeded / no-results / errors counts)
- **Image replacement**: Users can replace any image via the Unsplash picker in the review editor
- **Attribution**: Stored in `module.attribution.unsplash`; displayed in player
- **Audio**: TTS deferred to v2

---

### 5.5 WBT Player ✅

- React-based player (`WBTPlayer`)
- Cover screen with title, audience, duration, learning objectives
- Sticky header: topbar + progress bar grouped in `.player-header`
- Sticky bottom navigation bar (white background on mobile to prevent content overlap)
- Directional entrance animations per screen (up / left / right, staggered)
- `RichText` component: parses bullet and numbered lists into proper `<ul>`/`<ol>` HTML
- Screen types: text, image_text (left/right/top/bottom layouts with full-height image column), accordion (with rich text bodies), click_reveal, question
- Click-reveal redesigned as a **modal card grid**: 16:9 aspect cards with photo or gradient backgrounds, viewed-state indicator, focus-managed modal overlay
- Interactive questions: single and multiple choice with feedback
- Completion screen
- Exit button in topbar (links back to project; hidden in viewer mode)
- Viewer mode: no back-to-project navigation anywhere in the player
- Accessibility: aria attributes, focus management

---

### 5.6 Review Editor ✅

Available at `/projects/:id/review`:
- Module metadata editing (title, audience, duration, learning objectives)
- Per-screen field editing (title, body, image, alt text)
- Type-specific interaction editors (choices, feedback, accordion/click-reveal items)
- Per-screen review flags (add, resolve)
- Unsplash image picker for `image_text` screens

---

### 5.7 UI / Design System ✅

- Full dark-mode design applied to all non-player views
- Design tokens: deep navy (`#080c14`), teal accent (`#2dd4bf`), Inter font
- Atmospheric glow orbs on auth shell
- Projects view: responsive card grid with cover images, hover lift effect
- Project detail: hero banner with cover image + gradient scrim
- Project detail redesigned as a **dashboard** with a 2-column grid layout
- Mobile responsive throughout (stacking on small screens)
- Player CSS is intentionally isolated from the app design system

---

### 5.8 Viewer Invites & Progress Tracking ✅

Project owners can invite external viewers to access the course without requiring a WBT Forge account.

**Invite flow:**
- Owner enters an email address and expiry duration (1–365 days) in the Viewers panel on the project detail dashboard
- Backend sends an email with a unique magic link (`/view/{token}`)
- **Basic plan limit**: 10 active invites per project (hardcoded; licensing system deferred)
- Owner can revoke any active invite at any time

**Viewer access:**
- Viewer clicks the link and is shown an **email gate**: must enter the email address to which the invite was sent
- On match, the gate is bypassed on all future visits (verified state stored in `localStorage`)
- No account required

**Progress tracking:**
- Player reports screen index to the backend on every navigation
- Owner sees per-viewer progress: active/revoked/expired badge, mini progress bar, screen position, completion timestamp
- On return, viewers **resume from where they left off**; completed viewers restart from the beginning
- Completion is recorded once — preserved even if progress is updated later

**Data model:**
- `project_invites` — email, token, expires_at, revoked_at
- `invite_progress` — current_screen_index, total_screens, completed_at, last_active_at

---

### 5.9 API Surface ✅

Authentication:
- `POST /api/auth/login`
- `POST /api/auth/logout`

Projects:
- `GET /api/projects` — includes `cover_image_url` and `module_status` per project
- `POST /api/projects`
- `GET /api/projects/{id}` — includes `cover_image_url` and `module_status`
- `POST /api/projects/{id}/source`

Modules:
- `POST /api/projects/{id}/module/generate`
- `GET /api/projects/{id}/module`
- `PUT /api/projects/{id}/module`
- `POST /api/projects/{id}/module/flags`

Invites (authenticated):
- `GET /api/projects/{id}/invites`
- `POST /api/projects/{id}/invites`
- `DELETE /api/projects/{id}/invites/{inviteId}`

Viewer (public, token-gated):
- `GET /api/view/{token}` — validates token, returns module JSON + saved progress
- `POST /api/view/{token}/verify` — verifies viewer email against invite
- `POST /api/view/{token}/progress` — records screen progress

Media:
- `GET /api/unsplash/search?q=&page=`

Frontend routes:
- `/login`
- `/projects`
- `/projects/new`
- `/projects/:id`
- `/projects/:id/review`
- `/projects/:id/play`
- `/view/:token` — public viewer player (no auth)

---

## 6. Deferred / Known Items

### 6.1 SCORM / xAPI Export ❌
- Deferred to v2
- Backend stub (`ScormController`) exists but is not built out

### 6.2 Licensing & Payment ❌
- The 5-viewer limit is hardcoded as a basic plan constant (`MAX_INVITES = 5`)
- A full licensing/plan system requires a payment gateway
- **PayPal** is the preferred gateway (to avoid storing card data in the system)
- All plan enforcement logic is in one place (`ProjectInvitesController::MAX_INVITES = 10`) — easy to adjust when tiers are introduced

### 6.3 Email Delivery ❌ (Production)
- Currently using Laravel's `log` mail driver in development — emails written to `storage/logs/laravel.log`
- **Planned provider: Resend** (`resend/resend-laravel` package — drops in as a native Laravel mail driver)
- Migration path: set `MAIL_MAILER=resend` and add `RESEND_API_KEY` — no other code changes required
- Free tier: 3,000 emails/month, 100/day; includes delivery dashboard

### 6.4 Publishing / Public URLs ❌
- Versioned module snapshots not yet implemented
- `Event` model exists but publishing trigger is deferred

### 6.5 Analytics Dashboard ❌
- Viewer progress is tracked per-invite but there is no aggregate analytics view
- Deferred to post-MVP

### 6.6 Image Cache & Multi-Provider Media ❌
- **Sourced image cache table** (`sourced_images`): stores URL, attribution (photographer, source, license), topic/module context, and keywords
  - Before calling external image APIs, the enrichment pipeline queries this table by keyword; only hits the API on cache miss
  - Reduces API call volume and rate-limit exposure when similar topics are generated repeatedly
- **Pexels API** added as a second image provider alongside Unsplash, alternating to distribute rate limits
- **Video support** via Pexels Video API — enables a future `video` screen type in the player

### 6.7 Completion Certificates ❌
- When a viewer completes a course, a PDF certificate is generated server-side
- **Viewer name collected at the email gate** — the gate form asks for both email and full name; name is stored on `invite_progress` and used to personalize the certificate
- Certificate includes: viewer full name, course title, completion date, and a unique verification code
- Delivery: automatically emailed to the viewer on completion
- Owner access: certificate download link available per-viewer in the Viewers panel of the project dashboard
- Storage: generated PDF stored (S3 or local disk) and linked from `invite_progress`

### 6.8 Force Password Change on First Login ❌
- When a platform admin creates an account on behalf of a user, the account is flagged with `must_change_password = true`
- On first login, the user is redirected to a password change screen before accessing the app
- Flag is cleared once the password is successfully changed
- Requires a `must_change_password` boolean column on the `users` table and middleware to intercept requests until the flag is cleared

### 6.9 Other Deferred Items
- Text-to-Speech / narration (ElevenLabs integration noted for v2)
- Multi-tenant organization management
- Localization / translations
- Branching scenarios
- AI-powered content refinement ("improve this screen" in editor)
- Native PDF-to-Claude (send file directly for image-aware generation)

---

## 7. Technical Architecture

### Frontend
- React + Vite + TypeScript
- Custom dark-mode CSS design system (CSS custom properties)
- Zod for module schema validation (`moduleSchema.ts`)
- React Router for navigation
- `localStorage` used for viewer email verification persistence

### Backend
- Laravel 12 + PostgreSQL (local via Docker, production via Railway managed PostgreSQL)
- Laravel Sanctum (token-based auth for owners)
- REST API — public viewer endpoints are token-gated (no Sanctum)
- Queue-based AI generation (database queue driver)
- Anthropic Claude API (`anthropic-ai/sdk`) — **not OpenAI**
  - Default model: `claude-sonnet-4-6`
  - Configurable via `ANTHROPIC_MODEL` env var
- Unsplash API proxy
- Laravel Mail (log driver in dev; SMTP in prod)
- File parsing: smalot/pdfparser, phpoffice/phpword, phpoffice/phppresentation

### Local-First Principle
- Entire system runs locally for demo
- External calls: Claude API (generation), Unsplash API (image search)
- No AWS or cloud storage dependencies
- Local database: PostgreSQL via Docker (`postgres:16`) — mirrors production exactly, eliminating SQLite/PostgreSQL type divergence

### Starting the application
```bash
# Backend (from /backend)
composer dev

# Frontend (from /frontend)
npm run dev
```

Required env vars in `backend/.env`:
```
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6   # use claude-haiku-4-5-20251001 for faster/cheaper testing
UNSPLASH_ACCESS_KEY=
FRONTEND_URL=http://localhost:5173  # used to build viewer invite links in emails
```

---

## 8. Data Model

| Table | Purpose |
|---|---|
| `users` | Owner accounts (Sanctum auth) |
| `projects` | Title, rights attestation, source type/url/text/character count |
| `modules` | JSON payload, status, generation error, `cover_image_url` |
| `module_flags` | Per-screen review flags |
| `module_versions` | Version snapshots (stub) |
| `project_invites` | Viewer invites — email, token, expiry, revoked_at |
| `invite_progress` | Per-viewer screen progress and completion |
| `events` | Basic tracking (deferred) |
| `personal_access_tokens` | Sanctum tokens |
| `frontend_texts` | CMS-style UI text overrides |

---

## 9. Security & Compliance

- Rights attestation logged per project
- No content redistribution beyond user intent
- Unsplash attribution compliance (stored in module JSON, displayed in player)
- API keys protected server-side (Unsplash proxy, Claude key never exposed to frontend)
- Viewer access is token-gated + email-verified (case-insensitive match)
- Viewer tokens are 48-character random strings (Str::random)
- No viewer PII stored beyond the invited email address

---

## 10. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Low AI output quality | Strong schema validation + up to 3 generation retries + human review editor |
| API cost creep | No TTS in MVP; Unsplash free tier; 50k char input cap; Haiku model for testing |
| Generation timeout | Queue worker timeout set to 180s; job timeout 120s |
| Invite link abuse | Email gate verification + expiry + revocation |
| Plan limit bypass | Limit checked server-side against active (non-expired, non-revoked) invites |
| Email delivery in prod | Log driver for dev; Resend planned for prod (`MAIL_MAILER=resend`) |

---

## 11. Post-MVP Roadmap

| Item | Notes |
|---|---|
| Payment gateway (PayPal) | Unlocks paid plan tiers; viewer slot limits per plan |
| SCORM 1.2 / xAPI export | Stub exists; requires full packaging logic |
| Email delivery (Resend) | Drop-in: `MAIL_MAILER=resend` + `RESEND_API_KEY`; no other code changes |
| Completion certificates | PDF generated on course completion; emailed to viewer; downloadable by owner |
| Force password change | `must_change_password` flag on admin-created accounts; intercepted at login |
| Image cache + Pexels | `sourced_images` cache table; Pexels as second provider; video support |
| Analytics dashboard | Aggregate viewer progress, completion rates per project |
| Public learner URLs | Versioned snapshots, no invite required |
| ElevenLabs TTS | Screen narration with cost estimation |
| AI content refinement | "Improve this screen" in the editor |
| Multi-language support | Localization of generated content |
| Native PDF-to-Claude | Send file directly for image-aware generation |
| Multi-tenant orgs | Org-scoped projects, team members |
| Theme marketplace | Branded player skins |

---

## 12. Definition of Done (Updated)

- [x] Upload / URL → generate → review flow
- [x] Unsplash images auto-populated at generation; replaceable in editor
- [x] WBT player renders all screen types with interactions
- [x] Dark-mode UI across all non-player views
- [x] Project list as cover-image card grid
- [x] Project detail as dashboard
- [x] Viewer invite system (email, token, expiry, revoke)
- [x] Viewer email gate + localStorage persistence
- [x] Per-viewer progress tracking and resume
- [x] No LMS required for learner access
- [ ] SCORM export — deferred to v2
- [ ] Payment / licensing — deferred to v2
- [ ] Production email delivery — requires SMTP config at deploy time

---

## 13. Product Positioning

> *"Turn your existing documents into interactive training—no LMS required."*

> *"The fastest way to turn PDFs and slides into real training."*

---

**End of PRD**

---

## Appendix A: Module JSON Schema (Implemented)

Schema goals:
- Single source of truth for render, review, and export
- Minimal but extensible, with versioned schema
- Supports required screen types and attribution

### A.1 Core JSON Shape

```json
{
  "schema_version": "1.0",
  "meta": {
    "title": "Example Module",
    "audience": "New hires",
    "duration_minutes": 20,
    "source_summary": "Uploaded PDF: safety_handbook.pdf",
    "created_at": "2026-01-07T12:00:00Z"
  },
  "learning_objectives": [
    "Identify key safety procedures",
    "Recognize incident reporting steps"
  ],
  "screens": [
    {
      "id": "scr_001",
      "type": "text",
      "title": "Welcome",
      "body": "This training covers core safety procedures.",
      "image": null,
      "interactions": [],
      "accessibility": { "alt_text": null }
    },
    {
      "id": "scr_002",
      "type": "click_reveal",
      "title": "Key Procedures",
      "body": "Explore each procedure below.",
      "image": null,
      "interactions": [
        {
          "kind": "click_reveal",
          "items": [
            {
              "title": "Fire Safety",
              "body": "Locate the nearest exit before starting work.",
              "image": { "url": "https://images.unsplash.com/...", "alt_text": "fire exit sign" }
            }
          ]
        }
      ],
      "accessibility": { "alt_text": null }
    },
    {
      "id": "scr_006",
      "type": "question",
      "title": "Quick Check",
      "body": "Which items are required PPE?",
      "image": null,
      "interactions": [
        {
          "kind": "single_choice",
          "prompt": "Select one",
          "choices": [
            { "id": "a", "text": "Gloves", "is_correct": true },
            { "id": "b", "text": "Sandals", "is_correct": false }
          ],
          "feedback": { "correct": "Correct.", "incorrect": "Review PPE basics." }
        }
      ],
      "accessibility": { "alt_text": null }
    }
  ],
  "assessment": {
    "questions": [],
    "passing_score": 0.8
  },
  "attribution": {
    "unsplash": [
      {
        "image_id": "abc123",
        "photographer": "Jane Doe",
        "url": "https://unsplash.com/photos/abc123",
        "license": "Unsplash"
      }
    ]
  },
  "review_flags": [
    {
      "target_id": "scr_006",
      "target_type": "screen",
      "reason": "Question content may be inaccurate",
      "notes": "Verify PPE list with source doc",
      "status": "open"
    }
  ]
}
```

### A.2 Screen Types
- `text` — title + body text
- `image_text` — title + body + image (layout: `top` | `bottom` | `left` | `right`; assigned randomly at generation)
- `accordion` — collapsible items with rich text bodies
- `click_reveal` — modal card grid; cards have photo or gradient background; optional per-item images via Unsplash
- `question` — single or multiple choice with feedback

### A.3 Interaction Types
- `single_choice`
- `multi_choice`
- `accordion` (items: `title`, `body`)
- `click_reveal` (items: `title`, `body`, optional `image`)

### A.4 Validation Rules
- `schema_version` required
- `meta.title` required; `meta.duration_minutes` 10–30
- `learning_objectives` min 1
- `screens` min 10
- `screen.id` unique across module
- `question` screens must include at least 1 interaction
- `image_text` screens must include an `image` object
- Duplicate screen IDs not permitted

---

## Appendix B: User Flow (Implemented)

1. **Login** — `/login`
2. **Projects** — `/projects` — responsive card grid with cover images
3. **New Project Wizard** — `/projects/new`
   - Step 1: Title + rights attestation
   - Step 2: Upload file or paste URL
   - Step 3: Duration, objectives, assessment count
   - Step 4: Review → Generate
4. **Project Detail Dashboard** — `/projects/:id`
   - Hero banner (cover image)
   - Training module status panel (with review/preview actions)
   - Project info panel
   - Viewers panel (invite, track, revoke)
5. **Review Editor** — `/projects/:id/review`
6. **Owner Preview** — `/projects/:id/play`
7. **Viewer Player** — `/view/:token` (public, no login)
   - Email gate → course → resume on return → completion (no back-to-project)

---

## Appendix C: Environment Variables

| Variable | Required | Notes |
|---|---|---|
| `ANTHROPIC_API_KEY` | ✅ | Claude API key |
| `ANTHROPIC_MODEL` | Optional | Default: `claude-sonnet-4-6` |
| `UNSPLASH_ACCESS_KEY` | Optional | Image enrichment skipped if absent |
| `FRONTEND_URL` | ✅ | Used to build viewer invite links in emails. Default: `http://localhost:5173` |
| `MAIL_MAILER` | ✅ | `log` for dev; set to `smtp`/`mailgun`/etc. for prod |
| `MAIL_FROM_ADDRESS` | ✅ | From address for invite emails |
| `APP_NAME` | Optional | Shown in email templates. Default: `Laravel` — set to `WBT Forge` |
