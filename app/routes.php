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
        ['POST', '/dashboard/images/(\d+)/delete',      'dash_image_delete'],
        ['POST', '/dashboard/images/(\d+)/cover',       'dash_image_cover'],
        ['POST', '/dashboard/videos/(\d+)/delete',      'dash_video_delete'],
        ['GET',  '/dashboard/favorites',                'dash_favorites'],
        ['GET',  '/dashboard/inquiries',                'dash_inquiries'],
        ['POST', '/dashboard/inquiries/(\d+)/read',     'dash_inquiry_read'],
        ['GET',  '/dashboard/office',                   'dash_office'],
        ['POST', '/dashboard/office',                   'dash_office_save'],
        ['GET',  '/dashboard/notifications',            'dash_notifications'],
        ['POST', '/dashboard/notifications',            'dash_notification_send'],
        ['GET',  '/dashboard/profile',                  'dash_profile'],
        ['POST', '/dashboard/profile',                  'dash_profile_update'],
        ['POST', '/dashboard/password',                 'dash_password_update'],

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
        ['GET',  '/admin/notifications',                'admin_notifications'],
        ['POST', '/admin/notifications',                'admin_notification_send'],
        ['GET',  '/admin/cities',                       'admin_cities'],
        ['POST', '/admin/cities',                       'admin_city_create'],
        ['POST', '/admin/cities/(\d+)/delete',          'admin_city_delete'],
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
        }
        $args = array_map(static fn($v) => ctype_digit($v) ? (int) $v : $v, array_slice($mm, 1));
        $handler(...$args);
        return;
    }
    if ($allowed) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        abort(405);
    }
    abort(404);
}
