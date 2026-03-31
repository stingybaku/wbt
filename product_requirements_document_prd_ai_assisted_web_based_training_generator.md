# Product Requirements Document (PRD)

## Product Name (Working)
**WBT Forge** (working title)

## Document Status
MVP Implemented — `feature/finalize-schema`

## Author
Julio (Product / Engineering)

## Last Updated
2026-03-30

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
- SCORM export imports cleanly into SCORM Cloud

### Success Metrics
- Time to first generated draft module
- % of generated modules reviewed (flagged/annotated)
- # of screens edited post-generation (lower is better)
- Successful SCORM imports during demos

---

## 5. MVP Scope

### 5.1 Content Input ✅ Implemented
- File upload:
  - PDF
  - DOCX
  - PPTX
- URL input (public or authenticated via copy/paste)
- File size limit: 10MB
- Mandatory user attestation: *"I confirm I have the rights to use this content."*
- Text extracted server-side (smalot/pdfparser, phpoffice/phpword, phpoffice/phppresentation)
- Content capped at 50,000 characters before being passed to the AI

> **Note:** Password-protected PDFs are not supported. URL sources are subject to the target server allowing server-side HTTP requests.

---

### 5.2 Generation Controls ✅ Implemented

Users must provide or choose:
- **Duration**
  - Minimum: 10 minutes
  - Default: 20 minutes
  - Maximum: 30 minutes
- **Learning Objectives**
  - User-defined OR AI-generated
- **Assessment Questions**
  - User-defined count OR AI-decided
- **Output Size**
  - Target: 10 screens (minimum acceptable output)

---

### 5.3 Output Structure ✅ Implemented

The system generates a validated JSON module containing:
- Metadata (title, audience, duration)
- Learning objectives
- Screens (content, tabs, hotspots)
- Interactions:
  - Multiple choice (single and multiple select)
  - Click and reveal
  - Accordion
- Optional assessment block
- Accessibility metadata

The JSON is the **single source of truth** for:
- Rendering
- Editing
- Publishing
- Exporting

Generation is handled asynchronously via a Laravel queue job (`GenerationJob`). The job calls the Claude API (`GenerationService`), retries up to 3 times on schema validation failure, and updates the module status to `generated` or `failed`. The frontend polls every 3 seconds until the status changes.

---

### 5.4 Rendering (Frontend) ✅ Implemented

- React-based WBT player (`WBTPlayer`)
- Cover screen with module title, audience, duration, and learning objectives
- Screen navigation (Next / Previous) with progress bar
- Interactive questions (single and multiple choice) with answer feedback
- Accordion and click-reveal interactions
- Completion screen
- Accessibility-friendly markup (aria-expanded, aria-live, focus management)
- Preview available at `/projects/:id/play`

---

### 5.5 Media (MVP) ✅ Implemented

- **Images**:
  - Unsplash API (retrieve-only)
  - Backend proxy (`UnsplashController`) to protect API key
  - Mandatory attribution stored in `module.attribution.unsplash`
  - `image_text` screens are **automatically populated** with a relevant Unsplash image at generation time (using the screen title as the search query)
  - Users can replace any image via the Unsplash picker in the review editor

- **Audio (Out of Scope for MVP)**:
  - TTS deferred to v2

---

### 5.6 Review & Publishing ⚠️ Partially Implemented

- Review editor (`ModuleEditorView`) is implemented at `/projects/:id/review`:
  - Module metadata editing (title, audience, duration, learning objectives)
  - Per-screen field editing (title, body, image alt text)
  - Interaction editors (question choices/feedback, accordion/click-reveal items)
  - Per-screen review flags (add, resolve)
  - Unsplash image picker for `image_text` screens
- Publishing (public learner URL, versioned snapshots, event tracking) is **deferred**

---

### 5.7 Export ❌ Deferred (out of MVP scope)

- SCORM 1.2 export deferred to v2
- Backend stub (`ScormController`) exists but is not built out
- xAPI export deferred to v2

---

## 6. Non-Goals (Explicitly Out of Scope for MVP)

- Text-to-Speech / narration
- Advanced analytics dashboards
- Multi-tenant org management
- Billing / subscriptions
- Localization / translations
- Branching scenarios
- SCORM / xAPI export
- Public learner URLs / publishing

---

## 7. Technical Architecture (MVP)

