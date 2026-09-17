# Khetha Path — Mobile App Plan

## Recommended architecture

### Phase A — Free installable app now
Use the existing web app as a **Progressive Web App (PWA)**.

Users can:
- Install Khetha Path from a supported browser.
- Launch it from the phone home screen like an app.
- Use cached journey content offline.
- Reconnect and sync when internet returns.

This is the fastest route for the hackathon and does not require a paid app-store account.

### Phase B — Downloadable Android APK
Wrap the mobile web frontend with **Capacitor**.

Important: the current PHP/MySQL backend remains server-side. For true offline functionality, the mobile frontend must store the learner journey locally and sync to the server when connectivity returns.

Suggested production architecture:

Phone:
  Khetha Path mobile UI
  + local encrypted storage
  + offline NCAP content cache
  + pathway engine
        |
        | when online
        v
Secure API
        |
        v
DHET/NCAP approved data + PHP/MySQL services

The app should NOT attempt to run PHP/MySQL locally on the phone.

### Phase C — Production integration
- Secure authentication
- Consent management
- Approved NCAP/DHET API integration
- Versioned/synchronised career, qualification and provider data
- Push notifications
- Favourites
- Human career-practitioner contact
- Accessibility and multilingual support
- Audit logs and responsible-AI controls

## Cost
The software stack can be free/open-source. Direct APK distribution can be free; publishing through an app store may have a platform developer-account fee. Hosting/API infrastructure may still have costs.

## Hackathon message
We are not proposing a website inside a phone.

We are proposing a mobile-first career journey that can:
**Discover → Decide → Adapt → Do**, even when connectivity is unreliable.

## Important data note
The demo's pathway examples are prototype data. Production recommendations must be grounded in approved NCAP/DHET data and should not invent career, qualification or provider information.
