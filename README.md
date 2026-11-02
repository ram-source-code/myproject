# عەقارات · Aqarat — Kurdistan real estate

A property marketplace for **Kurdistan** in plain **PHP 8.1+, HTML, CSS and vanilla JS**:
**SQLite (WAL mode)**, a **GD pipeline that converts every upload to WebP**, an **installable PWA**,
and **Web Push notifications** from real-estate offices (نوسینگە) to the people who follow them.

**Only real-estate offices post listings, and only after the admin activates them.** Everyone else browses, saves,
follows offices and contacts them.

**Office activation (paid plans):**
1. An office registers (or any account picks *Dashboard → Open an office*). It is *waiting for activation*: it can't post,
   and it isn't shown publicly. Its page has a WhatsApp button to message the admin.
2. After the office pays, the admin opens **Admin → Offices**, picks the length (1/3/6/12 months or no end date)
   and taps **Generate code**. The code (e.g. `K7QM-3XPA`) is shown once, with **Send on WhatsApp** (a ready message to the
   office's number) and **Copy** buttons.
3. The office enters the code under *My office*. Codes work once, only for that office, and stop working after 14 days.
   A renewal code adds the months to the current end date, so paying early loses nothing.
4. When a plan ends the office can't post or send notifications until it enters a new code; its existing listings stay up.
   **Deactivate** stops an office immediately.

The admin's WhatsApp number comes from the admin's phone in *Profile* (or set `admin_whatsapp` in `data/config.local.php`).
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

## Install (fully automatic)

1. Upload the folder to your hosting, e.g. `public_html/aqarat/` → `https://example.com/aqarat/`.
   `index.php` in that folder is the entry point; any folder name (or the domain root) works.
2. Open the address in your browser. That's it — the app sets itself up:
   - creates the SQLite database (WAL mode) and the push-notification keys **with random file names**,
     and writes them to `data/config.local.php` (no environment variables or SSH needed);
   - recreates any `.htaccess` protection file your FTP client may have skipped;
   - redirects every page to a guided **installer** that checks the server (with plain-language fixes for
     cPanel), asks for language, dinar rate and the admin account, and can **load demo data** with one tick.
3. When the installer finishes you are logged in as admin. The installer disables itself permanently.

If `data/` or `uploads/` are not writable, the first page tells you exactly which permission to set (755).
Command-line alternative: `php bin/seed.php [--demo]` (admin from `ADMIN_EMAIL` / `ADMIN_PASSWORD`).

### Local testing with XAMPP (Windows / macOS / Linux)
1. Install [XAMPP](https://www.apachefriends.org) with **PHP 8.1 or newer** and start **Apache** in the XAMPP Control Panel
   (MySQL is not needed — the app uses SQLite).
2. Copy the project folder to `C:\xampp\htdocs\aqarat` (Windows) or `/Applications/XAMPP/htdocs/aqarat` (macOS).
   Make sure the hidden `.htaccess` files are copied too (the app recreates missing ones automatically).
3. Open **http://localhost/aqarat/** — the installer appears. Tick *Add demo data* to get offices and listings.
   If a PHP extension is missing, the installer shows the exact `php.ini` path and the line to change
   (XAMPP ships with GD, SQLite, cURL, OpenSSL, fileinfo and mbstring enabled).
4. Optional, for phone videos: in `php.ini` set `upload_max_filesize = 64M` and `post_max_size = 160M`, then
   Stop/Start Apache.

Notes for local testing:
- `http://localhost` counts as a secure origin, so the **PWA, service worker and push notifications work locally**
  in Chrome/Edge/Firefox on the same computer. A phone opening `http://192.168.x.x/aqarat` is *not* a secure
  origin: browsing works, but install/push need HTTPS (use XAMPP's `https://` with a trusted certificate, or test
  push on the desktop browser).
- Windows XAMPP usually has no CA bundle for PHP's cURL; the app uses its bundled Mozilla list
  (`app/certs/cacert.pem`) automatically, and a bundled `openssl.cnf` so push keys can be generated.
- macOS XAMPP: if the first page says `data` is not writable, run `chmod -R 777 data uploads` in the app folder.
- Tested with Apache 2.4 + mod_php using XAMPP's defaults (`AllowOverride All`, mod_rewrite, mod_headers):
  routing, folder protection, upload sandboxing, compression, caching and video range requests all work.

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

## Design, motion & performance (2026)
- **Instant navigation:** Speculation Rules prefetch likely next pages on hover/touch and prerender listing,
  office and city pages (prerenders never inflate view counts — the page reports the view only when shown).
- **Smooth page changes:** cross-document **View Transitions**; the card photo morphs into the listing hero and
  back. Everything respects `prefers-reduced-motion`.
- **No layout shift:** `font-size-adjust: from-font`, reserved image sizes, `rel=expect` render blocking until
  `<main>` is parsed, and the photo's **dominant colour** (computed at upload) as an instant placeholder.
- **Less work:** `content-visibility: auto` with intrinsic sizes for off-screen sections, compositor-only
  scroll-driven reveal animations, brotli/gzip and 1-year immutable caching for hashed assets.
- **Phone-first:** swipeable scroll-snap photo strip, bottom-sheet filters with a live "Show N results" count,
  app-style bottom navigation, sticky WhatsApp/call bar, spring micro-interactions, smooth `<details>`.
- Measured on a throttled mid-range phone (4× CPU, 9 Mbps/80 ms): **LCP ≈ 1.0 s, CLS 0.000** on home,
  search and listing pages.

## iPhone & iPad
- **iPhone:** 16 px form fields (no Safari focus-zoom), notch/Dynamic-Island safe areas in landscape and in the
  installed app, hover effects only for real pointers (no "sticky hover" after a tap), fixed bars hide while the
  keyboard is open, native swipe-back is not doubled by our page transition, launch screens for current iPhones.
- **Installed app (Home Screen):** a back button appears in the header (standalone apps have no browser chrome).
- **Push on iOS:** Apple only allows web push for Home-Screen apps (iOS 16.4+). Tapping Follow / Alert me in
  Safari opens a 3-step "Add to Home Screen" guide instead of failing.
- **iPad / tablets:** header navigation instead of the phone tab bar, 3-column listing grid, sheet filters in
  portrait and a filter sidebar in landscape, two-column listing page with a sticky contact card from 860 px,
  grid gallery (the swipe strip is phone-only), and the app may rotate freely (`orientation: any`).

## Icons
All icons are **self-hosted SVG**: [Lucide](https://lucide.dev) (ISC) plus the WhatsApp/Telegram marks from
Simple Icons (CC0). The full packs live in `bin/icon-pack/` (not web-served); `php bin/build-icons.php` builds
an inline sprite of only the icons in use (`app/views/partials/icons.php`, ~16 KB) and the few CSS mask icons
(`assets/icons/`). No icon fonts, no emoji or text glyphs as icons.

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
bin/             seed.php, make-icons.php, build-icons.php, icon-pack/ (Lucide + Simple Icons)
app/fonts/       TTF fonts used by GD for share images (not web-served)
assets/fonts/    self-hosted web fonts (WOFF2)
deploy/          nginx + php.ini samples
tests/           push_test.php, e2e.py
```
