# Phase 5 — WBT Player: Test Cases

## Setup

```bash
# Backend (from /backend)
composer dev

# Frontend (from /frontend)
npm run dev
```

Log in with `demo@wbtforge.test` / `password`.
Have at least one project with a generated module ready (Phase 3 TC-01).

---

## TC-01 — "Preview" button appears on the project detail page

**Steps:**
1. Navigate to a project with a generated module (`/projects/:id`)
2. Observe the Training Module card

**Expected:**
- "Review module" and "Preview" buttons are both visible
- Clicking "Preview" navigates to `/projects/:id/play`

---

## TC-02 — Cover screen is shown on player load

**Steps:**
1. Click "Preview" from the project detail page

**Expected:**
- Cover screen is shown (not a training screen)
- Module title is displayed
- Audience and duration metadata shown (e.g. "New hires · 20 min")
- Learning objectives listed
- "Start training →" button visible
- "← Back to project" link visible

---

## TC-03 — Start training navigates to first screen

**Steps:**
1. On the cover screen, click "Start training →"

**Expected:**
- Screen 1 of N is shown
- Top bar shows module title and progress ("1 / N")
- Red progress bar at top shows ~1/N fill
- "← Previous" button is disabled (first screen)
- "Next →" button is enabled

---

## TC-04 — Forward and backward navigation

**Steps:**
1. Start training and navigate through several screens using "Next →"
2. Then click "← Previous" to go back

**Expected:**
- Progress indicator updates on each navigation ("2 / N", "3 / N", etc.)
- Progress bar fill updates accordingly
- "← Previous" is disabled only on screen 1
- "Next →" changes to "Finish ✓" on the last screen

---

## TC-05 — Text screen renders correctly

**Steps:**
1. Navigate to a screen of type `text`

**Expected:**
- Screen title shown as a large heading
- Body text shown below
- No image, no interactive elements

---

## TC-06 — Image + text screen renders correctly

**Steps:**
1. Navigate to a screen of type `image_text`

**Expected:**
- Image displayed with rounded corners
- Alt text is applied to the `<img>` element (inspect via DevTools)
- If Unsplash attribution exists: "Photo by [name] on Unsplash" shown below the image
- Body text shown below the image

---

## TC-07 — Accordion screen: items expand and collapse

**Steps:**
1. Navigate to a screen of type `accordion`
2. Click one accordion item title to expand it
3. Click it again to collapse
4. Expand multiple items simultaneously

**Expected:**
- Items are collapsed by default
- Clicking a title toggles the body content
- "+" icon changes to "−" when expanded
- `aria-expanded` attribute on trigger button reflects state
- Multiple items can be open at the same time

---

## TC-08 — Click-reveal screen: items reveal on click

**Steps:**
1. Navigate to a screen of type `click_reveal`
2. Click an item to reveal its content

**Expected:**
- Items show "Tap to reveal →" hint before clicking
- Clicking reveals the body text
- Once revealed, the item cannot be re-hidden
- "Tap to reveal →" hint disappears after revealing

---

## TC-09 — Question screen (single choice): blocked until answered

**Steps:**
1. Navigate to a screen of type `question` with `single_choice` interaction
2. Observe the "Next →" button state
3. Do NOT select any answer

**Expected:**
- "Next →" button is disabled
- "Check answer" button is disabled (no selection yet)

---

## TC-10 — Question screen (single choice): correct answer flow

**Steps:**
1. On a single-choice question screen
2. Select the correct answer (radio button)
3. Click "Check answer"

**Expected:**
- Correct choice is highlighted green
- Green feedback banner shown with the "correct" feedback text
- "Next →" button becomes enabled
- Choices are disabled (cannot change after submitting)
- Focus moves to the feedback message

---

## TC-11 — Question screen (single choice): incorrect answer flow

**Steps:**
1. On a single-choice question screen
2. Select an incorrect answer
3. Click "Check answer"

**Expected:**
- Selected wrong choice highlighted red
- Correct choice(s) highlighted green
- Red feedback banner shown with the "incorrect" feedback text
- "Next →" still becomes enabled (learner can continue)

---

## TC-12 — Question screen (multiple choice)

**Steps:**
1. Find a question screen with `multi_choice` interaction
2. Check multiple checkboxes
3. Submit

**Expected:**
- Multiple choices can be selected simultaneously (checkboxes, not radio)
- After submit: correct choices green, incorrect selections red
- Feedback shown

---

## TC-13 — Question state resets between screens

**Steps:**
1. Answer a question screen and note the feedback
2. Navigate to the next screen and back to the question screen

**Expected:**
- Question resets — choices unselected, no feedback shown, Next disabled again
  (Each screen is freshly mounted when navigated to)

---

## TC-14 — Finish on last screen → completion screen

**Steps:**
1. Navigate to the last screen
2. Click "Finish ✓"

**Expected:**
- Completion screen shown with:
  - Green checkmark icon
  - "Training complete" heading
  - Module title shown
  - "Back to project" button navigates to `/projects/:id`

---

## TC-15 — Back link on cover screen

**Steps:**
1. On the player cover screen, click "← Back to project"

**Expected:**
- Navigated back to `/projects/:id`
- No data loss (module status unchanged)

---

## TC-16 — Accessibility: keyboard navigation

**Steps:**
1. Start the player and navigate using Tab and Enter keys only
2. Press Tab to reach "Next →" and Enter to activate it
3. On an accordion screen, Tab to an item and press Enter to expand

**Expected:**
- All interactive elements are reachable via Tab
- Enter activates buttons
- Screen title receives focus on each new screen (verify via browser focus indicator)
- `aria-expanded` reflects accordion/reveal state correctly

---

## TC-17 — Player URL requires authentication

**Steps:**
1. Copy the player URL (`http://localhost:5173/projects/:id/play`)
2. Log out
3. Paste the URL directly

**Expected:**
- Redirected to `/login`
