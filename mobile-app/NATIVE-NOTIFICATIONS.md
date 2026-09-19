# Native notifications

Khetha Path uses `@capacitor/local-notifications` for device-scheduled reminders. The web app also keeps an account-backed notification centre.

## Android build

1. Install Node.js and Android Studio/Android SDK.
2. From `mobile-app/`, run `npm install`.
3. Set the deployed HTTPS backend, for example:
   - Windows PowerShell: `$env:KHETHA_BACKEND_URL="https://YOUR-DOMAIN"`
   - macOS/Linux: `export KHETHA_BACKEND_URL="https://YOUR-DOMAIN"`
4. Run `npx cap add android` if the `android/` directory does not exist.
5. Run `npm run sync`.
6. Run `npm run android` or open the generated `android/` project in Android Studio.
7. Build the APK from Android Studio.

The mobile app must communicate with the PHP backend over HTTPS. It must **never** connect directly to MySQL.

## Notification flow

- Learner signs in.
- Khetha stores notification/reminder records in MySQL.
- The notification centre displays due reminders.
- The native bridge requests device permission and schedules future `scheduledAt` records locally.
- Production can add a server push provider later without changing the learner-facing notification centre.
