<?php
declare(strict_types=1);

require __DIR__ . '/controllers/public.php';
require __DIR__ . '/controllers/auth.php';
require __DIR__ . '/controllers/dashboard.php';
require __DIR__ . '/controllers/admin.php';
require __DIR__ . '/controllers/offices.php';
require __DIR__ . '/controllers/pwa.php';
require __DIR__ . '/controllers/install.php';
require __DIR__ . '/controllers/seo.php';

/** [method, regex (without delimiters), handler] — numbered groups are passed as int args. */
function routes(): array
{
    return [
        ['GET',  '/',                                   'home_page'],
        ['GET',  '/properties',                         'properties_index'],
        ['GET',  '/property/(\d+)(?:-[^/]*)?',          'property_show'],
        ['POST', '/property/(\d+)/inquiry',             'property_inquiry'],
        ['POST', '/favorite/(\d+)',                     'favorite_toggle'],
        ['GET',  '/lang/(ckb|ar|en)',                   'switch_lang'],
        ['GET',  '/offices',                            'offices_index'],
        ['GET',  '/office/(\d+)',                       'office_show'],
        ['GET',  '/notifications',                      'notifications_page'],
        ['POST', '/push/follow',                        'push_follow'],
        ['POST', '/push/unfollow',                      'push_unfollow'],
        ['POST', '/push/news',                          'push_news'],
        ['POST', '/push/status',                        'push_status'],
        ['GET',  '/api/map',                            'api_map'],
        ['GET',  '/api/count',                          'api_count'],
        ['GET',  '/api/nearby',                         'api_nearby'],
        ['GET',  '/api/recommend',                      'api_recommend'],
        ['POST', '/property/(\d+)/view',                'property_view_beacon'],
        ['POST', '/property/(\d+)/contact',             'property_contact_beacon'],
        ['GET',  '/manifest',                           'pwa_manifest'],   // extension-less: front proxies never treat it as a static file
        ['GET',  '/manifest.webmanifest',               'pwa_manifest'],
        ['GET',  '/offline',                            'offline_page'],
        ['POST', '/push/search',                        'push_search_save'],
        ['POST', '/push/search/delete',                 'push_search_delete'],
        ['POST', '/property/(\d+)/report',              'property_report'],
        ['GET',  '/city/([a-z0-9-]+)',                  'city_page'],
        ['GET',  '/sitemap.xml',                        'sitemap_xml'],
        ['GET',  '/robots.txt',                         'robots_txt'],
        ['GET',  '/og/(\d+)\.jpg',                      'og_image'],
        ['GET',  '/install',                            'install_form'],
        ['POST', '/install',                            'install_submit'],

        ['GET',  '/login',                              'login_form'],
        ['POST', '/login',                              'login_submit'],
        ['GET',  '/login/code',                         'login_code_form'],
        ['POST', '/login/code',                         'login_code_submit'],
        ['GET',  '/register',                           'register_form'],
        ['POST', '/register',                           'register_submit'],
        ['POST', '/logout',                             'logout_submit'],

        ['GET',  '/dashboard',                          'dashboard_home'],
        ['GET',  '/dashboard/properties/new',           'dash_property_new'],
        ['POST', '/dashboard/properties',               'dash_property_create'],
        ['GET',  '/dashboard/properties/(\d+)/edit',    'dash_property_edit'],
        ['POST', '/dashboard/properties/(\d+)',         'dash_property_update'],
        ['POST', '/dashboard/properties/(\d+)/delete',  'dash_property_delete'],
        ['POST', '/dashboard/properties/(\d+)/sold',    'dash_property_sold'],
        ['POST', '/dashboard/properties/(\d+)/confirm', 'dash_property_confirm'],
        ['POST', '/dashboard/properties/confirm-all',   'dash_property_confirm_all'],
        ['POST', '/dashboard/images/(\d+)/delete',      'dash_image_delete'],
        ['POST', '/dashboard/images/(\d+)/cover',       'dash_image_cover'],
        ['POST', '/dashboard/videos/(\d+)/delete',      'dash_video_delete'],
        ['GET',  '/dashboard/favorites',                'dash_favorites'],
        ['GET',  '/dashboard/inquiries',                'dash_inquiries'],
        ['POST', '/dashboard/inquiries/(\d+)/read',     'dash_inquiry_read'],
        ['GET',  '/dashboard/2fa',                      'dash_2fa_setup'],
        ['POST', '/dashboard/2fa',                      'dash_2fa_enable'],
        ['GET',  '/dashboard/2fa/codes',                'dash_2fa_codes'],
        ['POST', '/dashboard/2fa/new-codes',            'dash_2fa_new_codes'],
        ['POST', '/dashboard/2fa/disable',              'dash_2fa_disable'],
        ['GET',  '/dashboard/office',                   'dash_office'],
        ['POST', '/dashboard/office',                   'dash_office_save'],
        ['GET',  '/dashboard/notifications',            'dash_notifications'],
        ['POST', '/dashboard/notifications',            'dash_notification_send'],
        ['GET',  '/dashboard/profile',                  'dash_profile'],
        ['POST', '/dashboard/profile',                  'dash_profile_update'],
        ['POST', '/dashboard/password',                 'dash_password_update'],
        ['GET',  '/dashboard/export',                   'dash_export'],
        ['POST', '/dashboard/delete-account',           'dash_delete_account'],
        ['POST', '/admin/backup',                       'admin_backup'],
        ['POST', '/admin/backups/download',             'admin_backup_download'],
        ['GET',  '/privacy',                            'privacy_page'],
        ['GET',  '/terms',                              'terms_page'],

        ['GET',  '/admin',                              'admin_home'],
        ['GET',  '/admin/properties',                   'admin_properties'],
        ['POST', '/admin/properties/(\d+)/status',      'admin_property_status'],
        ['POST', '/admin/properties/(\d+)/feature',     'admin_property_feature'],
        ['GET',  '/admin/users',                        'admin_users'],
        ['POST', '/admin/users/(\d+)',                  'admin_user_update'],
        ['GET',  '/admin/reports',                      'admin_reports'],
        ['POST', '/admin/reports/(\d+)/resolve',        'admin_report_resolve'],
        ['POST', '/admin/duplicates/(\d+)/(\d+)/dismiss', 'admin_duplicate_dismiss'],
        ['GET',  '/admin/offices',                      'admin_offices'],
        ['POST', '/admin/offices/(\d+)/verify',         'admin_office_verify'],
        ['POST', '/admin/offices/(\d+)/activate',       'admin_office_activate'],
        ['POST', '/admin/offices/(\d+)/deactivate',     'admin_office_deactivate'],
        ['POST', '/admin/offices/(\d+)/password',       'admin_office_password'],
        ['POST', '/admin/offices/new',                  'admin_office_new'],
        ['POST', '/admin/offices/signup-mode',          'admin_office_signup_mode'],
        ['GET',  '/admin/notifications',                'admin_notifications'],
        ['POST', '/admin/notifications',                'admin_notification_send'],
        ['GET',  '/admin/cities',                       'admin_cities'],
        ['POST', '/admin/cities',                       'admin_city_create'],
        ['POST', '/admin/cities/(\d+)/delete',          'admin_city_delete'],
        ['GET',  '/admin/cities/(\d+)/districts',       'admin_districts'],
        ['POST', '/admin/districts',                    'admin_district_create'],
        ['POST', '/admin/districts/(\d+)',              'admin_district_update'],
        ['POST', '/admin/districts/(\d+)/delete',       'admin_district_delete'],
    ];
}

