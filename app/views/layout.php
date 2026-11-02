<?php
/** @var string $content */
$user = current_user();
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$navActive = static fn(string $p) => ($p === '/' ? $path === '/' : str_starts_with($path, $p)) ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= e(dir_attr()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#0f766e">
    <title><?= e($pageTitle ?? app_name()) ?></title>
    <meta name="description" content="<?= e($metaDesc ?? t('meta_description')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<?= render_partial('partials/icons') ?>
<script type="application/json" id="i18n"><?= json_encode([
    'show' => t('show'), 'hide' => t('hide'), 'link_copied' => t('link_copied'),
    'files_selected' => t('files_selected'), 'file_too_big' => t('file_too_big'), 'too_many_files' => t('too_many_files'),
    'v_password_match' => t('v_password_match'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<a class="skip-link" href="#main"><?= e(t('skip_to_content')) ?></a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <svg class="brand-mark" aria-hidden="true"><use href="#i-home"></use></svg>
            <span><?= e(app_name()) ?></span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
            <svg aria-hidden="true"><use href="#i-menu"></use></svg>
            <span class="visually-hidden"><?= e(t('menu')) ?></span>
        </button>

        <nav id="primary-nav" class="primary-nav" aria-label="<?= e(t('main_nav')) ?>">
            <ul>
                <li><a href="<?= e(url('/')) ?>"<?= $navActive('/') ?>><?= e(t('nav_home')) ?></a></li>
                <li><a href="<?= e(url('properties', ['purpose' => 'sale'])) ?>"><?= e(t('nav_buy')) ?></a></li>
                <li><a href="<?= e(url('properties', ['purpose' => 'rent'])) ?>"><?= e(t('nav_rent')) ?></a></li>
                <li><a href="<?= e(url('properties')) ?>"<?= $navActive('/propert') ?>><?= e(t('nav_listings')) ?></a></li>
            </ul>
            <div class="nav-actions">
                <a class="lang-switch" href="<?= e(url('lang/' . (lang() === 'ar' ? 'en' : 'ar'))) ?>" lang="<?= lang() === 'ar' ? 'en' : 'ar' ?>" hreflang="<?= lang() === 'ar' ? 'en' : 'ar' ?>">
                    <?= lang() === 'ar' ? 'English' : 'العربية' ?>
                </a>
                <?php if ($user): ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('dashboard/properties/new')) ?>">
                        <svg aria-hidden="true"><use href="#i-plus"></use></svg><?= e(t('add_listing')) ?>
                    </a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('dashboard')) ?>"<?= $navActive('/dashboard') ?>>
                        <svg aria-hidden="true"><use href="#i-user"></use></svg><?= e(t('dashboard')) ?>
                    </a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin')) ?>"<?= $navActive('/admin') ?>><?= e(t('admin')) ?></a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('logout')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('logout')) ?></button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('login')) ?>"><?= e(t('login')) ?></a>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('register')) ?>"><?= e(t('register')) ?></a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

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
        </div>
        <nav aria-label="<?= e(t('footer_nav')) ?>">
            <h2 class="footer-title"><?= e(t('browse')) ?></h2>
            <ul>
                <li><a href="<?= e(url('properties', ['purpose' => 'sale'])) ?>"><?= e(t('for_sale')) ?></a></li>
                <li><a href="<?= e(url('properties', ['purpose' => 'rent'])) ?>"><?= e(t('for_rent')) ?></a></li>
                <li><a href="<?= e(url('properties', ['type' => 'villa'])) ?>"><?= e(t('type_villa')) ?></a></li>
                <li><a href="<?= e(url('properties', ['type' => 'apartment'])) ?>"><?= e(t('type_apartment')) ?></a></li>
                <li><a href="<?= e(url('properties', ['type' => 'land'])) ?>"><?= e(t('type_land')) ?></a></li>
            </ul>
        </nav>
        <div>
            <h2 class="footer-title"><?= e(t('list_with_us')) ?></h2>
            <p class="muted"><?= e(t('list_with_us_text')) ?></p>
            <a class="btn btn-outline btn-sm" href="<?= e(url('dashboard/properties/new')) ?>"><?= e(t('add_listing')) ?></a>
        </div>
    </div>
    <div class="container footer-bottom">
        <small>© <?= date('Y') ?> <?= e(app_name()) ?>. <?= e(t('rights')) ?></small>
    </div>
</footer>

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
