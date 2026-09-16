# Weave Training — Instructional Design Analysis Layer

Architecture review and implementation plan.
Status: **proposal, nothing implemented.** Date: 2026-09-16.

---

## 1. How generation works today

The whole pipeline is a single straight line from source text to one JSON blob.

**Authoring (frontend)** — `CreateProjectWizard.tsx`, 4 steps:

1. Title + rights attestation → `POST /projects`
2. File (PDF/DOCX/PPTX, ≤10 MB) or URL → `POST /projects/{id}/source`
3. Controls: duration (10/20/30), *free-text* learning objectives, assessment-question count (0–10 slider)
4. Review → `POST /projects/{id}/module/generate`, then navigate to the project and poll.

**Source ingestion** — `ProjectSourceController::store`

- Extracts text (`Smalot\PdfParser`, PhpWord, PhpPresentation, or fetch-and-strip for URLs; PDF has a Claude vision fallback).
- **Hard-truncates to 50 000 characters.**
- `ModerationService::check` runs a Haiku classifier over the first 6 000 chars; unsafe → 422.
- Stores flat on the project: `source_type`, `source_url`, `source_text`, `source_character_count`.

**Enqueue** — `EnforceQuota('module')` → `ModuleController::generate` → `GenerationCredits::enqueue`

In one locking transaction: locks user + project, refuses if a run is already `queued`/`running`
(enforced by the partial unique index `generation_runs_one_active_project`), finds the active
subscription, **reserves** a module credit (`subscriptions.modules_reserved++`), creates a
`generation_runs` row, upserts the `modules` row to `status = generating` *keeping the previous
`module_json` intact*, and dispatches `GenerationJob` on the `database` queue.

**Generation** — `GenerationJob::handle` → `GenerationService::generate`

- If `source_text` > 8 000 chars: a **Haiku extraction pre-pass** condenses it to ~3 000–4 000 words of prose.
- One Sonnet streaming call, `max_tokens: 16000`, `temperature: 0.4`, with a ~300-line system prompt
  that carries the entire screen-type schema (10 screen types), the content-policy escape hatch, and
  all structure/validation rules.
- Response is scraped for JSON (`extractJson`: BOM strip, fence strip, control-char strip, regex
  fallback), then `validate()` checks required keys, ≥10 screens, unique IDs, interaction/image rules.
- Up to **3 attempts** on parse or validation failure; `DomainException` (content policy) is non-retriable.

**Post-processing, in the job:**

- `enrichImages` — per-screen and per-`click_reveal`-item stock photos: semantic cache
  (`ImageCacheService` + pgvector) → Pexels → Unsplash, with a large `refineImageQuery` heuristic that
  strips people-terms and lesson-structure stop words, plus per-module URL de-duplication.
- `enrichVideos` — Pexels video, downgrading the screen to `image_text` if nothing is found.
- `assignLayouts`, `extractCoverImage`.

**Completion** — `GenerationCredits::finish`

Converts the reservation (`modules_reserved--`, `modules_used++` on success only), flips the run to
`succeeded`/`failed`, writes `modules.module_json` + `cover_image_url`, writes an `Event`, notifies.
On failure the previous `module_json` survives and the credit is refunded.

**Editing & delivery** — `ProjectDetailView` polls `GET /projects/{id}/module`; `ModuleEditorView`
edits `module_json` wholesale and `PUT`s it back (client-side Zod `moduleSchema` is the only real
schema check — the server validates `array` and nothing more). `WBTPlayer` renders it, `ViewerController`
serves token-gated learners, `ScormExporter` zips `module.json` with the built player.

### Relevant surface, by file

| Area | Files |
|---|---|
| Prompt + LLM | `app/Services/GenerationService.php`, `ModerationService.php` |
| Orchestration | `app/Jobs/GenerationJob.php`, `app/Services/GenerationCredits.php` |
| HTTP | `ModuleController`, `ProjectSourceController`, `ProjectsController`, `Middleware/EnforceQuota` |
| Data | `projects`, `modules`, `module_versions` *(table + model exist, nothing writes to them)*, `generation_runs`, `subscriptions`, `events` |
| Frontend | `CreateProjectWizard`, `ProjectDetailView`, `ModuleEditorView`, `WBTPlayer`, `lib/moduleSchema.ts`, `lib/api.ts` |
| Export | `ScormExporter`, `ViewerController`, `frontend/src/scorm` |

