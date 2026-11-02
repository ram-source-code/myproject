<?php
declare(strict_types=1);

/**
 * SQLite connection (WAL mode) + schema migrations.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = config('db_path');
    $dir  = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create data directory');
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ]);

    // WAL: concurrent readers with a single writer, far fewer "database is locked" errors.
    $mode = $pdo->query('PRAGMA journal_mode = WAL')->fetchColumn();
    if (strtolower((string) $mode) !== 'wal') {
        error_log('aqarat: could not enable WAL mode, got ' . $mode);
    }
    $pdo->exec('PRAGMA synchronous = NORMAL');   // safe with WAL, much faster
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA temp_store = MEMORY');
    $pdo->exec('PRAGMA cache_size = -16000');    // ~16 MB

    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    $migrations = migrations();
    $target = count($migrations);
    if ($version >= $target) {
        return;
    }

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        // Re-check inside the write lock in case another request migrated first.
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        for ($i = $version; $i < $target; $i++) {
            $pdo->exec($migrations[$i]);
        }
        $pdo->exec('PRAGMA user_version = ' . $target);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

/** @return string[] Each entry is one schema version. Append only. */
function migrations(): array
{
    return [
        // v1 — base schema
        <<<SQL
        CREATE TABLE users (
            id            INTEGER PRIMARY KEY,
            name          TEXT    NOT NULL,
            email         TEXT    NOT NULL UNIQUE COLLATE NOCASE,
            phone         TEXT,
            password_hash TEXT    NOT NULL,
            role          TEXT    NOT NULL DEFAULT 'user' CHECK (role IN ('user','agent','admin')),
            status        TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','banned')),
            created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE cities (
            id      INTEGER PRIMARY KEY,
            name_ar TEXT NOT NULL,
            name_en TEXT NOT NULL,
            slug    TEXT NOT NULL UNIQUE
        );

        CREATE TABLE properties (
            id          INTEGER PRIMARY KEY,
            user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            city_id     INTEGER REFERENCES cities(id) ON DELETE SET NULL,
            title       TEXT    NOT NULL,
            description TEXT    NOT NULL DEFAULT '',
            purpose     TEXT    NOT NULL CHECK (purpose IN ('sale','rent')),
            type        TEXT    NOT NULL,
            district    TEXT    NOT NULL DEFAULT '',
            address     TEXT    NOT NULL DEFAULT '',
            price       INTEGER NOT NULL CHECK (price >= 0),
            rent_period TEXT    CHECK (rent_period IN ('monthly','yearly','daily') OR rent_period IS NULL),
            area        REAL    NOT NULL DEFAULT 0,
            bedrooms    INTEGER NOT NULL DEFAULT 0,
            bathrooms   INTEGER NOT NULL DEFAULT 0,
            floor       INTEGER,
            year_built  INTEGER,
            furnished   INTEGER NOT NULL DEFAULT 0,
            features    TEXT    NOT NULL DEFAULT '[]',
            lat         REAL,
            lng         REAL,
            status      TEXT    NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','published','rejected','sold')),
            is_featured INTEGER NOT NULL DEFAULT 0,
            views       INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            updated_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX idx_properties_search ON properties(status, purpose, type, city_id, price);
        CREATE INDEX idx_properties_user   ON properties(user_id);
        CREATE INDEX idx_properties_feat   ON properties(status, is_featured, created_at);

        CREATE TABLE property_images (
            id          INTEGER PRIMARY KEY,
            property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            filename    TEXT    NOT NULL,
            thumb       TEXT    NOT NULL,
            width       INTEGER NOT NULL,
            height      INTEGER NOT NULL,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX idx_images_property ON property_images(property_id, sort_order);

        CREATE TABLE favorites (
            user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            PRIMARY KEY (user_id, property_id)
        ) WITHOUT ROWID;

        CREATE TABLE inquiries (
            id          INTEGER PRIMARY KEY,
            property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
            name        TEXT NOT NULL,
            email       TEXT NOT NULL,
            phone       TEXT NOT NULL DEFAULT '',
            message     TEXT NOT NULL,
            is_read     INTEGER NOT NULL DEFAULT 0,
            ip          TEXT NOT NULL DEFAULT '',
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX idx_inquiries_property ON inquiries(property_id, created_at);
        CREATE INDEX idx_inquiries_ip ON inquiries(ip, created_at);

        CREATE TABLE login_attempts (
            id           INTEGER PRIMARY KEY,
            ip           TEXT NOT NULL,
            email        TEXT NOT NULL,
            attempted_at INTEGER NOT NULL
        );
        CREATE INDEX idx_login_attempts ON login_attempts(ip, attempted_at);
        CREATE INDEX idx_login_attempts_email ON login_attempts(email, attempted_at);

        -- Full-text search over title/description/district (external content table)
        CREATE VIRTUAL TABLE properties_fts USING fts5(
            title, description, district, address,
            content='properties', content_rowid='id', tokenize='unicode61 remove_diacritics 2'
        );
        CREATE TRIGGER properties_ai AFTER INSERT ON properties BEGIN
            INSERT INTO properties_fts(rowid, title, description, district, address)
            VALUES (new.id, new.title, new.description, new.district, new.address);
        END;
        CREATE TRIGGER properties_ad AFTER DELETE ON properties BEGIN
            INSERT INTO properties_fts(properties_fts, rowid, title, description, district, address)
            VALUES ('delete', old.id, old.title, old.description, old.district, old.address);
        END;
        CREATE TRIGGER properties_au AFTER UPDATE OF title, description, district, address ON properties BEGIN
            INSERT INTO properties_fts(properties_fts, rowid, title, description, district, address)
            VALUES ('delete', old.id, old.title, old.description, old.district, old.address);
            INSERT INTO properties_fts(rowid, title, description, district, address)
            VALUES (new.id, new.title, new.description, new.district, new.address);
        END;
        SQL,

        // v2 — Kurdistan localisation, offices (نوسینگە), currencies, web push
        <<<SQL
        ALTER TABLE cities ADD COLUMN name_ku TEXT NOT NULL DEFAULT '';

        ALTER TABLE properties ADD COLUMN currency  TEXT    NOT NULL DEFAULT 'USD' CHECK (currency IN ('USD','IQD'));
        ALTER TABLE properties ADD COLUMN price_usd INTEGER NOT NULL DEFAULT 0;
        ALTER TABLE properties ADD COLUMN deed      TEXT    CHECK (deed IN ('tapo','agricultural','investment','other') OR deed IS NULL);
        ALTER TABLE properties ADD COLUMN project   TEXT    NOT NULL DEFAULT '';
        UPDATE properties SET price_usd = price;
        CREATE INDEX idx_properties_price_usd ON properties(status, price_usd);

        CREATE TABLE offices (
            id          INTEGER PRIMARY KEY,
            user_id     INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            name        TEXT    NOT NULL,
            slug        TEXT    NOT NULL UNIQUE,
            logo        TEXT,
            about       TEXT    NOT NULL DEFAULT '',
            phone       TEXT    NOT NULL DEFAULT '',
            whatsapp    TEXT    NOT NULL DEFAULT '',
            address     TEXT    NOT NULL DEFAULT '',
            city_id     INTEGER REFERENCES cities(id) ON DELETE SET NULL,
            is_verified INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE push_subscriptions (
            id           INTEGER PRIMARY KEY,
            endpoint     TEXT    NOT NULL UNIQUE,
            p256dh       TEXT    NOT NULL,
            auth         TEXT    NOT NULL,
            user_id      INTEGER REFERENCES users(id) ON DELETE SET NULL,
            lang         TEXT    NOT NULL DEFAULT 'ckb',
            site_news    INTEGER NOT NULL DEFAULT 0,
            fail_count   INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
            last_seen_at TEXT    NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX idx_push_user ON push_subscriptions(user_id);

        CREATE TABLE office_follows (
            subscription_id INTEGER NOT NULL REFERENCES push_subscriptions(id) ON DELETE CASCADE,
            office_id       INTEGER NOT NULL REFERENCES offices(id) ON DELETE CASCADE,
            created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
            PRIMARY KEY (subscription_id, office_id)
        ) WITHOUT ROWID;
        CREATE INDEX idx_follows_office ON office_follows(office_id);

        CREATE TABLE notifications (
            id          INTEGER PRIMARY KEY,
            sender_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
            office_id   INTEGER REFERENCES offices(id) ON DELETE CASCADE,
            audience    TEXT    NOT NULL CHECK (audience IN ('followers','all')),
            kind        TEXT    NOT NULL DEFAULT 'manual' CHECK (kind IN ('manual','auto')),
            title       TEXT    NOT NULL,
            body        TEXT    NOT NULL,
            url         TEXT    NOT NULL DEFAULT '/',
            property_id INTEGER REFERENCES properties(id) ON DELETE SET NULL,
            sent        INTEGER NOT NULL DEFAULT 0,
            failed      INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX idx_notifications_office ON notifications(office_id, created_at);

        -- Rebuild FTS to also index the project / compound name (e.g. Dream City, Italian Village)
        DROP TRIGGER properties_ai;
        DROP TRIGGER properties_ad;
        DROP TRIGGER properties_au;
        DROP TABLE properties_fts;
        CREATE VIRTUAL TABLE properties_fts USING fts5(
            title, description, district, address, project,
            content='properties', content_rowid='id', tokenize='unicode61 remove_diacritics 2'
        );
        CREATE TRIGGER properties_ai AFTER INSERT ON properties BEGIN
            INSERT INTO properties_fts(rowid, title, description, district, address, project)
            VALUES (new.id, new.title, new.description, new.district, new.address, new.project);
        END;
        CREATE TRIGGER properties_ad AFTER DELETE ON properties BEGIN
            INSERT INTO properties_fts(properties_fts, rowid, title, description, district, address, project)
            VALUES ('delete', old.id, old.title, old.description, old.district, old.address, old.project);
        END;
        CREATE TRIGGER properties_au AFTER UPDATE OF title, description, district, address, project ON properties BEGIN
            INSERT INTO properties_fts(properties_fts, rowid, title, description, district, address, project)
            VALUES ('delete', old.id, old.title, old.description, old.district, old.address, old.project);
            INSERT INTO properties_fts(rowid, title, description, district, address, project)
            VALUES (new.id, new.title, new.description, new.district, new.address, new.project);
        END;
        INSERT INTO properties_fts(properties_fts) VALUES ('rebuild');
        SQL,

        // v3 — videos, saved-search alerts, reports, duplicate detection
        <<<SQL
        ALTER TABLE property_images ADD COLUMN phash TEXT;          -- 64-bit dHash (16 hex) of the original photo
        CREATE INDEX idx_images_phash ON property_images(phash);

        CREATE TABLE property_videos (
            id          INTEGER PRIMARY KEY,
            property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            filename    TEXT    NOT NULL,
            mime        TEXT    NOT NULL,
            size        INTEGER NOT NULL,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX idx_videos_property ON property_videos(property_id);

        CREATE TABLE saved_searches (
            id              INTEGER PRIMARY KEY,
            subscription_id INTEGER NOT NULL REFERENCES push_subscriptions(id) ON DELETE CASCADE,
            filters         TEXT    NOT NULL,          -- normalised JSON of search_filters()
            label           TEXT    NOT NULL DEFAULT '',
            hits            INTEGER NOT NULL DEFAULT 0,
            created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE (subscription_id, filters)
        );

        CREATE TABLE reports (
            id          INTEGER PRIMARY KEY,
            property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            reason      TEXT    NOT NULL CHECK (reason IN ('sold','wrong_price','fake','duplicate','wrong_location','offensive','other')),
            details     TEXT    NOT NULL DEFAULT '',
            ip          TEXT    NOT NULL DEFAULT '',
            status      TEXT    NOT NULL DEFAULT 'open' CHECK (status IN ('open','resolved')),
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX idx_reports_status ON reports(status, property_id);
        CREATE INDEX idx_reports_ip ON reports(ip, created_at);

        CREATE TABLE duplicate_flags (
            property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            other_id    INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            reason      TEXT    NOT NULL,                -- 'photo' or 'location'
            distance    INTEGER NOT NULL DEFAULT 0,      -- photo hash Hamming distance
            dismissed   INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            PRIMARY KEY (property_id, other_id)
        ) WITHOUT ROWID;

        -- notifications: add the 'search' audience (alert for one device's saved search)
        CREATE TABLE notifications_v3 (
            id              INTEGER PRIMARY KEY,
            sender_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
            office_id       INTEGER REFERENCES offices(id) ON DELETE CASCADE,
            subscription_id INTEGER REFERENCES push_subscriptions(id) ON DELETE CASCADE,
            audience        TEXT    NOT NULL CHECK (audience IN ('followers','all','search')),
            kind            TEXT    NOT NULL DEFAULT 'manual' CHECK (kind IN ('manual','auto')),
            title           TEXT    NOT NULL,
            body            TEXT    NOT NULL,
            url             TEXT    NOT NULL DEFAULT '/',
            property_id     INTEGER REFERENCES properties(id) ON DELETE SET NULL,
            sent            INTEGER NOT NULL DEFAULT 0,
            failed          INTEGER NOT NULL DEFAULT 0,
            created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
        );
        INSERT INTO notifications_v3 (id, sender_id, office_id, audience, kind, title, body, url, property_id, sent, failed, created_at)
            SELECT id, sender_id, office_id, audience, kind, title, body, url, property_id, sent, failed, created_at FROM notifications;
        DROP TABLE notifications;
        ALTER TABLE notifications_v3 RENAME TO notifications;
        CREATE INDEX idx_notifications_office ON notifications(office_id, created_at);
        CREATE INDEX idx_notifications_sub ON notifications(subscription_id, created_at);
        CREATE INDEX idx_notifications_prop ON notifications(property_id, kind);
        SQL,
    ];
}
