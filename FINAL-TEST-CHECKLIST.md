# Khetha Path — GovTech 2026 Final Test Checklist

## 0. Database first

1. Start **Apache** and **MySQL** in XAMPP.
2. Open phpMyAdmin.
3. Open **SQL**.
4. Paste/import `database/khetha_path.sql`.
5. Run it once. The script creates `khetha_path` and all tables, including reference data.
6. In the app, use the local XAMPP URL, for example `http://localhost/DHET-Ketha-Path--main/` after placing the folder in `htdocs`.

## 1. Real account

- Register with a new email.
- Confirm the row appears in `khetha_path.users`.
- Confirm `passwordHash` is populated and is not the plain password.
- Confirm `learner_profiles` contains the learner's profile JSON.
- Log out.
- Log back in with the same email/password.
- Change name in Account & Security; refresh and confirm it persists.
- Change password; sign out; confirm the old password fails and the new password works.

## 2. Personalised journey

- Complete Subject Chooser.
- Reload the page.
- Confirm the answers are still there.
- Complete Career Choice.
- Confirm the result survives logout/login.
- Complete Job Fit.
- Open My Path and confirm the profile drives the journey.
- Check `assessmentResults` and `learner_content_interactions` in phpMyAdmin.

## 3. Notifications

- Open Notifications.
- Confirm the seeded journey reminders appear.
- Click **Mark all as read**.
- Confirm the unread count/bell badge changes.
- Open Settings → Notifications.
- Enable browser/device notifications where supported.
- Click **Send a test notification**.
- Change notification preferences and click **Save notification preferences**.
- Refresh and confirm the preferences remain.
- On the Capacitor build, tap **Enable device reminders** and confirm native permission is requested.

## 4. NCAP alignment

- Open the NCAP alignment page from the dashboard.
- Confirm the six familiar areas are visible: Questionnaires, Subject Chooser, Careers, What to Study, Where to Study and Contact Us.
- Open the official NCAP reference link.
- Do not describe local prototype data as official NCAP data in the demo.

## 5. Directories

- Careers: search by title/alternate title and filter by field.
- What to Study: search qualifications and filter by field.
- Where to Study: search provider names and filter by provider type/province.
- Save a career, qualification and provider.
- Open Favourites and confirm all three appear.
- Remove each favourite and confirm it disappears.

## 6. Career Advisor

- Submit a Subject Choice / Career Decision / Job Fit request.
- Confirm the request is saved in `advisor_requests`.
- Confirm a notification is created.
- Confirm the recent request history appears.
- Verify the official Khetha contact links work.

## 7. Accessibility

- Navigate forms with keyboard only.
- Confirm visible focus outlines.
- Confirm every important form control has a label.
- Test at 200% browser zoom.
- Test a narrow phone viewport.
- Enable `prefers-reduced-motion` and confirm the greeting animation is reduced/hidden.
- Test with a screen reader if available.
- Test English, isiXhosa and isiZulu language switching.

## 8. Offline / low data

- From a connected session, open Settings → Data & offline → Save pages for offline.
- Turn off network.
- Open Dashboard, My Path, directories and saved pages.
- Confirm the offline page appears when a page is not cached.
- Turn network back on and confirm normal loading resumes.
- Log out and confirm previously cached personalised pages are cleared.

## 9. Native mobile

- `cd mobile-app`
- `npm install`
- Set `KHETHA_BACKEND_URL` to the deployed HTTPS backend.
- `npx cap add android` if needed.
- `npm run sync`
- Open Android Studio with `npx cap open android`.
- Build/run the debug APK.
- Test sign-in, directories, favourites, My Path and notifications.

## 10. Judge demo path

**Register → Dashboard → Subject Chooser → Career Choice → Careers → Save a career → My Path → What If? → Notifications → Career Advisor → offline → back online.**

The key line to explain:

> **NCAP is the trusted information foundation. Khetha Path adds the personal journey layer — remembering the learner's context, connecting career → qualification → provider → next step, and keeping that journey available through mobile engagement, offline access and human support.**