---

## 2. Gap analysis against the proposed workflow

The target is `Source → Needs Analysis → Design Plan → Human Review → Generation → Validation`.

| Proposed capability | Today | Gap |
|---|---|---|
| Performance/outcome capture | free-text textarea, pasted verbatim into the prompt | outcomes have no identity, no type, no strategy, no assessment mapping |
| Audience | inferred by the model, written into `meta.audience` | never asked, never influences design decisions |
| Business need | absent | absent |
| Evidence of learning | `assessment_count` integer | **actively fights the goal** — it forces N question screens regardless of outcome type |
| Constraints | duration + assessment count | tone, compliance, device, passing score not captured |
| Design plan before content | none | the model designs and authors in a single call; nothing is reviewable |
| "Why?" rationale | none | the model never emits reasoning, and there is nowhere to put it |
| Source traceability | none | §2.1 below — the pre-pass destroys the addresses this needs |
| Source validation (conflicts, gaps) | none; `review_flags[]` exists in the schema but is only ever author-authored | no analysis pass exists |
| Human approval gate | none — the wizard's last button generates | needs a real state machine |
| Structured ID data | one denormalized `module_json` blob | nothing is queryable: no coverage %, no orphan detection, no partial regeneration |

### 2.1 The blocker nobody would notice from the spec

**The Haiku extraction pre-pass makes the traceability goal impossible.** Any source over 8 000
characters is rewritten into prose *before Sonnet ever sees it*. Section numbers, clause references,
and exact wording are gone. You cannot produce `Policy §4.2 → R-12 → LO-3` from a paraphrase, and you
cannot detect "Section 3 contradicts Section 7" when neither section survives as an addressable unit.

This is also, independently, a plausible cause of the "sources not showing" item already on the
known-bugs list. Fixing it earns its keep even if the rest of this plan is deferred — which is why it
is Phase 1 rather than a detail inside Phase 2.

---

## 3. What to reuse (most of it)

Reuse unchanged:

- **`generation_runs` + `GenerationCredits`.** The reserve/claim/finish ledger, the crash-safe credit
  refund, and the one-active-run-per-project index are exactly the semantics an analysis run needs.
  Add a `kind` column rather than building a second run system.
- **`modules.module_json` as the content source of truth.** Every consumer — player, editor, viewer,
  SCORM exporter — reads it. Do not normalize screens.
- **Screen IDs (`scr_001`, …).** They are already stable and unique-validated. They are the join key
  for design→content traceability. No new identifier scheme is needed.
- **The job/queue/notification/event shape**, `ImageCacheService`, `PexelsService`, image enrichment,
  `ModerationService`, `EnforceQuota`, the polling pattern in `ProjectDetailView`.
- **The screen-type catalogue.** Ten types already cover the strategies the brief names — `scenario`
  for judgment, `process` for procedure, `flashcard`/`question` for recall, `click_reveal` for
  classification. The design layer's job is to *choose deliberately* among types that already exist.

Repurpose:

- **`module_versions`** is dead scaffolding (table + model, zero writers). Either delete it or make it
  the snapshot store for approved design plans + generated content. Deciding this is cheap and removes
  a misleading affordance.
- **`review_flags[]`** in `module_json` is the natural home for post-generation validation warnings.

Retire:

- The `assessment_count` slider as a *driver*. Keep accepting it on the API as a cap/hint for
  back-compat, but let the design plan decide the assessment form per outcome.
- The Haiku condense pre-pass (replaced, §4.1).

---

## 4. Proposed data model

The guiding rule: **normalize what you need to query, keep the rest as JSON.** The brief's §7 goals —
coverage %, orphan detection, per-activity regeneration, preserved author overrides — are all
relational queries. The narrative fields (business need, audience prose, constraints) are never
queried and belong in JSON.

### 4.1 `source_segments` — addressable source (Phase 1)

