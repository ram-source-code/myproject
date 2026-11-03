<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <div class="section-head">
            <h1 class="page-title"><?= e(is_admin() ? t('all_listings') : t('my_listings')) ?></h1>
            <?php if (can_post_listings(current_user())): ?><a class="btn btn-primary" href="<?= e(url('dashboard/properties/new')) ?>"><svg aria-hidden="true"><use href="#i-plus"></use></svg><?= e(t('add_listing')) ?></a><?php endif; ?>
        </div>

        <?php if ($needCheck): ?>
            <section class="panel stale-panel" aria-labelledby="stale-h">
                <div class="section-head">
                    <h2 id="stale-h" class="panel-title"><svg class="inline-icon" aria-hidden="true"><use href="#i-clock"></use></svg> <?= e(t('stale_title')) ?></h2>
                    <?php if (count($needCheck) > 1): ?>
                        <form method="post" action="<?= e(url('dashboard/properties/confirm-all')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><svg aria-hidden="true"><use href="#i-check"></use></svg><?= e(t('confirm_all')) ?></button></form>
                    <?php endif; ?>
                </div>
                <p class="muted small"><?= e(t('stale_desc', ['days' => STALE_AFTER_DAYS])) ?></p>
                <ul class="stale-list">
                    <?php foreach ($needCheck as $c): ?>
                        <li>
                            <span><?= e($c['title']) ?><?php if ($c['status'] === 'expired'): ?> <span class="status status-expired"><?= e(t('status_expired')) ?></span><?php endif; ?></span>
                            <span class="btn-row">
                                <form method="post" action="<?= e(url('dashboard/properties/' . $c['id'] . '/confirm')) ?>"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit"><?= e(t('still_available')) ?><span class="visually-hidden">: <?= e($c['title']) ?></span></button></form>
                                <form method="post" action="<?= e(url('dashboard/properties/' . $c['id'] . '/sold')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= e(t('mark_sold')) ?><span class="visually-hidden">: <?= e($c['title']) ?></span></button></form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <dl class="stat-tiles">
            <div><dt><?= e(t('stat_my_total')) ?></dt><dd><?= (int) $stats['total'] ?></dd></div>
            <div><dt><?= e(t('status_published')) ?></dt><dd><?= (int) $stats['published'] ?></dd></div>
            <div><dt><?= e(t('status_pending')) ?></dt><dd><?= (int) $stats['pending'] ?></dd></div>
            <div><dt><?= e(t('unread_inquiries')) ?></dt><dd><a href="<?= e(url('dashboard/inquiries')) ?>"><?= (int) $stats['unread'] ?></a></dd></div>
        </dl>


        <section class="panel month-stats" aria-labelledby="month-h">
            <h2 id="month-h" class="panel-title"><?= e(t('last_30_days')) ?></h2>
            <dl class="stat-tiles">
                <div><dt><svg class="inline-icon" aria-hidden="true"><use href="#i-eye"></use></svg> <?= e(t('views')) ?></dt><dd><?= number_format($month['total']['views']) ?></dd></div>
                <div><dt><svg class="inline-icon" aria-hidden="true"><use href="#i-whatsapp"></use></svg> <?= e(t('whatsapp')) ?></dt><dd><?= number_format($month['total']['wa']) ?></dd></div>
                <div><dt><svg class="inline-icon" aria-hidden="true"><use href="#i-phone"></use></svg> <?= e(t('calls')) ?></dt><dd><?= number_format($month['total']['calls']) ?></dd></div>
                <div><dt><svg class="inline-icon" aria-hidden="true"><use href="#i-mail"></use></svg> <?= e(t('inquiries')) ?></dt><dd><?= number_format($month['total']['inquiries']) ?></dd></div>
            </dl>
            <p class="muted small"><?= e(t('stats_note')) ?></p>
        </section>

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
                        <th scope="col"><?= e(t('contacts_30')) ?></th>
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
                        <td data-label="<?= e(t('status')) ?>"><span class="status status-<?= e($p['status']) ?>"><?= e(t('status_' . $p['status'])) ?></span><?php if ($p['is_featured']): ?> <span class="badge badge-featured small"><svg aria-hidden="true"><use href="#i-star"></use></svg><?= e($p['featured_until'] ? t('featured_until', ['date' => substr($p['featured_until'], 0, 10)]) : t('featured')) ?></span><?php endif; ?></td>
                        <td data-label="<?= e(t('views')) ?>"><?= number_format((int) $p['views']) ?></td>
                        <?php $ms = $month[(int) $p['id']] ?? ['wa' => 0, 'calls' => 0, 'inquiries' => 0, 'views' => 0]; ?>
                        <td data-label="<?= e(t('contacts_30')) ?>"><span title="<?= e(t('whatsapp') . ' ' . $ms['wa'] . ' · ' . t('calls') . ' ' . $ms['calls'] . ' · ' . t('inquiries') . ' ' . $ms['inquiries']) ?>"><?= $ms['wa'] + $ms['calls'] + $ms['inquiries'] ?></span></td>
                        <td class="row-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('dashboard/properties/' . $p['id'] . '/edit')) ?>"><svg aria-hidden="true"><use href="#i-edit"></use></svg><?= e(t('edit')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></a>
                            <?php if ($p['status'] === 'expired'): ?>
                            <form method="post" action="<?= e(url('dashboard/properties/' . $p['id'] . '/confirm')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline btn-sm" type="submit"><?= e(t('still_available')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></button>
                            </form>
                            <?php endif; ?>
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
