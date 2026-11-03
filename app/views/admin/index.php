<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <h1 class="page-title"><?= e(t('admin_overview')) ?></h1>
        <dl class="stat-tiles">
            <div><dt><?= e(t('admin_users')) ?></dt><dd><?= number_format((int) $stats['users']) ?></dd></div>
            <div><dt><?= e(t('admin_listings')) ?></dt><dd><?= number_format((int) $stats['properties']) ?></dd></div>
            <div><dt><?= e(t('status_published')) ?></dt><dd><?= number_format((int) $stats['published']) ?></dd></div>
            <div><dt><?= e(t('status_pending')) ?></dt><dd><a href="<?= e(url('admin/properties', ['status' => 'pending'])) ?>"><?= number_format((int) $stats['pending']) ?></a></dd></div>
            <div><dt><?= e(t('inquiries')) ?></dt><dd><?= number_format((int) $stats['inquiries']) ?></dd></div>
            <div><dt><?= e(t('views')) ?></dt><dd><?= number_format((int) $stats['views']) ?></dd></div>
            <div><dt><?= e(t('reported_listings')) ?></dt><dd><a href="<?= e(url('admin/reports')) ?>"><?= (int) $stats['reported'] ?></a></dd></div>
            <div><dt><?= e(t('possible_duplicates')) ?></dt><dd><a href="<?= e(url('admin/reports') . '#dups') ?>"><?= (int) $stats['dups'] ?></a></dd></div>
        </dl>

        <div class="grid-2 align-start">
            <section class="panel" aria-labelledby="pend-h">
                <h2 id="pend-h" class="panel-title"><?= e(t('awaiting_review')) ?></h2>
                <?php if ($pending): ?>
                    <ul class="review-list">
                        <?php foreach ($pending as $p): ?>
                            <li>
                                <div>
                                    <a href="<?= e(property_url($p)) ?>"><?= e($p['title']) ?></a>
                                    <p class="muted small"><?= e($p['owner_name']) ?> · <?= e(price_label($p)) ?></p>
                                </div>
                                <div class="row-actions">
                                    <form method="post" action="<?= e(url('admin/properties/' . $p['id'] . '/status')) ?>">
                                        <?= csrf_field() ?><input type="hidden" name="status" value="published">
                                        <button class="btn btn-primary btn-sm" type="submit"><?= e(t('approve')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></button>
                                    </form>
                                    <form method="post" action="<?= e(url('admin/properties/' . $p['id'] . '/status')) ?>">
                                        <?= csrf_field() ?><input type="hidden" name="status" value="rejected">
                                        <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('reject')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted"><?= e(t('nothing_pending')) ?></p>
                <?php endif; ?>
            </section>

            <section class="panel" aria-labelledby="city-h">
                <h2 id="city-h" class="panel-title"><?= e(t('listings_by_city')) ?></h2>
                <?php $max = max(1, ...array_map(static fn($r) => (int) $r['n'], $byCity ?: [['n' => 1]])); ?>
                <ul class="bar-list">
                    <?php foreach ($byCity as $r): ?>
                        <li>
                            <span class="bar-label"><?= e(city_name($r)) ?></span>
                            <meter min="0" max="<?= $max ?>" value="<?= (int) $r['n'] ?>" aria-label="<?= e(city_name($r)) ?>"></meter>
                            <span class="bar-value"><?= (int) $r['n'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="muted small"><?= e(t('db_mode')) ?>: <code><?= e($journal) ?></code> · <?= e(t('photos')) ?>: <?= (int) $stats['images'] ?></p>
            </section>
        </div>
        <div class="grid-2 align-start">
            <section class="panel" aria-labelledby="bk-h">
                <h2 id="bk-h" class="panel-title"><?= e(t('backup')) ?></h2>
                <p class="muted"><?= e(t('backup_desc')) ?></p>
                <form method="post" action="<?= e(url('admin/backup')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit"><svg aria-hidden="true"><use href="#i-download"></use></svg><?= e(t('download_backup')) ?></button>
                </form>
                <h3 class="small-heading"><?= e(t('auto_backups')) ?></h3>
                <?php $backups = glob(backups_dir() . '/aqarat-*.sqlite') ?: []; rsort($backups); ?>
                <?php if ($backups): ?>
                    <ul class="backup-list">
                        <?php foreach ($backups as $b): ?>
                            <li>
                                <span dir="ltr"><?= e(substr(basename($b), 7, 10)) ?></span>
                                <span class="muted small" dir="ltr"><?= e(number_format(filesize($b) / 1048576, 1)) ?> MB</span>
                                <form method="post" action="<?= e(url('admin/backups/download')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="file" value="<?= e(basename($b)) ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit"><svg aria-hidden="true"><use href="#i-download"></use></svg><?= e(t('download')) ?><span class="visually-hidden"> <?= e(substr(basename($b), 7, 10)) ?></span></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted small"><?= e(t('auto_backups_none')) ?></p>
                <?php endif; ?>
            </section>
            <section class="panel" aria-labelledby="log-h">
                <h2 id="log-h" class="panel-title"><?= e(t('audit_log')) ?></h2>
                <?php if ($log): ?>
                    <ul class="notif-list audit-list">
                        <?php foreach ($log as $l): ?>
                            <li><code><?= e($l['action']) ?></code> <?= e($l['target']) ?>
                                <p class="muted small"><?= e($l['name'] ?? '—') ?> · <time datetime="<?= e($l['created_at']) ?>"><?= e($l['created_at']) ?></time></p></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?><p class="muted"><?= e(t('nothing_logged')) ?></p><?php endif; ?>
            </section>
        </div>
    </div>
</div>