```
source_segments
  id             string pk        seg_001…
  project_id     fk projects
  ordinal        int
  heading_path   string           "3 Reporting / 3.2 Timeliness"
  label          string           author-facing address, e.g. "§3.2"
  text           text
  char_start     int
  char_end       int
  token_estimate int
```

Replaces the lossy condense. Segmentation is heading-aware and deterministic (a parser, not an LLM,
with a fast-model pass only to infer headings when the document has no structural markup). Long
sources are then handled by *selecting relevant segments per request* rather than by globally
paraphrasing — which also softens the 50 000-char truncation.

### 4.2 `design_plans` — the reviewable artifact

```
design_plans
  id, project_id fk, version int
  status         enum draft | analyzing | ready | approved | superseded
  plan_json      jsonb   business_need, audience{}, constraints{}, express_approved
  analysis_meta  jsonb   model, prompt_version, token usage, timings
  approved_at, approved_by (fk users)
```

One `ready`/`approved` plan per project at a time; revising an approved plan creates `version + 1`
and marks the old one `superseded`. Snapshots, not mutations — otherwise coverage figures drift away
from the content that was actually built.

### 4.3 Requirements, outcomes, and the links between them

```
design_requirements                      learning_outcomes
  id  R-12                                 id  LO-3
  design_plan_id                           design_plan_id
  segment_id   fk source_segments          statement           observable verb
  text                                     performance_type    recall|identification|
  kind  policy|procedure|constraint|fact                       procedure|judgment|decision
  status included|excluded|needs_review    strategy            worked_example|classification|
  exclusion_note                                               demonstration|branching_scenario|…
  confidence   float                       activity_hint
  origin  ai | author                      assessment_kind     none|retrieval|classification|
                                                               demonstration|scenario
                                           rationale           powers the "Why?" affordance
                                           position, origin, author_edited bool

requirement_outcome  (pivot)   → source coverage
outcome_screen  (outcome_id, screen_id string, role teach|practice|assess, origin)
                               → design ↔ content traceability, orphan + gap detection
```

`outcome_screen` is the whole trick: it references `module_json` screen IDs *as strings*, with no
foreign key into content. Screens are never normalized, existing modules are never migrated, and the
chain `segment → requirement → outcome → screen` is still a two-join query.

### 4.4 `source_findings` — validation output

```
source_findings
  id, design_plan_id
  kind      conflict | ambiguity | missing_step | duplicate | outdated_reference |
            omitted_requirement | non_training_cause
  severity  info | warning | critical      (advisory only — never blocks)
  message, suggestion
  segment_ids  jsonb   one or many — a conflict cites two
  status    open | acknowledged | resolved | dismissed
```

The brief's non-training-problem warning ("they know how, they just forget") is a `non_training_cause`
finding — same table, same UI, advisory, never blocking.

### 4.5 Small changes to existing tables

