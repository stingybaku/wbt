# Phase 4 — Review Editor: Test Cases

## Setup

```bash
# Backend (from /backend)
composer dev

# Frontend (from /frontend)
npm run dev
```

Log in with `demo@wbtforge.test` / `password`.
Have at least one project with a successfully generated module ready (run through Phase 3 TC-01 if needed).

---

## TC-01 — "Review module" button appears after generation

**Steps:**
1. Navigate to a project that has a generated module (`/projects/:id`)
2. Observe the Training Module card

**Expected:**
- "Review module" button is visible
- Module title and screen count are shown
- If there are open flags, a count is shown next to the button

---

## TC-02 — Navigating to the review editor

**Steps:**
1. Click "Review module" on a generated project

**Expected:**
- Navigated to `/projects/:id/review`
- Full-page editor is shown with:
  - Top bar: back link, module title, Save button (disabled/grey)
  - Left sidebar: "Module Info" item + numbered screen list
  - Right panel: Module Info editor

---

## TC-03 — Module Info editing

**Steps:**
1. Open the review editor (TC-02)
2. "Module Info" is selected by default in the sidebar
3. Edit the title, audience, and one learning objective
4. Click "Save changes"

**Expected:**
- "Save changes" button becomes enabled as soon as any field is changed
- After save: button shows "Saved" and becomes disabled again
- Reload the page — the edited values persist (fetched from the backend)

---

## TC-04 — Screen navigation

**Steps:**
1. Open the review editor
2. Click several screens in the sidebar

**Expected:**
- The right panel updates to show the selected screen's fields
- The active screen is highlighted in the sidebar
- Screen number, ID, and type badge are shown in the panel header

---

## TC-05 — Editing a text screen

**Steps:**
1. Click a screen of type `text` in the sidebar
2. Edit the title and body fields
3. Save

**Expected:**
- Changes are reflected immediately in the sidebar (title updates)
- After save, reload and verify the edits persisted

---

## TC-06 — Editing a question screen (single choice)

**Steps:**
1. Find a `question` screen in the sidebar and select it
2. Edit the prompt
3. Change the text of one choice
4. Change which choice is marked correct (radio button)
5. Edit the correct/incorrect feedback
6. Save

**Expected:**
- Only one radio button can be selected at a time
- After save, verify via Tinker:
  ```bash
  php artisan tinker
  $m = App\Models\Module::latest()->first();
  collect($m->module_json['screens'])->where('type', 'question')->first()['interactions'][0]
  ```
  - Shows updated prompt, choice text, correct flag, and feedback

---

## TC-07 — Editing a question screen (multiple choice)

**Steps:**
1. Find a `question` screen with `multi_choice` interaction
2. Toggle multiple "Correct" checkboxes
3. Save

**Expected:**
- Multiple choices can be checked simultaneously
- Correct flags saved as expected in the JSON

---

## TC-08 — Editing an accordion or click_reveal screen

**Steps:**
1. Select an `accordion` or `click_reveal` screen
2. Edit the title and body of one of the items
3. Save

**Expected:**
- Item fields update correctly
- Persisted after reload

---

## TC-09 — Editing an image_text screen

**Steps:**
1. Select an `image_text` screen (if one exists)
2. Edit the image alt text field
3. Save

**Expected:**
- Image thumbnail preview is shown above the alt text input
- Alt text change persists after save

---

## TC-10 — Adding a review flag to a screen

**Steps:**
1. Select any screen in the editor
2. Scroll to the "Review flags" section
3. Enter a reason (e.g. "Inaccurate statistic") and optional notes
4. Click "Add flag"

**Expected:**
- Flag appears in the list above the form with status "OPEN"
- Flag count badge appears on the screen's sidebar item
- Open flag count in the top bar increments
- "Save changes" becomes enabled — save and verify the flag is in `review_flags` via Tinker

---

## TC-11 — Resolving a review flag

**Steps:**
1. With an open flag on a screen (TC-10), click "Resolve" next to it

**Expected:**
- Flag status changes to "RESOLVED" and the row is greyed out
- Resolve button disappears for that flag
- Open flag count in the top bar decrements
- Save and verify the flag's `status` is `"resolved"` via Tinker

---

## TC-12 — Flag state resets when switching screens

**Steps:**
1. On Screen 1, type a reason in the "Flag reason" input (but don't click Add)
2. Click a different screen in the sidebar

**Expected:**
- The flag reason input is cleared (form resets when switching screens)

---

## TC-13 — Unsaved changes are not lost by switching screens

**Steps:**
1. Edit the title on Screen 3
2. Click Screen 5 in the sidebar
3. Click back to Screen 3

**Expected:**
- The edited title on Screen 3 is still present (draft is held in parent state, not per-screen state)

---

## TC-14 — Save is scoped to the project owner

**Steps:**
1. Get a project ID from user A
2. Attempt to update via API as user B:
```bash
curl -X PUT http://127.0.0.1:8000/api/projects/{user_a_project_id}/module \
  -H "Authorization: Bearer <user_b_token>" \
  -H "Content-Type: application/json" \
  -d '{"module_json": {}}'
```

**Expected:**
- HTTP 404

---

## TC-15 — Back link returns to project detail

**Steps:**
1. In the review editor, click the back link (← Project Title) in the top bar

**Expected:**
- Navigated back to `/projects/:id`
- Project detail page shows the updated module title if it was changed
