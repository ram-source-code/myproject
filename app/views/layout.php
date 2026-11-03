<?php
/** @var string $content */
$user = current_user();
$path = current_path();
$navActive = static fn(string $p) => ($p === '/' ? $path === '/' : str_starts_with($path, $p)) ? ' aria-current="page"' : '';
$useMap = $useMap ?? false;
?>
<!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= e(dir_attr()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#1f5641" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#121614" media="(prefers-color-scheme: dark)">
    <title><?= e($pageTitle ?? app_name()) ?></title>
    <meta name="description" content="<?= e($metaDesc ?? t('meta_description')) ?>">
<?php
    $q = $_GET; unset($q['lang']);
    $canonical = $seo['canonical'] ?? absolute_origin() . base_path() . current_path() . ($q ? '?' . http_build_query($q) : '');
    $og = ($seo['og'] ?? []) + [
        'type' => 'website', 'title' => $pageTitle ?? app_name(), 'description' => $metaDesc ?? t('meta_description'),
        'image' => absolute_origin() . asset('img/icon-512.png'), 'url' => $canonical,
    ];
    $ogLocale = ['ckb' => 'ckb_IQ', 'ar' => 'ar_IQ', 'en' => 'en_US'][lang()];
?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php foreach (seo_alternates() as $hl => $href): ?>
        <link rel="alternate" hreflang="<?= e($hl) ?>" href="<?= e($href) ?>">
    <?php endforeach; ?>
    <meta property="og:site_name" content="<?= e(app_name()) ?>">
    <meta property="og:locale" content="<?= e($ogLocale) ?>">
    <meta property="og:type" content="<?= e($og['type']) ?>">
    <meta property="og:title" content="<?= e($og['title']) ?>">
    <meta property="og:description" content="<?= e($og['description']) ?>">
    <meta property="og:url" content="<?= e($og['url']) ?>">
    <meta property="og:image" content="<?= e($og['image']) ?>">
    <?php if (!empty($og['image_width'])): ?>
        <meta property="og:image:width" content="<?= (int) $og['image_width'] ?>">
        <meta property="og:image:height" content="<?= (int) $og['image_height'] ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= !empty($seo['og']) ? 'summary_large_image' : 'summary' ?>">
    <?php if (!empty($seo['jsonld'])): ?>
        <script type="application/ld+json"><?= jsonld($seo['jsonld']) ?></script>
    <?php elseif (current_path() === '/'): ?>
        <script type="application/ld+json"><?= jsonld([
            '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => app_name(), 'url' => absolute_origin() . url('/'),
            'potentialAction' => ['@type' => 'SearchAction', 'target' => absolute_origin() . base_path() . '/properties?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
        ]) ?></script>
    <?php endif; ?>
    <meta name="app-base" content="<?= e(base_path()) ?>">
    <meta name="vapid-key" content="<?= e(vapid_keys()['public']) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="manifest" href="<?= e(url('manifest')) ?>" crossorigin="use-credentials">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
    <?php foreach (IOS_SPLASH as [$w, $h, $r]): ?>
        <link rel="apple-touch-startup-image" href="<?= e(asset("img/splash/splash-{$w}x{$h}.png")) ?>" media="(device-width: <?= intdiv($w, $r) ?>px) and (device-height: <?= intdiv($h, $r) ?>px) and (-webkit-device-pixel-ratio: <?= $r ?>) and (orientation: portrait)">
    <?php endforeach; ?>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= e(app_name()) ?>">
    <?php /* Preload EXACTLY the URL that @font-face in app.css requests (no ?v= query) — otherwise the browser treats
             them as two files: the preload goes unused (console warning) and the font downloads twice. */ ?>
    <link rel="preload" href="<?= e(url('assets/fonts/noto-kufi-arabic-wght.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <?php $useMap = $useMap && maps_enabled(); ?>
    <?php if ($useMap): ?>
        <link rel="stylesheet" href="<?= e(asset('vendor/leaflet/leaflet.css')) ?>">
        <link rel="stylesheet" href="<?= e(asset('vendor/markercluster/MarkerCluster.css')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if ($useMap): ?>
        <script src="<?= e(asset('vendor/leaflet/leaflet.js')) ?>" defer></script>
        <script src="<?= e(asset('vendor/markercluster/leaflet.markercluster.js')) ?>" defer></script>
        <meta name="map-tiles" content="<?= e(config('tile_url')) ?>" data-attribution="<?= e(config('tile_attribution')) ?>">
    <?php endif; ?>
    <?php if (!empty($useQr)): ?><script src="<?= e(asset('vendor/qrcode/qrcode.js')) ?>" defer></script><?php endif; ?>
    <script src="<?= e(asset('js/boot.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?php /* Hold the first paint (and the view transition) until <main> is parsed: no flash of a half page. */ ?>
    <link rel="expect" href="#main" blocking="render">
    <?php
    // Speculation Rules: prefetch likely next pages on hover/touch, prerender listing and office pages.
    $b = base_path();
    $never = array_map(static fn($p) => ['not' => ['href_matches' => "$b/$p*"]], ['dashboard', 'admin', 'logout', 'lang/', 'push', 'api', 'install', 'login', 'register', 'og/', 'uploads/']);
    $rules = [
        'prefetch'  => [['where' => ['and' => array_merge([['href_matches' => "$b/*"]], $never, [['not' => ['selector_matches' => '[rel~=nofollow], [target=_blank], [download]']]])], 'eagerness' => 'moderate']],
        'prerender' => [['where' => ['or' => [['href_matches' => "$b/property/*"], ['href_matches' => "$b/office/*"], ['href_matches' => "$b/city/*"]]], 'eagerness' => 'moderate']],
    ];
    ?>
    <script type="speculationrules"><?= json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</head>
<body class="<?= $user ? 'is-auth' : 'is-guest' ?>">
<?= render_partial('partials/icons') ?>
<script type="application/json" id="i18n"><?= json_encode([
    'show' => t('show'), 'hide' => t('hide'), 'link_copied' => t('link_copied'), 'copied' => t('copied'),
    'files_selected' => t('files_selected'), 'file_too_big' => t('file_too_big'), 'too_many_files' => t('too_many_files'), 'remove_photo' => t('remove_photo'), 'preparing_photos' => t('preparing_photos'), 'uploading_pct' => t('uploading_pct'), 'upload_failed' => t('upload_failed'), 'saving' => t('saving'), 'img_heic_client' => t('img_heic_client'),
    'v_password_match' => t('v_password_match'),
    'follow' => t('follow'), 'following' => t('following'), 'push_denied' => t('push_denied'),
    'push_unsupported' => t('push_unsupported'), 'push_ios' => t('push_ios'), 'followed_toast' => t('followed_toast'),
    'unfollowed_toast' => t('unfollowed_toast'), 'news_on' => t('news_on'), 'news_off' => t('news_off'),
    'all_on' => t('all_on'), 'all_off' => t('all_off'), 'push_test_sent' => t('push_test_sent'), 'push_test_nosub' => t('push_test_nosub'),
    'no_follows' => t('no_follows'), 'no_feed' => t('no_feed'), 'unfollow' => t('unfollow'),
    'view' => t('view'), 'beds_short' => t('beds_short'), 'sqm' => t('sqm'), 'map_empty' => t('map_empty'),
    'alert_on' => t('alert_on'), 'delete' => t('delete'), 'no_searches' => t('no_searches'),
    'loc_insecure' => t('loc_insecure'), 'loc_unavailable' => t('loc_unavailable'), 'loc_denied' => t('loc_denied'),
    'loc_timeout' => t('loc_timeout'), 'loc_set' => t('loc_set'), 'near_none' => t('near_none'), 'pick_city' => t('pick_city'),
    'locate_me' => t('locate_me'), 'search_this_area' => t('search_this_area'),
    'recently_viewed' => t('recently_viewed'), 'clear' => t('reset'), 'offline_now' => t('offline_now'), 'online_now' => t('online_now'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<a class="skip-link" href="#main"><?= e(t('skip_to_content')) ?></a>

<header class="site-header">
    <div class="container header-inner">
        <button type="button" class="app-back" data-app-back hidden aria-label="<?= e(t('back')) ?>"><svg aria-hidden="true"><use href="#i-chevron"></use></svg></button>
        <a class="brand" href="<?= e(url('/')) ?>">
            <svg class="brand-mark" aria-hidden="true"><use href="#i-home"></use></svg>
            <span><?= e(app_name()) ?></span>
        </a>

        <nav id="primary-nav" class="primary-nav" aria-label="<?= e(t('main_nav')) ?>">
            <ul>
                <li><a href="<?= e(url('properties', ['purpose' => 'sale'])) ?>"<?= ($path === '/properties' && ($_GET['purpose'] ?? '') === 'sale') ? ' aria-current="page"' : '' ?>><?= e(t('nav_buy')) ?></a></li>
                <li><a href="<?= e(url('properties', ['purpose' => 'rent'])) ?>"<?= ($path === '/properties' && ($_GET['purpose'] ?? '') === 'rent') ? ' aria-current="page"' : '' ?>><?= e(t('nav_rent')) ?></a></li>
                <li><a href="<?= e(url('properties', ['view' => 'map'])) ?>"><?= e(t('map')) ?></a></li>
                <li><a href="<?= e(url('offices')) ?>"<?= $navActive('/office') ?>><?= e(t('offices')) ?></a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <details class="menu lang-menu">
                <summary class="btn btn-ghost btn-sm" aria-label="<?= e(t('language')) ?>">
                    <svg aria-hidden="true"><use href="#i-globe"></use></svg><span class="hide-sm"><?= e(LANG_NAMES[lang()]) ?></span>
                </summary>
                <ul class="menu-list">
                    <?php foreach (LANG_NAMES as $code => $name): ?>
                        <li><a href="<?= e(url('lang/' . $code)) ?>" lang="<?= e($code) ?>" hreflang="<?= e($code) ?>"<?= lang() === $code ? ' aria-current="true"' : '' ?>><?= e($name) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <a class="icon-link" href="<?= e(url('notifications')) ?>"<?= $navActive('/notifications') ?> aria-label="<?= e(t('notifications')) ?>">
                <svg aria-hidden="true"><use href="#i-bell"></use></svg>
            </a>
            <?php $canPost = can_post_listings($user); ?>
            <?php if ($user): ?>
                <?php if ($canPost): ?>
                <a class="btn btn-primary btn-sm hide-sm" href="<?= e(url('dashboard/properties/new')) ?>">
                    <svg aria-hidden="true"><use href="#i-plus"></use></svg><?= e(t('add_listing')) ?>
                </a>
                <?php endif; ?>
                <details class="menu account-menu">
                    <summary class="btn btn-ghost btn-sm" aria-label="<?= e(t('account')) ?>">
                        <span class="avatar avatar-sm" aria-hidden="true"><?= e(mb_substr($user['name'], 0, 1)) ?></span>
                        <span class="hide-sm"><?= e(str_limit($user['name'], 18)) ?></span>
                    </summary>
                    <ul class="menu-list">
                        <?php if ($canPost || $user['role'] === 'admin'): ?><li><a href="<?= e(url('dashboard')) ?>"><?= e(t('my_listings')) ?></a></li><?php endif; ?>
                        <?php if (office_state($user) !== 'none'): ?>
                            <li><a href="<?= e(url('dashboard/office')) ?>"><?= e(t('my_office')) ?></a></li>
                            <?php if ($canPost): ?><li><a href="<?= e(url('dashboard/notifications')) ?>"><?= e(t('send_notification')) ?></a></li><?php endif; ?>
                        <?php else: ?>
                            <li><a href="<?= e(url('dashboard/office')) ?>"><?= e(t('open_office')) ?></a></li>
                        <?php endif; ?>
                        <li><a href="<?= e(url('dashboard/favorites')) ?>"><?= e(t('favorites')) ?></a></li>
                        <li><a href="<?= e(url('dashboard/profile')) ?>"><?= e(t('profile')) ?></a></li>
                        <?php if ($user['role'] === 'admin'): ?><li><a href="<?= e(url('admin')) ?>"><?= e(t('admin')) ?></a></li><?php endif; ?>
                        <li>
                            <form method="post" action="<?= e(url('logout')) ?>" data-logout>
                                <?= csrf_field() ?>
                                <button type="submit" class="menu-btn"><?= e(t('logout')) ?></button>
                            </form>
                        </li>
                    </ul>
                </details>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('login')) ?>"><?= e(t('login')) ?></a>
                <a class="btn btn-primary btn-sm hide-sm" href="<?= e(url('register')) ?>"><?= e(t('register')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="install-banner" data-install-banner hidden>
    <div class="container install-inner">
        <img src="<?= e(asset('img/icon-192.png')) ?>" alt="" width="40" height="40">
        <p><strong><?= e(t('install_title')) ?></strong> <span class="muted small" data-install-text><?= e(t('install_text')) ?></span></p>
        <button type="button" class="btn btn-primary btn-sm" data-install><?= e(t('install')) ?></button>
        <button type="button" class="icon-btn-plain" data-install-dismiss aria-label="<?= e(t('close')) ?>"><svg aria-hidden="true"><use href="#i-x"></use></svg></button>
    </div>
</div>

<?php $flashes = take_flashes(); if ($flashes): ?>
<div class="container flash-stack" role="status" aria-live="polite">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>">
            <svg aria-hidden="true"><use href="#i-<?= $f['type'] === 'error' ? 'alert' : 'check' ?>"></use></svg>
            <p><?= e($f['message']) ?></p>
            <button type="button" class="alert-close" data-dismiss aria-label="<?= e(t('close')) ?>">
                <svg aria-hidden="true"><use href="#i-x"></use></svg>
            </button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<main id="main" tabindex="-1">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand" href="<?= e(url('/')) ?>">
                <svg class="brand-mark" aria-hidden="true"><use href="#i-home"></use></svg>
                <span><?= e(app_name()) ?></span>
            </a>
            <p class="muted"><?= e(t('footer_about')) ?></p>
            <button type="button" class="btn btn-outline btn-sm" data-install hidden><svg aria-hidden="true"><use href="#i-download"></use></svg><?= e(t('install_app')) ?></button>
        </div>
        <nav aria-label="<?= e(t('footer_nav')) ?>">
            <h2 class="footer-title"><?= e(t('browse')) ?></h2>
            <ul>
                <li><a href="<?= e(url('properties', ['purpose' => 'sale'])) ?>"><?= e(t('for_sale')) ?></a></li>
                <li><a href="<?= e(url('properties', ['purpose' => 'rent'])) ?>"><?= e(t('for_rent')) ?></a></li>
                <li><a href="<?= e(url('properties', ['type' => 'house'])) ?>"><?= e(t('type_house')) ?></a></li>
                <li><a href="<?= e(url('properties', ['type' => 'apartment'])) ?>"><?= e(t('type_apartment')) ?></a></li>
                <li><a href="<?= e(url('properties', ['type' => 'land'])) ?>"><?= e(t('type_land')) ?></a></li>
                <li><a href="<?= e(url('offices')) ?>"><?= e(t('offices')) ?></a></li>
            </ul>
        </nav>
        <div>
            <h2 class="footer-title"><?= e(t('list_with_us')) ?></h2>
            <p class="muted"><?= e(t('list_with_us_text')) ?></p>
            <a class="btn btn-outline btn-sm" href="<?= e(url('register', ['type' => 'office'])) ?>"><?= e(t('register_office')) ?></a>
        </div>
    </div>
    <div class="container footer-bottom">
        <small>© <?= date('Y') ?> <?= e(app_name()) ?>. <?= e(t('rights')) ?>
            · <a href="<?= e(url('privacy')) ?>"><?= e(t('privacy_policy')) ?></a>
            · <a href="<?= e(url('terms')) ?>"><?= e(t('terms')) ?></a></small>
        <ul class="footer-langs">
            <?php foreach (LANG_NAMES as $code => $name): ?>
                <li><a href="<?= e(url('lang/' . $code)) ?>" lang="<?= e($code) ?>"><?= e($name) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</footer>

<nav class="bottom-nav" aria-label="<?= e(t('app_nav')) ?>">
    <a href="<?= e(url('/')) ?>"<?= $navActive('/') ?>><svg aria-hidden="true"><use href="#i-home"></use></svg><span><?= e(t('nav_home')) ?></span></a>
    <a href="<?= e(url('properties')) ?>"<?= $navActive('/propert') ?>><svg aria-hidden="true"><use href="#i-search"></use></svg><span><?= e(t('search')) ?></span></a>
    <a href="<?= e($canPost ? url('dashboard/properties/new') : ($user ? url('dashboard/office') : url('register', ['type' => 'office']))) ?>" class="bottom-nav-add"><svg aria-hidden="true"><use href="#i-plus"></use></svg><span><?= e(t('add_short')) ?></span></a>
    <a href="<?= e(url('offices')) ?>"<?= $navActive('/office') ?>><svg aria-hidden="true"><use href="#i-building"></use></svg><span><?= e(t('offices_short')) ?></span></a>
    <a href="<?= e(url($user ? 'dashboard' : 'login')) ?>"<?= $navActive('/dashboard') ?: $navActive('/login') ?>><svg aria-hidden="true"><use href="#i-user"></use></svg><span><?= e(t('account')) ?></span></a>
</nav>

<dialog id="ios-install" class="sheet" aria-labelledby="ios-install-h" closedby="any">
    <div class="sheet-head">
        <span class="sheet-grip" aria-hidden="true"></span>
        <h2 id="ios-install-h" class="panel-title"><?= e(t('ios_install_title')) ?></h2>
        <form method="dialog"><button class="icon-btn-plain" aria-label="<?= e(t('close')) ?>"><svg aria-hidden="true"><use href="#i-x"></use></svg></button></form>
    </div>
    <div class="sheet-body">
        <p class="muted"><?= e(t('ios_install_why')) ?></p>
        <ol class="ios-steps">
            <li><span><?= e(t('ios_step_1')) ?></span><svg aria-hidden="true"><use href="#i-ios-share"></use></svg></li>
            <li><span><?= e(t('ios_step_2')) ?></span><svg aria-hidden="true"><use href="#i-square-plus"></use></svg></li>
            <li><span><?= e(t('ios_step_3')) ?></span><svg aria-hidden="true"><use href="#i-smartphone"></use></svg></li>
        </ol>
        <form method="dialog"><button class="btn btn-primary btn-block"><?= e(t('got_it')) ?></button></form>
    </div>
</dialog>

<dialog id="confirm-dialog" class="dialog dialog-sm" aria-labelledby="confirm-title" closedby="any">
    <form method="dialog">
        <h2 id="confirm-title" class="dialog-title"><?= e(t('are_you_sure')) ?></h2>
        <p id="confirm-text" class="muted"></p>
        <div class="dialog-actions">
            <button class="btn btn-ghost" value="cancel" autofocus><?= e(t('cancel')) ?></button>
            <button class="btn btn-danger" value="confirm"><?= e(t('confirm')) ?></button>
        </div>
    </form>
</dialog>
</body>
</html>
