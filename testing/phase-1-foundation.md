# Phase 1 — Foundation: Test Cases

## Setup

Start both servers before running these tests:

```bash
# Backend (from /backend)
php artisan serve

# Frontend (from /frontend)
npm run dev
```

Demo credentials: `demo@wbtforge.test` / `password`
(Run `php artisan db:seed` if the demo user doesn't exist yet.)

---

## TC-01 — Login with valid credentials

**Steps:**
1. Open the app at `http://localhost:5173`
2. Enter email `demo@wbtforge.test` and password `password`
3. Click Sign In

**Expected:**
- Redirected to `/projects`
- Projects list is shown (empty if no projects exist)

---

## TC-02 — Login with invalid credentials

**Steps:**
1. Open `http://localhost:5173/login`
2. Enter any email and a wrong password
3. Click Sign In

**Expected:**
- Error message shown ("Invalid credentials" or similar)
- Stays on `/login`

---

## TC-03 — Unauthenticated access is blocked

**Steps:**
1. Clear localStorage (DevTools → Application → Local Storage → clear)
2. Navigate directly to `http://localhost:5173/projects`

**Expected:**
- Redirected to `/login`

---

## TC-04 — Persistent session across page reload

**Steps:**
1. Log in (TC-01)
2. Hard-refresh the page (`Ctrl+Shift+R`)

**Expected:**
- Still on `/projects`, still authenticated — not redirected to login

---

## TC-05 — Logout

**Steps:**
1. Log in (TC-01)
2. Click the Logout button

**Expected:**
- Redirected to `/login`
- Navigating to `/projects` redirects back to `/login`
- The Bearer token is invalidated — confirm via:
  ```bash
  # Copy the token before logging out, then after logout:
  curl -H "Authorization: Bearer <token>" http://127.0.0.1:8000/api/projects
  # Should return 401
  ```

---

## TC-06 — Create a project

**Steps:**
1. Log in (TC-01)
2. Enter a title (e.g. "Safety Induction 2026") in the Create Project form
3. Click Create project

**Expected:**
- New project appears in the Recent Projects list
- Project is linked to the logged-in user (verify via Tinker: `App\Models\Project::latest()->first()->user_id`)

---

## TC-07 — Projects are scoped to the authenticated user

**Steps:**
1. Log in as `demo@wbtforge.test`, create a project
2. Log out
3. Log in as a different user (create one via `php artisan tinker`: `App\Models\User::factory()->create(['email'=>'other@test.com','password'=>'password'])`)
4. Check the projects list

**Expected:**
- The second user sees no projects
- The first user's project is not visible to the second user

---

## TC-08 — Navigate to project detail

**Steps:**
1. Log in and ensure at least one project exists (TC-06)
2. Click the project title link

**Expected:**
- Navigated to `/projects/:id`
- Project title, ID, rights attestation status, and creation date are shown
- "No module yet" message shown under Training Module

---

## TC-09 — Direct URL to project detail requires auth

**Steps:**
1. Copy a project detail URL (e.g. `http://localhost:5173/projects/proj-xxx`)
2. Log out
3. Paste the URL directly into the browser

**Expected:**
- Redirected to `/login`

---

## TC-10 — API rejects requests without a token

**Steps:**
Run the following from a terminal:

```bash
curl -s http://127.0.0.1:8000/api/projects
```

**Expected:**
- HTTP 401 response

---

## TC-11 — Frontend texts still load on login page (no auth required)

**Steps:**
1. Log out so you are on `/login`
2. Check that the login form renders with text (brand name, labels, button text)

**Expected:**
- Login form text is rendered (sourced from the `/api/frontend-texts` endpoint, which is intentionally public)
