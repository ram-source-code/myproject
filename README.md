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
| Install as an app, works offline for pages already visited | Photos are **watermarked with the office logo** automatically | **Reports** queue + automatic **duplicate-photo detection** |
| **Saved-search alerts**: "notify me" for any filter combination | Upload **videos** recorded on the phone (MP4/WebM/MOV) | |
| Report a listing (sold, wrong price, fake, duplicate…) | | |

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

## Self-contained by design
Everything is served from your own folder: **fonts are self-hosted** (Noto Sans Arabic for Kurdish/Arabic,
Inter for Latin — SIL Open Font License, see `assets/fonts/`), Leaflet and markercluster are vendored in
`assets/vendor/`, videos and photos live in `uploads/`. The Content-Security-Policy only allows `'self'`.

The **one exception is map tiles** (the map background images). Self-hosting street-level tiles for
Kurdistan would take hundreds of MB and the OpenStreetMap tile policy forbids bulk downloads, so tiles come
from `tile.openstreetmap.org` by default. Set `AQARAT_TILE_URL` to your own tile server, or to an empty string
to switch maps off completely (list view, listing pages and forms keep working; the CSP then allows nothing
external at all).

## Saved searches, reports and duplicates
- **Saved-search alerts:** on any results page, "Alert me" stores the current filters for this device
  (up to 10). When a listing is published, every matching device gets one push — devices that already got the
  office-follower push for the same listing are skipped. Managed from the Notifications page.
- **Reports:** visitors can report a listing (sold, wrong price, fake, duplicate, wrong location, offensive,
  other). One report per IP per listing, 5 per hour. Admins keep or unpublish from *Admin → Reports*.
- **Duplicate detection:** every photo gets a 64-bit perceptual hash (dHash, computed with GD on the
  original before watermarking). A new photo within Hamming distance 4 of a photo on another listing — even
  resized or re-compressed — or a listing with the same location, type and price (±5 %) is flagged for the
  admin.

## Videos
MP4, WebM or MOV (as recorded by phones), up to 2 per listing and 60 MB each. Files are validated by MIME
type **and** container magic bytes, stored under random names and streamed by your web server (Apache/nginx
handle the range requests video players need). No ffmpeg or transcoding required. Raise
`upload_max_filesize` / `post_max_size` as in `deploy/php.ini`.

## SEO and sharing
Readable listing URLs (`/property/123-خانووی-٤-ژوور-لە-عەنکاوە`, old links 301-redirect), canonical and
`hreflang` tags (`?lang=ckb|ar|en`), `RealEstateListing` JSON-LD, Open Graph/Twitter tags, city landing pages
(`/city/erbil`), `sitemap.xml`, and an auto-generated **1200×630 share image** per listing (photo, price,
rooms, area, office logo) so links look good in WhatsApp, Telegram and Facebook.
For a sub-folder install, add `Sitemap: https://example.com/aqarat/sitemap.xml` to the robots.txt at your
domain root (crawlers only read robots.txt there). Set `AQARAT_APP_URL` so shared links use your real domain.

## Images (`app/image.php`)
MIME sniffed with `finfo` + `getimagesize`, dimension and pixel limits checked before decoding, EXIF
orientation fixed, resized (1600×1200 max + 640×427 thumbnail; logos 256×256), **re-encoded to WebP**
(strips metadata/GPS and any embedded payload), watermarked with the office logo (`AQARAT_WATERMARK=0` to
disable), saved under random names.

## Database (`app/db.php`)
`journal_mode=WAL`, `synchronous=NORMAL`, `foreign_keys=ON`, `busy_timeout=5000`; versioned migrations
via `PRAGMA user_version`; FTS5 full-text search over title, description, district, address and project.

## Configuration (environment variables)
`AQARAT_APP_URL`, `AQARAT_LANG` (ckb/ar/en), `AQARAT_IQD_PER_USD` (default 1450, used to compare IQD
prices), `AQARAT_PUSH_SUBJECT` (mailto: contact for push services), `AQARAT_REQUIRE_APPROVAL`,
`AQARAT_DEBUG`, `AQARAT_DB_PATH`, `AQARAT_TILE_URL`, `AQARAT_TILE_ATTRIBUTION`, `AQARAT_WATERMARK`.
See `app/config.php`.

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
app/fonts/       TTF fonts used by GD for share images (not web-served)
assets/fonts/    self-hosted web fonts (WOFF2)
deploy/          nginx + php.ini samples
tests/           push_test.php, e2e.py
```