### Frontend
- React + Vite + TypeScript
- Tailwind-inspired custom CSS
- Zod for module schema validation (`moduleSchema.ts`)
- React Router for navigation
- Key routes:
  - `/login` — authentication
  - `/projects` — project list
  - `/projects/new` — creation wizard
  - `/projects/:id` — project detail + module status
  - `/projects/:id/review` — review editor
  - `/projects/:id/play` — WBT player (preview)

### Backend
- Laravel 12 + SQLite
- Laravel Sanctum (token-based auth)
- REST API
- Queue-based AI generation (database queue driver)
- Anthropic Claude API (`anthropic-ai/sdk`) — **not OpenAI**
  - Default model: `claude-sonnet-4-6`
  - Configurable via `ANTHROPIC_MODEL` env var
- Unsplash API proxy
- File parsing: smalot/pdfparser, phpoffice/phpword, phpoffice/phppresentation

### Local-First Principle
- Entire system runs locally for demo
- External calls: Claude API (generation), Unsplash API (image search)
- No AWS or cloud storage dependencies

### Starting the application
```bash
# Backend (from /backend) — starts server, queue worker, log watcher, backend Vite
composer dev

# Frontend (from /frontend) — starts React dev server
npm run dev
```

Required env vars in `backend/.env`:
```
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6   # use claude-haiku-4-5-20251001 for faster/cheaper testing
UNSPLASH_ACCESS_KEY=
```

---

## 8. Data Model (MVP)

Core entities:
- `User` — login required, projects scoped to user
- `Project` — title, rights attestation, source type/url/text/character count
- `Module` — JSON payload, status (`generating` | `generated` | `failed`), generation error
- `ModuleFlag` — per-screen review flags (stored in both DB and module JSON)
- `Event` — basic tracking (implemented, publishing trigger deferred)

---

## 9. Security & Compliance

- Rights attestation logged per project
- No content redistribution beyond user intent
- Unsplash attribution compliance (stored in module JSON, displayed in player)
- PII protection compliance (avoid storing learner PII for MVP)
- API keys protected server-side (Unsplash proxy, Claude key never exposed to frontend)

---

## 10. Risks & Mitigations

| Risk | Mitigation |
|----|----|
| Low AI output quality | Strong schema validation + up to 3 generation retries + human review editor |
| API cost creep | No TTS in MVP; Unsplash free tier; 50k char input cap; Haiku model for testing |
| Generation timeout | Queue worker timeout set to 180s; job timeout 120s |
| LMS comparison | Position as LMS-free, rapid deployment tool |
| Overbuilding | Strict MVP scope, local demo focus |

---

## 11. Future Enhancements (Post-MVP)

- SCORM 1.2 / xAPI export
- Public learner URLs with versioned snapshots
- Basic event tracking (start, screen views, completion)
- ElevenLabs TTS with cost estimation
- Seat limits & learner management
- Analytics dashboard
- Multi-language support
- Theme marketplace
- AI-powered content refinement
- Native document input to Claude (send PDF directly for image-aware generation)

---

## 12. MVP Definition of Done

- [x] Local demo: upload/URL → generate → review
- [x] Unsplash image picker functional (auto-populated on generation, replaceable in editor)
- [x] WBT player renders all screen types with interactive questions
- [ ] SCORM export imports cleanly into SCORM Cloud — **deferred to v2**
- [x] No external infrastructure required beyond API keys
- [x] Clear path to v2 monetization

---

## 13. Product Positioning (Draft)

> *"Turn your existing documents into interactive training—no LMS required."*

Or:

> *"The fastest way to turn PDFs and slides into real training."*

---

**End of PRD**

---

## Appendix A: Module JSON Schema (MVP — Implemented)

Schema goals:
- Single source of truth for render, review, and export
- Minimal but extensible, with versioned schema
- Supports required screen types and attribution

### A.1 Core JSON Shape (MVP)

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

### A.2 Required Screen Types
- `text`
- `image_text`
- `accordion`
- `click_reveal`
- `question`

### A.3 Interaction Types
- `single_choice`
- `multi_choice`
- `accordion` (items with `title`, `body`)
- `click_reveal` (items with `title`, `body`)

### A.4 Validation Rules (MVP)
- `schema_version` required, semantic version string
- `meta.title` required; `meta.duration_minutes` between 10 and 30
- `learning_objectives` minimum 1 item
- `screens` minimum 10 items (target output)
- `screen.id` unique across module
- `question` screens must include at least 1 interaction
- `image.alt_text` required when `image` is present
- `image_text` screens must include an `image` object
- Duplicate screen IDs are not permitted (enforced globally)
- Additional constraints implemented in Zod `superRefine` logic

