<?php
$active = $active ?? '';
$a = static fn(string $k) => $active === $k ? ' aria-current="page"' : '';
$isAdmin = is_admin();
?>
<nav class="dash-nav" aria-label="<?= e(t('dashboard_nav')) ?>">
    <ul>
        <li><a href="<?= e(url('dashboard')) ?>"<?= $a('listings') ?>><?= e(t('my_listings')) ?></a></li>
        <li><a href="<?= e(url('dashboard/properties/new')) ?>"<?= $a('new') ?>><?= e(t('add_listing')) ?></a></li>
        <li><a href="<?= e(url('dashboard/inquiries')) ?>"<?= $a('inquiries') ?>><?= e(t('inquiries')) ?></a></li>
        <li><a href="<?= e(url('dashboard/favorites')) ?>"<?= $a('favorites') ?>><?= e(t('favorites')) ?></a></li>
        <li><a href="<?= e(url('dashboard/profile')) ?>"<?= $a('profile') ?>><?= e(t('profile')) ?></a></li>
        <?php if ($isAdmin): ?>
            <li class="dash-nav-sep"><a href="<?= e(url('admin')) ?>"<?= $a('admin') ?>><?= e(t('admin_overview')) ?></a></li>
            <li><a href="<?= e(url('admin/properties')) ?>"<?= $a('admin_listings') ?>><?= e(t('admin_listings')) ?></a></li>
            <li><a href="<?= e(url('admin/users')) ?>"<?= $a('admin_users') ?>><?= e(t('admin_users')) ?></a></li>
            <li><a href="<?= e(url('admin/cities')) ?>"<?= $a('admin_cities') ?>><?= e(t('admin_cities')) ?></a></li>
        <?php endif; ?>
    </ul>
</nav>
