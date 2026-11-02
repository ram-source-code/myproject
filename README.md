# عەقارات · Aqarat — Kurdistan real estate

A property marketplace for **Kurdistan** in plain **PHP 8.1+, HTML, CSS and vanilla JS**:
**SQLite (WAL mode)**, a **GD pipeline that converts every upload to WebP**, an **installable PWA**,
and **Web Push notifications** from real-estate offices (نوسینگە) to the people who follow them.
No frameworks, no Composer, no build step.

Languages: **کوردی (Sorani, default)**, العربية and English — RTL/LTR handled automatically.

## Highlights

| For people | For real-estate offices (نوسینگە) | For the site admin |
|---|---|---|
| Search by city, type, price (USD or IQD), rooms, area, **tapo / title-deed** type | Office page with logo, about, phone, WhatsApp, verified badge | Moderation queue (approve / reject / feature) |
| **Map view** with price pins (Leaflet + OpenStreetMap, self-hosted JS) | Publish listings with drag-and-drop photos and a **map pin picker** | Verify offices, manage users and cities (Kurdish/Arabic/English names) |
| Quick-filter chips, list/map toggle, recently viewed | **Notify followers** with a push notification (5/day limit) | **Broadcast** to everyone who enabled announcements |
| **Follow an office with one tap — no account needed** | Followers are notified automatically when a listing goes live | Notification log with delivery counts |
| WhatsApp & call buttons, sticky contact bar on mobile | Enquiries inbox | Database mode / stats overview |
| Install as an app, works offline for pages already visited | | |

## Install in a sub-folder (e.g. `public_html/aqarat/` → `example.com/aqarat/`)

1. Upload the whole folder to `public_html/aqarat/`. `index.php` in that folder is the entry point.
2. Make `data/` and `uploads/` writable by PHP.
3. Initialise the database. If you have SSH:

   ```bash
   cd public_html/aqarat
   ```

   ```bash
   ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='a-strong-password' php bin/seed.php
   ```

   Add `--demo` to load demo offices and listings.

   **No SSH?** Open `https://example.com/aqarat/install` instead: it checks the server requirements,
   adds the Kurdistan cities and creates the admin account. The installer disables itself as soon as
   an admin exists — run it right after uploading.
4. Open `https://example.com/aqarat/`.

The install folder is **auto-detected**, so the same files also work at the domain root or in any other
folder. Set `AQARAT_APP_URL=https://example.com/aqarat` if you sit behind a proxy — it is also used
to build absolute links inside push notifications.

**Apache / cPanel:** the included `.htaccess` files route requests to `index.php` and block access to
`app/`, `data/`, `bin/`, `tests/`, `deploy/`, `*.md` and `*.sqlite`. `uploads/` never executes code.
**nginx:** see `deploy/nginx.conf`. Recommended PHP settings: `deploy/php.ini`.

**HTTPS is required** for the PWA and push notifications (browsers only allow them on secure origins
and on `localhost`).

## Push notifications (Web Push)
- Pure PHP implementation of **VAPID (ES256 JWT)** and **RFC 8291 aes128gcm** payload encryption using
  `openssl` and `curl` — no Composer packages. Keys are generated on first use in `data/vapid.json`
  (outside the web root). Back this file up: changing it invalidates all subscriptions.
- People tap **Follow** on an office; the browser asks for permission and the device subscription is
  stored (no account needed). The **Notifications** page lets them manage follows and announcements.
- Only real push-service hosts (FCM, Mozilla, Apple, WNS) are accepted as endpoints (SSRF protection).
  Expired subscriptions (404/410) are removed automatically.
- iPhone/iPad: notifications work when the site is **added to the Home Screen** (iOS 16.4+); the site
  shows this hint automatically.

## Images (`app/image.php`)
MIME sniffed with `finfo` + `getimagesize`, dimension and pixel limits checked before decoding, EXIF
orientation fixed, resized (1600×1200 max + 640×427 thumbnail; logos 256×256), **re-encoded to WebP**
(strips metadata/GPS and any embedded payload), saved under random names.

## Database (`app/db.php`)
`journal_mode=WAL`, `synchronous=NORMAL`, `foreign_keys=ON`, `busy_timeout=5000`; versioned migrations
via `PRAGMA user_version`; FTS5 full-text search over title, description, district, address and project.

## Configuration (environment variables)
`AQARAT_APP_URL`, `AQARAT_LANG` (ckb/ar/en), `AQARAT_IQD_PER_USD` (default 1450, used to compare IQD
prices), `AQARAT_PUSH_SUBJECT` (mailto: contact for push services), `AQARAT_REQUIRE_APPROVAL`,
`AQARAT_DEBUG`, `AQARAT_DB_PATH`. See `app/config.php`.

## Tests
- `php tests/push_test.php` — Web Push crypto: encrypt/decrypt round-trip, VAPID signature, SSRF guard.
- `tests/e2e.py` — functional + security checks (subfolder routing, languages, offices, follow/push,
  IDOR, CSRF, XSS, uploads, moderation, throttling). Setup instructions at the top of the file.

## Layout
```
index.php        front controller (entry point of the install folder)
sw.js            service worker (offline + push)
.htaccess        routing + protection of internal folders
app/             bootstrap, config, db, helpers, image, push, models, routes, controllers, views, lang
assets/          css, js, img (PWA icons), vendor/leaflet
uploads/         WebP images (generated)
data/            SQLite database + VAPID keys (generated, protected)
bin/             seed.php, make-icons.php
deploy/          nginx + php.ini samples
tests/           push_test.php, e2e.py
```
