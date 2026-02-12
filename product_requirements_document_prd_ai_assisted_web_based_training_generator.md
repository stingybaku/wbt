# Product Requirements Document (PRD)

## Product Name (Working)
**WBT Forge** (working title)

## Document Status
Draft – MVP Definition (Tightened)

## Author
Julio (Product / Engineering)

## Last Updated
2026-01-07

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

### 5.1 Content Input
- File upload:
  - PDF
  - DOCX
  - PPTX
- URL input (public or authenticated via copy/paste)
- File size limit: 10MB
- Mandatory user attestation: *“I confirm I have the rights to use this content.”*

---

### 5.2 Generation Controls

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

### 5.3 Output Structure

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

---

### 5.4 Rendering (Frontend)

- React-based WBT player
- Screen navigation (Next / Previous)
- Progress indicator
- Interactive questions
- Accessibility-friendly markup

---

### 5.5 Media (MVP)

- **Images**:
  - Unsplash API (retrieve-only)
  - Backend proxy to protect API key
  - Mandatory attribution stored in module JSON

- **Audio (Out of Scope for MVP)**:
  - TTS deferred to v2

---

### 5.6 Review & Publishing

MVP focuses on upload → generation → review. Publishing is deferred, but implementation should support:
- A local, app-served learner URL
- Versioned snapshots on publish for auditability
- Basic event tracking when publish is enabled:
  - Start
  - Screen views
  - Completion

---

### 5.7 Export

- SCORM 1.2 export
  - Single SCO
  - Includes:
    - imsmanifest.xml
    - module.json
    - static player that reads module.json

xAPI export deferred to v2

---

## 6. Non-Goals (Explicitly Out of Scope for MVP)

- Text-to-Speech / narration
- Advanced analytics dashboards
- Multi-tenant org management
- Billing / subscriptions
- Localization / translations
- Branching scenarios

---

## 7. Technical Architecture (MVP)

### Frontend
- React + Vite
- Tailwind CSS
- Zod for schema validation

### Backend
- Laravel 12
- SQLite database
- REST API
- Unsplash API proxy
- SCORM export service
- OpenAI API integration (platform-provided API key)

### Local-First Principle
- Entire system must run locally for demo, except AI generation calls
- No AWS or cloud dependencies beyond OpenAI API

---

## 8. Data Model (MVP)

Core entities:
- Project
- Module (JSON payload)
- Publish (slug-based delivery)
- Event (basic tracking)
 - User (login required)

---

## 9. Security & Compliance

- Rights attestation logged per project
- No content redistribution beyond user intent
- Unsplash attribution compliance
- PII protection compliance (avoid storing learner PII for MVP)

---

## 10. Risks & Mitigations

| Risk | Mitigation |
|----|----|
| Low AI output quality | Strong schema validation + human review |
| API cost creep | No TTS in MVP; Unsplash free tier; cap input size |
| LMS comparison | Position as LMS-free, rapid deployment tool |
| Overbuilding | Strict MVP scope, local demo focus |

---

## 11. Future Enhancements (Post-MVP)

- ElevenLabs TTS with cost estimation
- xAPI export
- Seat limits & learner management
- Analytics dashboard
- Multi-language support
- Theme marketplace
- AI-powered content refinement

---

## 12. MVP Definition of Done

- Local demo: upload/URL → generate → review
- Unsplash image picker functional
- SCORM export imports cleanly into SCORM Cloud
- No external infrastructure required
- Clear path to v2 monetization

---

## 13. Product Positioning (Draft)

> *“Turn your existing documents into interactive training—no LMS required.”*

Or:

> *“The fastest way to turn PDFs and slides into real training.”*

---

**End of PRD**

---

## Appendix A: Module JSON Schema (MVP Proposal)

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

### A.5 Zod Schema (Starter)

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

Editor requirements:
- Form-based review with flags and notes per screen/question
- Ability to mark inaccurate text/images before publish

---

## Appendix B: MVP User Flow (Screens)

1) Login
- Email/password login

2) Projects
- Project list
- Create project

3) New Project Setup
- Title
- Rights attestation checkbox

4) Content Input
- Upload PDF/DOCX/PPTX (10MB limit) or paste URL

5) Generation Controls
- Duration
- Learning objectives (user or AI)
- Assessment count

6) Generate
- Progress state
- Output preview summary (title, objectives, screen count)

7) Review Editor (Form-based)
- Screen list with types
- Fields for text, images, and interactions
- Review flags and notes (per screen/question)

8) Export
- SCORM 1.2 export (static player + module.json)

---

## Appendix C: MVP API Endpoints (Draft)

Authentication:
- `POST /api/auth/login`
- `POST /api/auth/logout`

Projects:
- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/{id}`

Modules:
- `POST /api/projects/{id}/module/generate`
- `GET /api/projects/{id}/module`
- `PUT /api/projects/{id}/module` (editor updates)
- `POST /api/projects/{id}/module/flags`

Media:
- `GET /api/unsplash/search?q=&page=`

Exports:
- `POST /api/projects/{id}/scorm`

Events (enabled when publishing is implemented):
- `POST /api/projects/{id}/events`
