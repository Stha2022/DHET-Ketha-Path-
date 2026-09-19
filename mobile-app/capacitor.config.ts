import type { CapacitorConfig } from '@capacitor/cli';

// For the hackathon, point this at the deployed HTTPS Khetha backend before
// building the APK. Do not point a release build directly at MySQL.
const backendUrl = process.env.KHETHA_BACKEND_URL || '';

const config: CapacitorConfig = {
  appId: 'za.pamsitha.khethapath',
  appName: 'Khetha Path',
  webDir: 'www',
  android: { allowMixedContent: false },
  ...(backendUrl ? { server: { url: backendUrl, cleartext: false } } : {})
};

export default config;
