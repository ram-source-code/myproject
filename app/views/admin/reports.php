<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <h1 class="page-title"><?= e(t('reports')) ?></h1>

        <section class="panel" aria-labelledby="rep-h">
            <h2 id="rep-h" class="panel-title"><?= e(t('reported_listings')) ?> (<?= count($reports) ?>)</h2>
            <?php if ($reports): ?>
                <ul class="review-list">
                    <?php foreach ($reports as $p): ?>
                        <li>
                            <div>
                                <a href="<?= e(property_url($p)) ?>"><?= e($p['title']) ?></a>
                                <span class="badge badge-danger"><?= e(t('n_reports', ['n' => (int) $p['open_reports']])) ?></span>
                                <p class="muted small"><?= e($p['office_name'] ?? $p['owner_name']) ?> · <?= e(price_label($p)) ?></p>
                                <p class="small"><?= e(strtr((string) $p['summary'], array_combine(REPORT_REASONS, array_map(static fn($r) => t('report_' . $r), REPORT_REASONS)))) ?></p>
                            </div>
                            <div class="row-actions">
                                <form method="post" action="<?= e(url('admin/reports/' . $p['id'] . '/resolve')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="keep">
                                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('keep_listing')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></button>
                                </form>
                                <form method="post" action="<?= e(url('admin/reports/' . $p['id'] . '/resolve')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="unpublish">
                                    <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('unpublish')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?><p class="muted"><?= e(t('no_reports')) ?></p><?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="dups-h" id="dups">
            <h2 id="dups-h" class="panel-title"><?= e(t('possible_duplicates')) ?> (<?= count($dups) ?>)</h2>
            <p class="muted small"><?= e(t('duplicates_desc')) ?></p>
            <?php if ($dups): ?>
                <ul class="review-list">
                    <?php foreach ($dups as $d): ?>
                        <li>
                            <div>
                                <p><a href="<?= e(property_url(['id' => $d['property_id'], 'title' => $d['a_title']])) ?>">#<?= (int) $d['property_id'] ?> <?= e($d['a_title']) ?></a> <span class="muted small">(<?= e($d['a_owner']) ?> · <?= e(t('status_' . $d['a_status'])) ?>)</span></p>
                                <p class="muted small"><svg class="inline-icon" aria-hidden="true"><use href="#i-copy"></use></svg>
                                    <?= e($d['reason'] === 'photo' ? t('dup_photo', ['d' => (int) $d['distance']]) : t('dup_location')) ?>
                                    <a href="<?= e(property_url(['id' => $d['other_id'], 'title' => $d['b_title']])) ?>">#<?= (int) $d['other_id'] ?> <?= e($d['b_title']) ?></a>
                                    (<?= e($d['b_owner']) ?>)</p>
                            </div>
                            <div class="row-actions">
                                <form method="post" action="<?= e(url('admin/duplicates/' . $d['property_id'] . '/' . $d['other_id'] . '/dismiss')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="dismiss">
                                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('not_duplicate')) ?></button>
                                </form>
                                <form method="post" action="<?= e(url('admin/duplicates/' . $d['property_id'] . '/' . $d['other_id'] . '/dismiss')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="reject">
                                    <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('reject_copy', ['id' => (int) $d['property_id']])) ?></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?><p class="muted"><?= e(t('no_duplicates')) ?></p><?php endif; ?>
        </section>
    </div>
</div>