### A.5 Zod Schema (Implemented — `frontend/src/lib/moduleSchema.ts`)

```ts
import { z } from "zod";

const choiceSchema = z.object({
  id: z.string(),
  text: z.string(),
  is_correct: z.boolean()
});

const interactionSchema = z.discriminatedUnion("kind", [
  z.object({
    kind: z.literal("single_choice"),
    prompt: z.string(),
    choices: z.array(choiceSchema).min(2),
    feedback: z.object({
      correct: z.string(),
      incorrect: z.string()
    })
  }),
  z.object({
    kind: z.literal("multi_choice"),
    prompt: z.string(),
    choices: z.array(choiceSchema).min(2),
    feedback: z.object({
      correct: z.string(),
      incorrect: z.string()
    })
  }),
  z.object({
    kind: z.literal("accordion"),
    items: z.array(
      z.object({ title: z.string(), body: z.string() })
    ).min(2)
  }),
  z.object({
    kind: z.literal("click_reveal"),
    items: z.array(
      z.object({ title: z.string(), body: z.string() })
    ).min(2)
  })
]);

const screenSchema = z.object({
  id: z.string(),
  type: z.enum(["text", "image_text", "accordion", "click_reveal", "question"]),
  title: z.string(),
  body: z.string(),
  image: z.object({
    url: z.string().url(),
    alt_text: z.string()
  }).nullable(),
  interactions: z.array(interactionSchema),
  accessibility: z.object({ alt_text: z.string().nullable() })
});

export const moduleSchema = z.object({
  schema_version: z.string(),
  meta: z.object({
    title: z.string(),
    audience: z.string(),
    duration_minutes: z.number().min(10).max(30),
    source_summary: z.string(),
    created_at: z.string()
  }),
  learning_objectives: z.array(z.string()).min(1),
  screens: z.array(screenSchema).min(10),
  assessment: z.object({
    questions: z.array(z.any()),
    passing_score: z.number().min(0).max(1)
  }).optional(),
  attribution: z.object({
    unsplash: z.array(z.object({
      image_id: z.string(),
      photographer: z.string(),
      url: z.string().url(),
      license: z.string()
    }))
  }),
  review_flags: z.array(z.object({
    target_id: z.string(),
    target_type: z.enum(["screen", "interaction"]),
    reason: z.string(),
    notes: z.string().optional(),
    status: z.enum(["open", "resolved"])
  })).default([])
});
```

---

## Appendix B: MVP User Flow (Implemented)

1) **Login** — `/login`
   - Email/password login via Sanctum token auth

2) **Projects** — `/projects`
   - Project list scoped to authenticated user
   - "New Project" button

3) **New Project Wizard** — `/projects/new`
   - Step 1: Title + rights attestation checkbox
   - Step 2: Upload PDF/DOCX/PPTX (10MB limit) or paste URL
   - Step 3: Duration, learning objectives, assessment count
   - Step 4: Review summary → "Generate Training"

4) **Project Detail** — `/projects/:id`
   - Shows project info, source metadata, module status
   - Polls during generation; shows error + retry on failure
   - "Review module" and "Preview" buttons when generated

5) **Review Editor** — `/projects/:id/review`
   - Two-panel layout: screen list sidebar + editor panel
   - Module info editing
   - Per-screen field editing with type-specific interaction editors
   - Unsplash image picker for image_text screens
   - Per-screen review flags (add/resolve)
   - Save changes

6) **WBT Player (Preview)** — `/projects/:id/play`
   - Cover screen → screens → completion
   - Interactive questions with feedback
   - Accordion and click-reveal interactions
   - Progress bar

7) **Export** — ❌ Deferred to v2

---

## Appendix C: MVP API Endpoints (Implemented)

Authentication:
- `POST /api/auth/login`
- `POST /api/auth/logout`

Projects:
- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/{id}`
- `POST /api/projects/{id}/source`

Modules:
- `POST /api/projects/{id}/module/generate`
- `GET /api/projects/{id}/module`
- `PUT /api/projects/{id}/module`
- `POST /api/projects/{id}/module/flags`

Media:
- `GET /api/unsplash/search?q=&page=`

Exports (stub only):
- `POST /api/projects/{id}/scorm` — not implemented

Events (deferred):
- `POST /api/projects/{id}/events`
