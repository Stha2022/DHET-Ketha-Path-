# Khetha Path — GovTech 2026 Phase 3 MVP

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


## Animated startup
The welcome experience now opens with the supplied Khetha logo as an animated startup screen.
The learner taps **Start My Journey** to enter the main Khetha Path system.

## Mobile app
This build is PWA-ready and includes a Capacitor starter configuration. The PWA can be installed
from supported browsers. For an Android APK, the web frontend can be packaged with Capacitor;
the PHP/MySQL backend remains server-side and the mobile app uses local storage/cache for offline
journey features.
