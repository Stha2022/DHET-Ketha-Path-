# Khetha Path — Downloadable Android App

## Architecture

The current Khetha application uses PHP + MySQL. PHP must remain on a server; an Android APK cannot directly run the PHP/MySQL backend.

Production:

Android app (Capacitor)
        ↓ HTTPS
Khetha PHP/API backend
        ↓
MySQL database

The phone must NEVER connect directly to MySQL.

## Why Capacitor?

Capacitor can package a modern web app inside a native Android project while still allowing access to native Android capabilities. This lets us keep the Khetha HTML/CSS/JavaScript experience and add native notifications, secure storage, network detection and other mobile features later.

## Build the Android app

Install Node.js and Android Studio.

From the `mobile-app` folder:

    npm install
    npx cap add android
    npx cap sync
    npx cap open android

Android Studio opens the generated project. You can run it on an Android emulator or a USB-connected Android phone.

For a release build, Android Studio can generate a signed APK or Android App Bundle (AAB).

## IMPORTANT: localhost

Do not use:

    http://localhost/...

inside the installed phone app.

On a phone, localhost means the phone itself.

The Khetha PHP backend needs to be deployed to an HTTPS domain, for example:

    https://your-khetha-domain.example

The app then communicates with that backend through HTTPS APIs.

## Offline plan

We should support two levels:

1. PWA/browser caching for the web version.
2. Native local storage for the installed app.

The installed app should cache safe, non-sensitive journey information and approved NCAP/DHET content for low-connectivity use. Changes made offline can be queued and synchronised when the network returns.

Authentication tokens should use secure native storage, not plain localStorage.

## Next production API

The current PHP pages are browser pages. For the native app, we should expose API endpoints such as:

POST /api/register.php
POST /api/login.php
GET  /api/me.php
GET  /api/journey.php
POST /api/journey-event.php
GET  /api/directories.php
POST /api/favourites.php
GET  /api/notifications.php

The API uses the same MySQL database.

## Hackathon demo strategy

For the hackathon, the strongest path is:

1. Run the PHP version locally for development.
2. Deploy the PHP/MySQL backend to HTTPS.
3. Point the Capacitor Android app at the HTTPS API/backend.
4. Demonstrate the same learner account on phone and browser.
5. Turn off connectivity and demonstrate the cached journey.
6. Restore connectivity and demonstrate synchronisation.
