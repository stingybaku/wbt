# Phase 3 — AI Generation: Test Cases

## Setup

```bash
# Backend (from /backend) — starts web server AND queue worker
composer dev

# Frontend (from /frontend)
npm run dev
```

Add your API key to `/backend/.env`:
```
ANTHROPIC_API_KEY=sk-ant-...
```

Use demo credentials: `demo@wbtforge.test` / `password`

---

## TC-01 — Full happy path: file upload → generation → success

**Steps:**
1. Log in and click "New Project"
2. Complete the wizard:
   - Title: any title
   - Source: upload a PDF or DOCX with readable text
   - Duration: 20 min, any objectives/assessment count
3. Click "Generate Training"

**Expected:**
- Wizard submits, upload progress shows, then navigates to `/projects/:id`
- Project detail shows: "● Generating your training module..." with a note about 60s
- After 20–60 seconds the page updates automatically (polling)
- Status changes to the generated module summary:
  - Module title shown
  - Number of screens listed (≥ 10)
  - Learning objectives listed

---

## TC-02 — Full happy path: URL source

**Steps:**
1. Create a new project using "Paste URL" as the source
2. Enter a public URL with substantial text content

**Expected:**
- Same outcome as TC-01 — generation completes and module is shown

---

## TC-03 — Generation without a source is rejected

**Steps:**
```bash
curl -X POST http://127.0.0.1:8000/api/projects/{id}/module/generate \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"duration_minutes": 20, "assessment_count": 5}'
```
(Use a project that has no source attached)

**Expected:**
- HTTP 422: `"Upload a content source before generating."`

---

## TC-04 — Polling stops when status is no longer "generating"

**Steps:**
1. Start generation (TC-01)
2. Open browser DevTools → Network tab
3. Watch requests to `GET /api/projects/:id/module`

**Expected:**
- Requests fire every ~3 seconds while status is `generating`
- Requests stop once status becomes `generated` or `failed`

---

## TC-05 — Failed generation shows error with retry button

**Steps:**
1. Set `ANTHROPIC_API_KEY` to an invalid value in `.env`
2. Run `php artisan config:clear`
3. Create a new project and attempt generation

**Expected:**
- After a short wait, module status shows as failed
- Error message is displayed (e.g. authentication error)
- "Retry generation" button is visible

---

## TC-06 — Retry generation works after failure

**Steps:**
1. After a failed generation (TC-05), restore the correct API key
2. Run `php artisan config:clear`
3. Click "Retry generation"

**Expected:**
- Status returns to "Generating..."
- After completion, module is shown with screens and objectives

---

## TC-07 — Generated module meets minimum schema requirements

**Steps:**
1. Complete a successful generation (TC-01)
2. Inspect the module via Tinker:
```bash
php artisan tinker
$m = App\Models\Module::where('status', 'generated')->latest()->first();
echo count($m->module_json['screens']);          // ≥ 10
echo $m->module_json['schema_version'];          // "1.0"
echo count($m->module_json['learning_objectives']); // ≥ 1
echo $m->module_json['meta']['duration_minutes'];
```

**Expected:**
- `screens` count ≥ 10
- `schema_version` = "1.0"
- `learning_objectives` has at least 1 item
- `duration_minutes` matches what was selected in the wizard

---

## TC-08 — Generated module uses a variety of screen types

**Steps:**
1. After a successful generation, inspect screen types:
```bash
php artisan tinker
$m = App\Models\Module::where('status', 'generated')->latest()->first();
collect($m->module_json['screens'])->pluck('type')->countBy();
```

**Expected:**
- Multiple screen types present (text, image_text, accordion, click_reveal, question)
- No type should account for all screens

---

## TC-09 — Generating again resets a previously generated module

**Steps:**
1. Complete a successful generation
2. Via API, trigger generation again:
```bash
curl -X POST http://127.0.0.1:8000/api/projects/{id}/module/generate \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"duration_minutes": 10, "assessment_count": 3}'
```
3. Poll `GET /api/projects/{id}/module`

**Expected:**
- Status immediately returns to `generating`
- `module_json` is null during generation
- After completion, new module is shown

---

## TC-10 — Generation is scoped to the project owner

**Steps:**
1. Get a project ID from user A
2. Attempt to generate using user B's token:
```bash
curl -X POST http://127.0.0.1:8000/api/projects/{user_a_project_id}/module/generate \
  -H "Authorization: Bearer <user_b_token>" \
  -H "Content-Type: application/json" \
  -d '{"duration_minutes": 20}'
```

**Expected:**
- HTTP 404

---

## TC-11 — Queue worker must be running for generation to complete

**Steps:**
1. Stop the queue worker (run only `php artisan serve`, not `composer dev`)
2. Trigger generation via the wizard
3. Check module status

**Expected:**
- Status stays as `generating` indefinitely (job is queued but not processed)
- Starting `php artisan queue:listen --tries=1` causes the job to run and status to update

---

## Notes

- Generation takes 20–90 seconds depending on source length and Claude response time
- Use `ANTHROPIC_MODEL=claude-haiku-4-5-20251001` in `.env` for faster, cheaper testing iterations
- Raw Claude responses and errors are logged to `storage/logs/laravel.log`
