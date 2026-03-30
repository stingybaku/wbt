# Phase 7 — Unsplash Image Picker: Test Cases

## Setup

```bash
# Backend (from /backend)
composer dev

# Frontend (from /frontend)
npm run dev
```

Add your Unsplash Access Key to `/backend/.env`:
```
UNSPLASH_ACCESS_KEY=your_key_here
```
Then run `php artisan config:clear`.

Log in with `demo@wbtforge.test` / `password`.
Have a generated module with at least one `image_text` screen ready.

---

## TC-01 — "Change image" button appears on image_text screens

**Steps:**
1. Open the review editor (`/projects/:id/review`)
2. Click an `image_text` screen in the sidebar

**Expected:**
- Image section is visible in the editor panel
- Current image thumbnail is shown (if one was generated)
- "Change image" button is visible below the thumbnail
- Alt text input is shown below the button

---

## TC-02 — "Add image" button when image_text screen has no image

**Steps:**
1. If any `image_text` screen has `image: null` (bad AI output), select it

**Expected:**
- No thumbnail shown
- Button reads "Add image" instead of "Change image"

---

## TC-03 — Picker opens on button click

**Steps:**
1. On an `image_text` screen, click "Change image"

**Expected:**
- Modal overlay appears with a dark backdrop
- Modal contains: title "Search images", search input, and placeholder text "Type to search for images"
- Search input is auto-focused

---

## TC-04 — Picker closes on Escape key

**Steps:**
1. With the picker open, press Escape

**Expected:**
- Modal closes, editor is visible again

---

## TC-05 — Picker closes on backdrop click

**Steps:**
1. With the picker open, click the dark backdrop outside the modal

**Expected:**
- Modal closes

---

## TC-06 — Search returns results

**Steps:**
1. Open the picker
2. Type a search query (e.g. "office")

**Expected:**
- After a short debounce (~400ms), results appear in a grid
- Images show as thumbnails with photographer name overlaid
- 12 results shown per page
- "Page X of Y" shown if multiple pages exist

---

## TC-07 — Pagination works

**Steps:**
1. Search for a common term (e.g. "nature") that returns many pages
2. Click "Next →"

**Expected:**
- Page 2 results load
- "← Prev" button is now enabled
- Page counter updates to "Page 2 of Y"
3. Click "← Prev"
- Returns to page 1, "← Prev" becomes disabled

---

## TC-08 — Query change resets to page 1

**Steps:**
1. Navigate to page 3 of results
2. Change the search query

**Expected:**
- Page resets to 1 with new results

---

## TC-09 — Selecting an image updates the screen

**Steps:**
1. Open the picker on an `image_text` screen
2. Click any photo in the results grid

**Expected:**
- Picker closes automatically
- The selected image thumbnail appears in the editor
- Alt text input is pre-filled with the photo's description from Unsplash (may be empty if unavailable)
- "Save changes" button becomes enabled (dirty state)

---

## TC-10 — Attribution is stored in module JSON

**Steps:**
1. Select an image (TC-09) and save
2. Verify via Tinker:
```bash
php artisan tinker
$m = App\Models\Module::latest()->first();
$m->module_json['attribution']['unsplash'];
```

**Expected:**
- At least one attribution entry with:
  - `image_id`: the Unsplash photo ID
  - `photographer`: photographer's name
  - `url`: link to the Unsplash photo page
  - `license`: "Unsplash"

---

## TC-11 — Replacing an image updates attribution (no duplicates)

**Steps:**
1. Pick one image for a screen and save
2. Open the picker again for the same screen
3. Pick a different image and save
4. Check `attribution.unsplash` via Tinker

**Expected:**
- Only one attribution entry per `image_id` (no duplicates)
- If the new image is different from the old one, the new `image_id` is present

---

## TC-12 — Attribution appears in the player

**Steps:**
1. After selecting an Unsplash image and saving, open the player (`/projects/:id/play`)
2. Navigate to the `image_text` screen

**Expected:**
- Image is displayed
- "Photo by [photographer name] on Unsplash" attribution appears below the image as a caption

---

## TC-13 — Non-image_text screens do not show the image picker

**Steps:**
1. Select a `text`, `accordion`, `click_reveal`, or `question` screen in the editor

**Expected:**
- No "Change image" or "Add image" button is shown
- No image section in the editor panel

---

## TC-14 — API key not configured returns an error

**Steps:**
1. Remove or blank `UNSPLASH_ACCESS_KEY` from `.env`, run `php artisan config:clear`
2. Open the picker and search for something

**Expected:**
- Error message shown in the picker ("Search failed" or similar)
- No crash

---

## TC-15 — Search API is proxied through the backend

**Steps:**
1. Open browser DevTools → Network tab
2. Perform a search in the picker

**Expected:**
- The request goes to `GET http://127.0.0.1:8000/api/unsplash/search?q=...`
- No direct calls to `api.unsplash.com` from the browser (key is protected)