- `generation_runs.kind` — `analysis | design | content`, default `content` for existing rows.
- `generation_runs.subscription_id` → nullable (analysis runs don't reserve a credit).
- `generation_runs.started_at` / heartbeat, so a reaper can release the one-active-run lock.
- `modules.design_plan_id` — nullable fk recording which plan produced this content.
- `module_json.screens[].outcome_ids` — **optional** array. Zod stays backward compatible; old modules
  validate unchanged.

No changes to `projects`, `subscriptions`, `plans`, invites, or anything in the viewer path.

---

## 5. Proposed service and API changes

### 5.1 Split the monolith prompt

`GenerationService` currently does objectives, structure, and screen authoring in one 300-line prompt.
Split into services sharing a `Prompts` registry (versioned prompt strings, moved out of PHP heredocs
so prompt changes are reviewable and `analysis_meta.prompt_version` means something):

| Service | Input | Output |
|---|---|---|
| `SourceSegmenter` | `projects.source_text` | `source_segments` rows |
| `AnalysisService` | segments + author answers | requirements, findings, proposed outcomes |
| `DesignPlanService` | analysis + author edits | per-outcome type/strategy/activity/evidence/rationale |
| `GenerationService` | **approved plan** + selected segments | screens (existing prompt, existing schema) |

### 5.2 Use tool-use structured output, not JSON scraping

`extractJson`'s BOM/fence/control-char/regex gauntlet plus a 3-attempt retry loop is direct evidence
that "reply with JSON and nothing else" is not holding. The Anthropic SDK is already a dependency:
define a tool with an `input_schema` and read `tool_use.input`. The parser disappears, the retry loop
shrinks, and the *new* analysis/design calls — which have larger, more nested schemas than the screen
schema — get reliability for free. Do this in Phase 0, before adding calls that would inherit the
fragility.

### 5.3 Endpoints

```
POST   /projects/{id}/analysis           start analysis (no module credit)   202
GET    /projects/{id}/design             plan + requirements + outcomes + findings + coverage
PATCH  /projects/{id}/design             business need, audience, constraints
POST   /projects/{id}/design/outcomes    add            PATCH/DELETE .../{outcomeId}
PATCH  /projects/{id}/design/requirements/{rid}   include | exclude(+note) | resolve
PATCH  /projects/{id}/design/findings/{fid}       acknowledge | dismiss
POST   /projects/{id}/design/approve      → freezes the plan, then enqueues content generation
GET    /projects/{id}/coverage            post-generation report
```

`POST .../design/approve` is where `EnforceQuota('module')` and `GenerationCredits::enqueue` move to.
`POST /projects/{id}/module/generate` **stays and keeps working** — retry-generation and every
existing project depend on it; when a project has an approved plan it generates from the plan,
otherwise it behaves exactly as it does today.

### 5.4 Assessment selection replaces the slider

`assessment_kind` per outcome maps to screen types the player already renders:

| Performance type | Strategy | Assessment | Screen type |
|---|---|---|---|
| recall | retrieval practice | retrieval | `question` / `flashcard` |
| identification | worked examples | classification | `click_reveal` + `question` |
| procedure | demonstration + guided practice | demonstration | `process` + `question` |
| judgment | branching scenario | scenario | `scenario` |
| complex decision | scenario with feedback | scenario | `scenario` (multiple) |

Some outcomes legitimately map to `none`. `assessment_count` becomes an upper bound the plan respects,
not a quota the model must fill.

---

## 6. UX changes

**Wizard** (steps 1–2 unchanged): step 3 becomes **Analyze** rather than *Controls* — one compact
screen holding all five questions, every field pre-filled by AI with an edit affordance, nothing
required. The brief's warning about questionnaire fatigue is the design constraint here: five
headings, not five steps.

**New: `/projects/:id/design` — Design Plan review.** This is the product.

- Business need / audience / constraints as editable summary blocks.
- Outcomes table — outcome · type · strategy · evidence — inline editable, reorderable, add/remove,
  each row with a **Why?** disclosure showing `rationale`.
- Findings panel — conflicts and gaps first, each citing its source segments side by side, with
  Acknowledge / Dismiss. Never blocks the Approve button.
- Coverage meter — requirements identified / addressed / assessed / excluded / needs review, each
  number clicking through to the list behind it.
- Footer: **Approve & Build** (primary) and **Accept recommended design** (the express path — one
  click from analysis to build for authors who trust the defaults).

**Project detail:** a *Design* tab next to the existing overview, a coverage summary once content
exists, and status copy for the new `analyzing` / `awaiting approval` states the poller must handle.

**Editor:** each screen gets an outcome badge from `outcome_screen`, plus "unassessed outcome" and
"orphan question" nudges fed by the validation pass.

---

## 7. Risks, migration, and back-compat

| Risk | Handling |
|---|---|
| **Existing modules have no design plan** | Every read path tolerates `design_plan === null`; the design tab shows a "this course predates design plans" empty state. **Never backfill by re-running AI over old projects.** |
| **`module_json` compatibility** | Only additive, optional (`screens[].outcome_ids`). Existing Zod validation passes on untouched modules. Player, viewer, SCORM exporter need no change — the reason for not normalizing screens. |
| **Two extra Claude calls before any content exists** | Time-to-first-course goes up, and it lands on the *first-run* experience. Mitigate: stream analysis results progressively, segment with the fast model, and make the express path genuinely one click. Measure before Phase 4 ships. |
| **Analysis cost with no credit charged** | Analysis doesn't consume a module credit (the credit belongs at content generation, where it is now). Cap re-analyses per project (say 3) rather than inventing a second credit type. Revisit with real numbers. |
| **The one-active-run index now blocks more** | A stuck `analysis` run locks out generation. Needs `started_at` + a reaper command that fails runs past `timeout`. This gap exists today too — it just gets more reachable. |
| **Plan/content drift** | Approved plans are immutable snapshots; revising creates a new version and marks the previous `superseded`. Coverage is always computed against the version that built the content. |
| **Author overrides lost on regeneration** | `origin` + `author_edited` on outcomes and requirements; regeneration must preserve any row where `author_edited = true`. Worth a test from the first phase that writes these columns. |
| **Abandoned drafts** | Plans in `draft`/`ready` with no approval accumulate. Harmless, but the project list needs a state for them so it doesn't look broken. |
| **Prompt regression** | Phase 1 changes how source reaches the model. Ship behind a flag and compare output on a fixed set of real sources before flipping the default. |
| **Billing / quota** | Untouched. The credit is still reserved once, at build time, by the same code. |

---

## 8. Phased plan

Each phase is independently shippable and leaves the product working.

**Phase 0 — Foundations (no user-visible change).**
Tool-use structured output in `GenerationService`; prompts extracted to a versioned `Prompts` registry;
`generation_runs.kind` + nullable `subscription_id` + `started_at`; stuck-run reaper command; decide
`module_versions`' fate. *Exit: current generation is comparable in quality with a simpler parser.*

**Phase 1 — Addressable source.**
`source_segments`, heading-aware segmenter, generation fed selected segments instead of the Haiku
condense. Flagged, compared against real sources. *Exit: no condense pre-pass; source references
survive into generated content.*

**Phase 2 — Analysis (backend only).**
`design_plans`, `design_requirements`, `source_findings`, `AnalysisService`,
`POST /projects/{id}/analysis`, `GET /projects/{id}/design`. No production UI — validate quality
against real documents first. *Exit: analysis produces credible requirements and finds a planted
contradiction.*

**Phase 3 — Design plan.**
`learning_outcomes`, `requirement_outcome`, `DesignPlanService`, edit + approve endpoints, plan
versioning, rationale capture. *Exit: a plan can be produced, edited via API, and approved.*

**Phase 4 — Review UI.**
`/projects/:id/design`, the revised wizard step 3, Why? disclosures, findings panel, coverage meter,
express approve, new polling states. *Exit: the full workflow is usable end to end.*

**Phase 5 — Design-driven generation.**
Generation consumes the approved plan; assessment form derives from `assessment_kind`;
`screens[].outcome_ids` emitted; `outcome_screen` populated post-generation; coverage report.
*Exit: `segment → requirement → outcome → screen` resolves for a newly built course.*

**Phase 6 — Validation.**
Post-generation check against the plan: unaddressed outcomes, unassessed outcomes, orphan questions,
uncovered requirements — written into `review_flags[]` and surfaced in the editor.
*Exit: the loop in the brief's §8 is closed.*

**Phase 7 — Later.**
Per-outcome regeneration without rebuilding the course; per-user trusted-defaults auto-approve;
design plan / coverage report as an export artifact.

Phases 0–1 are worth doing regardless of whether the rest is built.

---

## 9. Open questions

1. **Analysis billing** — free, as proposed, or does it consume something? Affects Phase 2 scope.
2. **Post-build plan editing** — accept immutable-with-versioning, or do you want live editing with
   drift warnings? Versioning is simpler and safer; live editing is friendlier.
3. **Retrofit** — should existing projects get an "analyze this course" action that produces a plan
   from already-generated content, or is the design layer new-projects-only?
4. **Express path default** — is the recommended design accepted by default (review optional), or is
   review always the default with express as an opt-in per user? This decides whether Weave reads as a
   design partner or as a generator with a confirmation dialog.
5. **Screen-count/duration controls** — duration currently drives screen count directly. Should the
   design plan own pacing instead, with duration as a constraint it tries to satisfy?
6. **Competency reuse** — outcomes are modeled per plan. Reusing them across projects (an org
   competency library) is a natural later product, and the schema above allows it, but it is
   deliberately out of scope here.
7. **`module_versions`** — delete, or repurpose as the approved-plan + content snapshot store?
