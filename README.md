# Khetha Path — GovTech 2026 Phase 3 MVP

## ⚠️ You are on `render-demo` — a database-free branch

This branch exists only to deploy a live, clickable demo (e.g. on Render) without needing MySQL set up anywhere. Compared to `main`:

- `register.php` and `login.php` no longer touch a database — a profile lives only in the browser session for that visit. Register with any details, or sign in with any email/password (nothing is checked or stored), and you get a fresh demo session.
- `config/db.php` and `db-test.php` are removed; database access uses `database/db-connection.php`.
- A `Dockerfile` + `render.yaml` are included so Render can build and run this branch directly — see **Deploy on Render** below.

**`main` still has the real MySQL-backed auth** (password hashing, `users`/`assessments`/`journey_events` tables) — that's the version to keep developing against locally with XAMPP. Don't merge `render-demo` back into `main`; it's a one-way deployment branch, cut from `main` and periodically re-cut when `main` moves forward.

### Deploy on Render
1. Push this branch to GitHub (already done if you're reading this on `render-demo`).
2. On [render.com](https://render.com): **New → Web Service**, connect this repo, pick the `render-demo` branch.
3. Render should auto-detect `render.yaml` (runtime: Docker). If asked manually: Environment = **Docker**, leave build/start commands blank (the `Dockerfile` handles it).
4. Deploy. Render provides `$PORT` automatically — the `Dockerfile` listens on it via PHP's built-in server.
5. Share the resulting `*.onrender.com` URL — anyone can register or "sign in" with any details to try the full flow, including the Subject Choice questionnaire.

## Core idea
**NCAP tells you what is possible. Khetha Path helps you navigate how to get there.**

The prototype focuses on **career pathway intelligence** rather than a generic AI career chatbot.

### What makes this version different
1. **AI Companion with context** — Khetha is attached to the learner's journey.
2. **Pathway Adapter / “What If?”** — the learner can change a scenario and see how the route could adapt.
3. **Start Where You Are** — the journey stores grade, subjects and interests.
4. **Next-action journey** — career → qualification → provider → opportunity → next step.
5. **Offline/low-data foundation** — PWA manifest + service worker + network state.
6. **Explainability & governance** — the UI explicitly shows that production recommendations must be grounded in approved NCAP/DHET data, with consent, auditability and human escalation.
7. **Demo governance view** — `admin/index.php`.

## Important
This is a **hackathon prototype**, not an official NCAP replacement. Career names and pathway examples in `data/careers.json` are prototype data and must be replaced/validated against approved NCAP/DHET data for production.

## Run
1. Install XAMPP.
2. Copy this folder to `C:\xampp\htdocs\Khetha-Path-Phase3`.
3. Start Apache.
4. Open:
   `http://localhost/Khetha-Path-Phase3/`
5. Enter a demo learner such as `Lindi`, select Mathematics + IT, and enter an interest such as `building apps`.
6. Explore **My Journey → What If? → Ask Khetha**.
7. Governance demo:
   `http://localhost/Khetha-Path-Phase3/admin/`

## MySQL
The current click-through demo does not require MySQL.
A starter schema is provided in `database/khetha_path.sql` for the next integration phase.

## Demo narrative
**Discover → Decide → Adapt → Do**

- Discover: Khetha learns the learner's starting context.
- Decide: show a career pathway, not just a career label.
- Adapt: use “What If?” to demonstrate alternate routes.
- Do: give one clear next action.

## Suggested judge line
“Most career tools answer: ‘What career suits me?’ Khetha Path asks a more useful question: ‘Given where I am today, what path can I take — and what happens if my situation changes?’”


## Phase 7 — Authentication is the first screen
The app now opens on an authentication gateway using the supplied Khetha logo:
**Create Account** or **Sign In**.
- Create Account → registration + personalisation → dashboard.
- Sign In → returning learner → saved dashboard.
The registration page is the point where personalisation begins.


## Phase 8 — REAL MySQL registration and sign-in

The previous prototype only stored registration data in `$_SESSION`. That is why phpMyAdmin showed an empty `users` table.

This phase fixes that:
- `database/db-connection.php` connects PHP to the `khetha_path` MySQL database.
- Registration inserts the user into `users`.
- Passwords are stored using `password_hash()` — never plain text.
- Subjects and interests are inserted into `assessments`.
- Profile creation/login events are recorded in `journey_events`.
- Login checks the database with `password_verify()`.
- The learner's profile is restored from MySQL after signing in.

### If you already created the old users table

Import `database/migration_existing_users.sql` first. The old schema did not have `email` or `password_hash`, so the application could not authenticate users against it.

### XAMPP test

1. Start **Apache** and **MySQL** in XAMPP.
2. Open phpMyAdmin.
3. Select `khetha_path`.
4. If this is a fresh database, import `database/khetha_path.sql`.
5. If you already have the old tables, import `database/migration_existing_users.sql` instead, then make sure the final `users` table has:
   - `id`
   - `name`
   - `email`
   - `password_hash`
   - `grade`
   - `consent_at`
   - `created_at`
6. Check `database/db-connection.php`. Default XAMPP is normally:
   - host: `127.0.0.1`
   - user: `root`
   - password: blank
7. Open:
   `http://localhost/Khetha-Path/`
8. Click **Create Account**.
9. Register a test learner.
10. Open phpMyAdmin → `khetha_path` → `users` → **Browse**.
11. You should now see the new user row.
12. Open `assessments` to see subjects/interests.
13. Sign out, then sign in using the same email/password.
14. Confirm the dashboard loads the saved profile.

### Important

The current implementation is appropriate for local hackathon testing. Before production/mobile deployment we still need:
- HTTPS
- secure token-based API authentication
- CSRF protection
- rate limiting/login lockout
- password reset
- secure cookies
- server-side validation
- consent records and privacy policy
- encrypted local mobile storage
- approved NCAP/DHET API/data integration
- proper production database credentials stored outside source code

## Subject Choice questionnaire

Implements the Subject Choice decision from NCAP's Self Exploration set, combining the **Interests**, **Career Abilities** and **Employability Skills** questionnaires (each 12 statements, 2 per study field) into ranked study-field suggestions.

- `subject-choice-data.php` — study fields, questionnaire statements and scoring, no page output.
- `subject-chooser.php` — hub (replaces the earlier placeholder): shows progress across the three questionnaires and unlocks the combined report once all are done.
- `questionnaire.php?type=interests|abilities|skills` — the 12-statement Likert form for each questionnaire.
- `questionnaire-report.php?type=...` — per-questionnaire top-3 study fields.
- `subject-choice-report.php` — combined top-3 study fields with suggested subjects and example institutions (mock data, not a live NCAP listing).
- `contact-advisor.php` — mocked "contact a Career Advisor" flow.
- `reset-subject-choice.php` — clears saved answers for all three questionnaires.

All of the above require a signed-in learner (`$_SESSION['user']`, set by `login.php`); progress is stored in `$_SESSION['subject_choice']` only — it is not yet persisted to MySQL.


## Phase 11 visual refresh
The UI now uses a consistent Khetha + Department of Higher Education and Training brand lockup, a colourful mobile-first dashboard, animated micro-interactions, and the local Khetha greeting video. The DHET logo supplied for the hackathon is stored at `assets/images/dhet-official-logo.png`.

## GovTech 2026 final build additions

This build includes a complete importable `database/khetha_path.sql`, persistent MySQL accounts and learner profiles, account-backed notifications/reminders, NCAP alignment mapping, searchable Careers/What to Study/Where to Study directories, persistent favourites, functional advisor-request storage, accessibility enhancements, and Capacitor 8 native local-notification integration.

Prototype content in `occupation-data.php` and the seeded reference tables is explicitly labelled as prototype content. Production should synchronise approved NCAP/DHET data through an authorised integration/API.

See `FINAL-TEST-CHECKLIST.md` for the test sequence.
