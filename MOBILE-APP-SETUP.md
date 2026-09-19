# Khetha Path — Mobile App Setup

The `mobile-app/` directory is a Capacitor 8 native shell. It is intentionally not an APK yet.

## Important architecture

**Android/iOS app → HTTPS PHP API/pages → MySQL**

The phone must not connect directly to MySQL.

## Build prerequisites

- Node.js
- Android Studio + Android SDK for Android builds
- A deployed HTTPS Khetha backend

## Configure backend

Set `KHETHA_BACKEND_URL` before `npm run sync`. Example:

```text
KHETHA_BACKEND_URL=https://your-khetha-domain.example
```

Then:

```text
cd mobile-app
npm install
npx cap add android
npm run sync
npx cap open android
```

For local XAMPP testing, an Android emulator can reach the host machine through `10.0.2.2`, but a physical phone needs the computer's LAN address and an appropriate HTTPS setup. Use the deployed HTTPS URL for the final demo.

Native notification support is documented in `NATIVE-NOTIFICATIONS.md`.
