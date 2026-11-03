<?php
declare(strict_types=1);

/**
 * A short (60 s) cache of public pages for visitors who are not signed in — the bulk of the traffic — so a burst
 * of visitors is served from ready-made files instead of the database.
 *
 *  - Only home, search (common filters, first pages), offices, office and city pages. Never listing pages (they
 *    count views and carry the enquiry form), never "near me", never anyone signed in or with a message waiting.
 *  - Per-visitor values (security token, form time stamp) are swapped in on every hit, so forms keep working.
 *  - Any change on the site (a listing saved, approved, deleted, an office edited, the daily job) starts a new
 *    "generation": every cached page is stale at once. Beacons (views, taps, push status) don't count as changes.
 */

const PAGE_CACHE_TTL = 60;
const PAGE_CACHE_MAX_FILES = 3000;
const PAGE_CACHE_CSRF = '%%AQ-CSRF%%';

function page_cache_dir(): string
{
    return dirname((string) config('db_path')) . '/cache';
}

function page_cache_generation(): string
{
    $f = page_cache_dir() . '/generation';
    return is_file($f) ? (string) @file_get_contents($f) : '0';
}

/** Something changed: every cached page is out of date from now on. */
function page_cache_bump(): void
{
    $dir = page_cache_dir();
    if (is_dir($dir) || @mkdir($dir, 0750, true)) {
        @file_put_contents($dir . '/generation', (string) hrtime(true), LOCK_EX);
    }
}

/** Requests that write nothing visible on public pages (so they must not empty the cache). */
function page_cache_is_beacon(string $path): bool
{
    return (bool) preg_match('#^/(property/\d+/(view|contact)|push/status|api/)#', $path);
}

/** The cache key for this request, or null when it must not be cached. */
function page_cache_key(string $method, string $path): ?string
{
    if ($method !== 'GET' || !empty($_SESSION['user_id']) || !empty($_SESSION['_flash']) || !empty($_SESSION['_old']) || !empty($_SESSION['_errors'])
        || !empty($_SESSION['_2fa']) || config('debug')) {
        return null;
    }
    if (!preg_match('#^/($|properties$|offices$|office/\d+$|city/[a-z0-9-]+$)#', $path)) {
        return null;
    }
    $q = $_GET;
    unset($q['lang']);                               // the language is part of the key below
    if ($q) {
        // Search pages: only the common, low-variety filters (anything rarer is not worth a file)
        if ($path !== '/properties' && !preg_match('#^/city/#', $path)) {
            return null;
        }
        $allowed = ['purpose', 'type', 'city', 'sort', 'page', 'district'];
        if (array_diff(array_keys($q), $allowed) || (int) ($q['page'] ?? 1) > 5 || array_filter($q, 'is_array')) {
            return null;
        }
        ksort($q);
    }
    return hash('sha256', implode('|', [page_cache_generation(), lang(), forced_lang() ?? '', $_SERVER['HTTP_HOST'] ?? '', base_path(), $path, http_build_query($q)]));
}

/** Serve a fresh cached copy (with this visitor's tokens) and stop, or return false. */
function page_cache_serve(string $key): bool
{
    $file = page_cache_dir() . '/p-' . $key . '.html';
    $mtime = @filemtime($file);
    if ($mtime === false || $mtime < time() - PAGE_CACHE_TTL) {
        return false;
    }
    $html = @file_get_contents($file);
    if ($html === false || $html === '') {
        return false;
    }
    $html = str_replace(PAGE_CACHE_CSRF, csrf_token(), $html);
    $html = (string) preg_replace_callback('#<input type="hidden" name="_ft" value="[^"]*">#', static fn() => form_time_field(), $html);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Page-Cache: HIT');
    echo $html;
    return true;
}

/** Keep the page that was just rendered (tokens replaced by placeholders). */
function page_cache_store(string $key, string $html): void
{
    // Only HTML pages (PHP's default type is text/html, so a page usually sets no Content-Type of its own)
    $type = '';
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $type = $h;
        }
    }
    if (http_response_code() !== 200 || $html === '' || ($type !== '' && stripos($type, 'text/html') === false)) {
        return;
    }
    if (!empty($_SESSION['_flash'])) {
        return;                                      // a message was queued while rendering — page is personal
    }
    $dir = page_cache_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return;
    }
    if (count(glob($dir . '/p-*.html', GLOB_NOSORT) ?: []) >= PAGE_CACHE_MAX_FILES) {
        return;                                      // full: the daily clean-up makes room
    }
    $tmp = $dir . '/p-' . $key . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, str_replace(csrf_token(), PAGE_CACHE_CSRF, $html)) !== false) {
        @rename($tmp, $dir . '/p-' . $key . '.html');
    }
}

/** Daily job: remove cached pages nobody can use any more. */
function page_cache_cleanup(): void
{
    foreach (glob(page_cache_dir() . '/p-*', GLOB_NOSORT) ?: [] as $f) {
        if (@filemtime($f) < time() - PAGE_CACHE_TTL * 5) {
            @unlink($f);
        }
    }
}
