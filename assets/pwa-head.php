<?php
/**
 * Khetha Path — shared PWA <head> tags. Include inside <head> on every
 * page so the app is installable from any screen and the service worker
 * (registered by assets/js/pwa.js) controls the whole site.
 */
?>
<meta name="theme-color" content="#102a43">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Khetha">
<script src="assets/js/pwa.js" defer></script>
