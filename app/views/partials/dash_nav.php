<?php
$active = $active ?? '';
$a = static fn(string $k) => $active === $k ? ' aria-current="page"' : '';
$isAdmin = is_admin();
$canPost = can_post_listings(current_user());
?>
<nav class="dash-nav" aria-label="<?= e(t('dashboard_nav')) ?>">
    <ul>
        <?php if ($canPost || $isAdmin): ?>
            <li><a href="<?= e(url('dashboard')) ?>"<?= $a('listings') ?>><?= e(t('my_listings')) ?></a></li>
        <?php endif; ?>
        <?php if ($canPost): ?>
            <li><a href="<?= e(url('dashboard/properties/new')) ?>"<?= $a('new') ?>><?= e(t('add_listing')) ?></a></li>
            <li><a href="<?= e(url('dashboard/inquiries')) ?>"<?= $a('inquiries') ?>><?= e(t('inquiries')) ?></a></li>
        <?php endif; ?>
        <li><a href="<?= e(url('dashboard/favorites')) ?>"<?= $a('favorites') ?>><?= e(t('favorites')) ?></a></li>
        <?php if (office_state(current_user()) !== 'none'): ?>
            <li><a href="<?= e(url('dashboard/office')) ?>"<?= $a('office') ?>><?= e(t('my_office')) ?></a></li>
            <?php if ($canPost): ?><li><a href="<?= e(url('dashboard/notifications')) ?>"<?= $a('notifications') ?>><?= e(t('send_notification')) ?></a></li><?php endif; ?>
        <?php else: ?>
            <li><a href="<?= e(url('dashboard/office')) ?>"<?= $a('office') ?>><?= e(t('open_office')) ?></a></li>
        <?php endif; ?>
        <li><a href="<?= e(url('dashboard/profile')) ?>"<?= $a('profile') ?>><?= e(t('profile')) ?></a></li>
        <?php if ($isAdmin): ?>
            <li class="dash-nav-sep"><a href="<?= e(url('admin')) ?>"<?= $a('admin') ?>><?= e(t('admin_overview')) ?></a></li>
            <li><a href="<?= e(url('admin/properties')) ?>"<?= $a('admin_listings') ?>><?= e(t('admin_listings')) ?></a></li>
            <li><a href="<?= e(url('admin/users')) ?>"<?= $a('admin_users') ?>><?= e(t('admin_users')) ?></a></li>
            <li><a href="<?= e(url('admin/reports')) ?>"<?= $a('admin_reports') ?>><?= e(t('reports')) ?></a></li>
            <?php $waiting = (int) db()->query("SELECT COUNT(*) FROM offices WHERE activated_at IS NULL OR (active_until IS NOT NULL AND active_until <= datetime('now'))")->fetchColumn(); ?>
            <li><a href="<?= e(url('admin/offices')) ?>"<?= $a('admin_offices') ?>><?= e(t('offices')) ?><?php if ($waiting): ?> <span class="count-badge" aria-label="<?= e(t('pending_offices', ['n' => $waiting])) ?>"><?= $waiting ?></span><?php endif; ?></a></li>
            <li><a href="<?= e(url('admin/notifications')) ?>"<?= $a('admin_notifications') ?>><?= e(t('broadcast')) ?></a></li>
            <li><a href="<?= e(url('admin/cities')) ?>"<?= $a('admin_cities') ?>><?= e(t('admin_cities')) ?></a></li>
        <?php endif; ?>
    </ul>
</nav>
