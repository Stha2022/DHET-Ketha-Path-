# Khetha Path API — planned mobile layer

The native app should communicate with PHP over HTTPS rather than directly with MySQL.

Planned endpoints:

POST /api/register.php
POST /api/login.php
GET  /api/me.php
GET  /api/journey.php
POST /api/journey-event.php
GET  /api/directories.php
POST /api/favourites.php
GET  /api/notifications.php

All endpoints must use prepared statements, validate input server-side, enforce authentication/authorization and avoid exposing database credentials.
