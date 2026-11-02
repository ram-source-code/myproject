# عقارات · Aqarat

A real-estate marketplace web app (sale and rent) in plain **PHP 8.1+, HTML, CSS and vanilla JS**, with **SQLite in WAL mode** and a **GD image pipeline that converts every upload to WebP**. It needs no frameworks and no Composer.

Arabic (RTL) is the default language, and English (LTR) is one click away. It supports light and dark mode.

## Features

**Visitors**
- Home page with hero search, live stats, browsing by property type, and featured and latest listings
- Search page:
  - Full-text keyword search (SQLite FTS5, Arabic-aware)
  - Filters: purpose, type, city, price range, bedrooms, bathrooms, minimum area, furnished
  - Five sort orders and pagination
- Property page:
  - Photo gallery with a full-screen lightbox (keyboard, swipe, RTL-aware arrows)
  - Facts, features, description, OpenStreetMap link and similar listings
- Contact form with a honeypot field and per-IP rate limiting
- Click-to-call button for the advertiser

**Registered users / agents**
- Create, edit and delete listings, and mark them sold or rented
- Upload multiple photos by drag-and-drop with previews, then set a cover photo or delete photos
- Favourites (saved without a page reload), an enquiries inbox with read/unread state, and profile and password settings

**Admins**
- Overview stats and a moderation queue: approve, reject or feature listings
- Manage users (role, suspend) and cities

## Image pipeline (`app/image.php`)
1. Checks the PHP upload: error code, `is_uploaded_file` and size (8 MB per file by default).
2. Detects the real MIME type with `finfo`, which must match `getimagesize()`. JPEG, PNG, GIF and WebP are allowed.
3. Rejects images that are too small, or larger than 40 MP (decompression bombs), *before* decoding.
4. Decodes with GD and fixes JPEG EXIF orientation.
5. Resizes the image to fit 1600×1200 and makes a 640×427 cover-cropped thumbnail.
6. **Re-encodes both to WebP.** This strips EXIF/GPS metadata and any payload hidden in the original file, such as a polyglot.
7. Saves the files under random 128-bit names. The uploads folder is configured never to execute code.

## Database (`app/db.php`)
- `PRAGMA journal_mode = WAL`, `synchronous = NORMAL`, `foreign_keys = ON`, `busy_timeout = 5000`
- Migrations are versioned with `PRAGMA user_version` and run automatically on the first request.
- The database file is in `data/`, **outside the web root**.

## Security
- CSRF token on every POST, session cookie set to `HttpOnly` and `SameSite=Lax`, session ID regenerated on login and on password change
- All SQL uses bound parameters; FTS search terms are quoted
- All output is escaped with `e()`
- Strict CSP (`script-src 'self'`, no inline scripts or styles), `X-Frame-Options`, `nosniff`, Referrer and Permissions policies
- Passwords stored with `password_hash`; login throttled per IP and per email; login takes the same time whether or not the email exists
- Every edit, delete and photo action checks that the user owns the listing. Pending and rejected listings return 404 to the public.
- Moderation: new listings, and edits to published ones, by non-admin users go back to review

## Quick start

```bash
php bin/seed.php --demo            # creates DB (WAL), cities, an admin (password printed) + 24 demo listings
```

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Open http://127.0.0.1:8080. To choose the admin credentials yourself, set them when seeding:

```bash
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='a-strong-password' php bin/seed.php
```

**Requirements:** PHP ≥ 8.1 with `pdo_sqlite` (FTS5), `gd` built with WebP support, `fileinfo`; `exif` is optional.

## Deployment
- Point the web server's document root at **`public/`**.
- **Apache:** the included `.htaccess` files handle routing and lock down `uploads/` and `data/`.
- **nginx:** see `deploy/nginx.conf`.
- Recommended PHP settings are in `deploy/php.ini` (`upload_max_filesize`, `post_max_size`, `memory_limit` for GD).
- The PHP user needs write access to `data/` (the database plus its `-wal`/`-shm` files) and `public/uploads/`.
- Configure through environment variables: `AQARAT_APP_URL`, `AQARAT_DEBUG`, `AQARAT_CURRENCY`, `AQARAT_LANG`, `AQARAT_DB_PATH`, `AQARAT_REQUIRE_APPROVAL`. See `app/config.php`.

## Tests
`tests/e2e.py` runs 29 functional and security checks against a running server: CSRF, IDOR, XSS escaping, upload rejection, WebP output, moderation and throttling. The setup steps are at the top of the file.

## Layout
```
app/            bootstrap, config, db (WAL + migrations), helpers, image pipeline, models, routes
app/controllers public / auth / dashboard / admin
app/views       PHP templates (layout, partials, pages)
app/lang        ar.php, en.php
public/         front controller, assets (css/js/img), uploads/
bin/seed.php    initial data + demo listings with generated photos
deploy/         nginx + php.ini samples
```