function dispatch(string $method, string $path): void
{
    $base = base_path();
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base));
    }
    $path = rtrim($path, '/') ?: '/';
    if ($method === 'HEAD') {
        $method = 'GET';
    }
    if ($method === 'GET' && random_int(1, 20) === 1) {
        run_maintenance();                                   // daily privacy/retention cleanup (cheap check)
    }
    // An account the admin set up must choose its own password first (profile, saving it and signing out still work).
    if (($u = current_user()) && !in_array($path, ['/dashboard/profile', '/dashboard/password', '/logout', '/manifest', '/offline'], true)
        && (int) (db()->query('SELECT must_change_password FROM users WHERE id = ' . (int) $u['id'])->fetchColumn()) === 1) {
        flash('info', t('choose_own_password'));
        redirect(url('dashboard/profile') . '#password');
    }
    // Until an administrator exists, every page leads to the guided installer.
    if (!admin_exists() && !in_array($path, ['/install', '/manifest', '/manifest.webmanifest', '/offline', '/robots.txt'], true)) {
        redirect(url('install'), 302);
    }

    $allowed = [];
    foreach (routes() as [$m, $pattern, $handler]) {
        if (!preg_match('~^' . $pattern . '$~', $path, $mm)) {
            continue;
        }
        if ($m !== $method) {
            $allowed[] = $m;
            continue;
        }
        if ($method === 'POST') {
            verify_csrf();
            if (!page_cache_is_beacon($path)) {
                page_cache_bump();                       // something may change on public pages
            }
        }
        $args = array_map(static fn($v) => ctype_digit($v) ? (int) $v : $v, array_slice($mm, 1));
        $cacheKey = page_cache_key($method, $path);
        if ($cacheKey !== null && page_cache_serve($cacheKey)) {
            return;
        }
        if ($cacheKey !== null) {
            ob_start();
            $handler(...$args);
            page_cache_store($cacheKey, (string) ob_get_contents());
            ob_end_flush();
            return;
        }
        $handler(...$args);
        return;
    }
    if ($allowed) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        abort(405);
    }
    abort(404);
}
