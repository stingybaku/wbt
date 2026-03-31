# Phase 2 — Content Input: Test Cases

## Setup

```bash
# Backend (from /backend)
php artisan serve

# Frontend (from /frontend)
npm run dev
```

Log in with `demo@wbtforge.test` / `password`.

Have the following files ready for upload tests:
- A small PDF (any PDF, ideally with readable text)
- A DOCX file
- A PPTX file
- A file that is NOT one of the above (e.g. `.jpg` or `.txt`) — for rejection testing

---

## TC-01 — New Project button navigates to wizard

**Steps:**
1. Log in and go to `/projects`
2. Click "New Project"

**Expected:**
- Navigated to `/projects/new`
- Wizard is shown at Step 1 ("New Project")
- Four steps are visible in the progress indicator

---

## TC-02 — Step 1 blocks progression without title

**Steps:**
1. On Step 1, leave the title blank
2. Click Next

**Expected:**
- Cannot proceed — Next button is disabled

---

## TC-03 — Step 1 blocks progression without rights attestation

**Steps:**
1. Enter a title
2. Leave the rights attestation checkbox unchecked
3. Click Next

**Expected:**
- Next button remains disabled until checkbox is checked

---

## TC-04 — Step 1 proceeds when both fields are complete

**Steps:**
1. Enter a title (e.g. "Fire Safety Training")
2. Check the rights attestation checkbox
3. Click Next

**Expected:**
- Moves to Step 2 ("Content Source")
- Step 1 marked as done in the progress indicator

---

## TC-05 — File upload: drop zone accepts PDF

**Steps:**
1. Complete Step 1 and proceed to Step 2
2. Ensure "Upload file" is selected
3. Click the drop zone and select a PDF file

**Expected:**
- File name and size shown in the drop zone
- Next button becomes enabled

---

## TC-06 — File upload: drag and drop works

**Steps:**
1. On Step 2 with "Upload file" selected
2. Drag a PDF onto the drop zone

**Expected:**
- File name and size shown in the drop zone

---

## TC-07 — Switch to URL input

**Steps:**
1. On Step 2, click "Paste URL"

**Expected:**
- URL input field is shown, drop zone is hidden
- Next button disabled until a URL is entered

---

## TC-08 — Step 3 controls are interactive

**Steps:**
1. Complete Steps 1 and 2, proceed to Step 3
2. Click the "10 min" and "30 min" duration buttons
3. Type learning objectives in the textarea
4. Drag the assessment count slider

**Expected:**
- Duration selection highlights the active option
- Learning objectives textarea accepts text
- Slider updates the count label ("Assessment questions: N")

---

## TC-09 — Step 4 shows correct summary

**Steps:**
1. Complete all three previous steps with:
   - Title: "Fire Safety Training"
   - Source: a PDF file
   - Duration: 30 min
   - Learning objectives: "Understand evacuation routes"
   - Assessment count: 3
2. Click Review

**Expected:**
- Summary shows all five values accurately
- "Generate Training" button is enabled

---

## TC-10 — Full wizard creates a project with extracted source (file)

**Steps:**
1. Complete the full wizard with a PDF file
2. Click "Generate Training"

**Expected:**
- Upload progress bar appears during file upload
- "Processing content…" message shown briefly after 100%
- Navigated to `/projects/:id` (the new project's detail page)
- Detail page shows:
  - Correct project title
  - Source: "Uploaded file"
  - Character count > 0 (e.g. "1,234 characters extracted")
  - "Content ready." message under Training Module

---

## TC-11 — Full wizard creates a project with URL source

**Steps:**
1. Complete the wizard selecting "Paste URL"
2. Enter a valid public URL (e.g. `https://example.com`)
3. Click "Generate Training"

**Expected:**
- Navigated to the project detail page
- Source shows the URL
- Character count shown

---

## TC-12 — Invalid file type is rejected by the server

**Steps:**
1. Bypass the UI by sending a request directly:
```bash
curl -X POST http://127.0.0.1:8000/api/projects/{id}/source \
  -H "Authorization: Bearer <token>" \
  -F "source_type=file" \
  -F "file=@/path/to/image.jpg"
```

**Expected:**
- HTTP 422 with a validation error (unsupported mime type)

---

## TC-13 — File over 10 MB is rejected

**Steps:**
1. Attempt to upload a file larger than 10 MB (via curl or by modifying the UI):
```bash
curl -X POST http://127.0.0.1:8000/api/projects/{id}/source \
  -H "Authorization: Bearer <token>" \
  -F "source_type=file" \
  -F "file=@/path/to/large_file.pdf"
```

**Expected:**
- HTTP 422 with a file size validation error

---

## TC-14 — Unreachable URL returns an error

**Steps:**
1. Submit the wizard with a URL that doesn't exist or returns an error (e.g. `https://thisdomaindoesnotexist12345.com`)

**Expected:**
- Error message shown on Step 4 (does not navigate away)

---

## TC-15 — Long content is truncated at 50,000 characters

**Steps:**
1. Upload a very large PDF (or a URL with a large page)
2. After submission, check in Tinker:
```bash
php artisan tinker
App\Models\Project::latest()->first()->source_character_count
```

**Expected:**
- `source_character_count` is at most 50,000

---

## TC-16 — Source is scoped to the project owner

**Steps:**
1. Get the ID of a project owned by user A
2. Log in as user B and attempt to upload a source:
```bash
curl -X POST http://127.0.0.1:8000/api/projects/{user_a_project_id}/source \
  -H "Authorization: Bearer <user_b_token>" \
  -F "source_type=url" \
  -F "url=https://example.com"
```

**Expected:**
- HTTP 404 (project not found for that user)
