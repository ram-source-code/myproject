<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <div class="section-head">
            <h1 class="page-title"><?= e(is_admin() ? t('all_listings') : t('my_listings')) ?></h1>
            <?php if (can_post_listings(current_user())): ?><a class="btn btn-primary" href="<?= e(url('dashboard/properties/new')) ?>"><svg aria-hidden="true"><use href="#i-plus"></use></svg><?= e(t('add_listing')) ?></a><?php endif; ?>
        </div>

        <dl class="stat-tiles">
            <div><dt><?= e(t('stat_my_total')) ?></dt><dd><?= (int) $stats['total'] ?></dd></div>
            <div><dt><?= e(t('status_published')) ?></dt><dd><?= (int) $stats['published'] ?></dd></div>
            <div><dt><?= e(t('status_pending')) ?></dt><dd><?= (int) $stats['pending'] ?></dd></div>
            <div><dt><?= e(t('views')) ?></dt><dd><?= number_format((int) $stats['views']) ?></dd></div>
            <div><dt><?= e(t('unread_inquiries')) ?></dt><dd><a href="<?= e(url('dashboard/inquiries')) ?>"><?= (int) $stats['unread'] ?></a></dd></div>
        </dl>

        <nav class="tabs" aria-label="<?= e(t('filter_status')) ?>">
            <a href="<?= e(url('dashboard')) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>><?= e(t('all')) ?></a>
            <?php foreach (PROPERTY_STATUSES as $s): ?>
                <a href="<?= e(url('dashboard', ['status' => $s])) ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>><?= e(t('status_' . $s)) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($items): ?>
        <div class="table-wrap" role="region" aria-label="<?= e(t('my_listings')) ?>" tabindex="0">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('listing')) ?></th>
                        <th scope="col"><?= e(t('price')) ?></th>
                        <th scope="col"><?= e(t('status')) ?></th>
                        <th scope="col"><?= e(t('views')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $p): ?>
                    <tr>
                        <td data-label="<?= e(t('listing')) ?>">
                            <div class="row-listing">
                                <?php if ($p['cover_thumb']): ?>
                                    <img src="<?= e(upload_url($p['cover_thumb'])) ?>" alt="" width="72" height="48" loading="lazy">
                                <?php else: ?>
                                    <span class="thumb-empty" aria-hidden="true"><svg><use href="#i-image"></use></svg></span>
                                <?php endif; ?>
                                <div>
                                    <a href="<?= e(property_url($p)) ?>" class="row-title"><?= e($p['title']) ?></a>
                                    <p class="muted small"><?= e(t('type_' . $p['type'])) ?> · <?= e(city_name($p)) ?> · <?= (int) $p['image_count'] ?> <?= e(t('photos_short')) ?></p>
                                </div>
                            </div>
                        </td>
                        <td data-label="<?= e(t('price')) ?>"><?= e(price_label($p)) ?></td>
                        <td data-label="<?= e(t('status')) ?>"><span class="status status-<?= e($p['status']) ?>"><?= e(t('status_' . $p['status'])) ?></span></td>
                        <td data-label="<?= e(t('views')) ?>"><?= number_format((int) $p['views']) ?></td>
                        <td class="row-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('dashboard/properties/' . $p['id'] . '/edit')) ?>"><svg aria-hidden="true"><use href="#i-edit"></use></svg><?= e(t('edit')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></a>
                            <?php if (in_array($p['status'], ['published', 'sold'], true)): ?>
                            <form method="post" action="<?= e(url('dashboard/properties/' . $p['id'] . '/sold')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm" type="submit"><?= e($p['status'] === 'sold' ? t('mark_available') : t($p['purpose'] === 'rent' ? 'mark_rented' : 'mark_sold')) ?></button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('dashboard/properties/' . $p['id'] . '/delete')) ?>" data-confirm="<?= e(t('confirm_delete_listing')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm text-danger" type="submit"><svg aria-hidden="true"><use href="#i-trash"></use></svg><?= e(t('delete')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <svg aria-hidden="true"><use href="#i-home"></use></svg>
                <p><?= e(t('no_my_listings')) ?></p>
                <a class="btn btn-primary" href="<?= e(url('dashboard/properties/new')) ?>"><?= e(t('add_first_listing')) ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
