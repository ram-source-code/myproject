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
        <?php if (in_array(current_user()['role'] ?? '', ['agent', 'admin'], true)): ?>
            <li><a href="<?= e(url('dashboard/office')) ?>"<?= $a('office') ?>><?= e(t('my_office')) ?></a></li>
            <li><a href="<?= e(url('dashboard/notifications')) ?>"<?= $a('notifications') ?>><?= e(t('send_notification')) ?></a></li>
        <?php endif; ?>
        <li><a href="<?= e(url('dashboard/profile')) ?>"<?= $a('profile') ?>><?= e(t('profile')) ?></a></li>
        <?php if ($isAdmin): ?>
            <li class="dash-nav-sep"><a href="<?= e(url('admin')) ?>"<?= $a('admin') ?>><?= e(t('admin_overview')) ?></a></li>
            <li><a href="<?= e(url('admin/properties')) ?>"<?= $a('admin_listings') ?>><?= e(t('admin_listings')) ?></a></li>
            <li><a href="<?= e(url('admin/users')) ?>"<?= $a('admin_users') ?>><?= e(t('admin_users')) ?></a></li>
            <li><a href="<?= e(url('admin/reports')) ?>"<?= $a('admin_reports') ?>><?= e(t('reports')) ?></a></li>
            <li><a href="<?= e(url('admin/offices')) ?>"<?= $a('admin_offices') ?>><?= e(t('offices')) ?></a></li>
            <li><a href="<?= e(url('admin/notifications')) ?>"<?= $a('admin_notifications') ?>><?= e(t('broadcast')) ?></a></li>
            <li><a href="<?= e(url('admin/cities')) ?>"<?= $a('admin_cities') ?>><?= e(t('admin_cities')) ?></a></li>
        <?php endif; ?>
    </ul>
</nav>
