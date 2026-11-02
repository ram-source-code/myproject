<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <div class="section-head">
            <h1 class="page-title"><?= e(t('admin_listings')) ?></h1>
            <form method="get" action="<?= e(url('admin/properties')) ?>" class="inline-search" role="search">
                <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                <label for="aq" class="visually-hidden"><?= e(t('search')) ?></label>
                <input id="aq" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search')) ?>">
                <button class="btn btn-outline btn-sm" type="submit"><svg aria-hidden="true"><use href="#i-search"></use></svg><span class="visually-hidden"><?= e(t('search')) ?></span></button>
            </form>
        </div>
        <nav class="tabs" aria-label="<?= e(t('filter_status')) ?>">
            <a href="<?= e(url('admin/properties', ['q' => $q])) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>><?= e(t('all')) ?></a>
            <?php foreach (PROPERTY_STATUSES as $s): ?>
                <a href="<?= e(url('admin/properties', ['status' => $s, 'q' => $q])) ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>><?= e(t('status_' . $s)) ?></a>
            <?php endforeach; ?>
        </nav>
        <p class="muted" role="status"><?= e(t('results_count', ['n' => $pg['total']])) ?></p>

        <div class="table-wrap" role="region" aria-label="<?= e(t('admin_listings')) ?>" tabindex="0">
            <table class="table">
                <thead><tr>
                    <th scope="col"><?= e(t('listing')) ?></th>
                    <th scope="col"><?= e(t('owner')) ?></th>
                    <th scope="col"><?= e(t('price')) ?></th>
                    <th scope="col"><?= e(t('status')) ?></th>
                    <th scope="col"><?= e(t('featured')) ?></th>
                    <th scope="col"><span class="visually-hidden"><?= e(t('actions')) ?></span></th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $p): ?>
                    <tr>
                        <td data-label="<?= e(t('listing')) ?>">
                            <a class="row-title" href="<?= e(property_url($p)) ?>"><?= e($p['title']) ?></a>
                            <p class="muted small">#<?= (int) $p['id'] ?> · <?= e(t('type_' . $p['type'])) ?> · <?= e(city_name($p)) ?></p>
                        </td>
                        <td data-label="<?= e(t('owner')) ?>"><?= e($p['owner_name']) ?></td>
                        <td data-label="<?= e(t('price')) ?>"><?= e(price_label($p)) ?></td>
                        <td data-label="<?= e(t('status')) ?>">
                            <form method="post" action="<?= e(url('admin/properties/' . $p['id'] . '/status')) ?>" class="inline-select" data-autosubmit>
                                <?= csrf_field() ?>
                                <label for="st-<?= (int) $p['id'] ?>" class="visually-hidden"><?= e(t('status')) ?>: <?= e($p['title']) ?></label>
                                <select id="st-<?= (int) $p['id'] ?>" name="status">
                                    <?php foreach (PROPERTY_STATUSES as $s): ?>
                                        <option value="<?= $s ?>" <?= $p['status'] === $s ? 'selected' : '' ?>><?= e(t('status_' . $s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <noscript><button class="btn btn-sm btn-outline" type="submit"><?= e(t('apply')) ?></button></noscript>
                            </form>
                        </td>
                        <td data-label="<?= e(t('featured')) ?>">
                            <form method="post" action="<?= e(url('admin/properties/' . $p['id'] . '/feature')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm star-toggle" type="submit" aria-pressed="<?= $p['is_featured'] ? 'true' : 'false' ?>">
                                    <svg aria-hidden="true"><use href="#i-star"></use></svg><span class="visually-hidden"><?= e(t('featured')) ?>: <?= e($p['title']) ?></span>
                                </button>
                            </form>
                        </td>
                        <td class="row-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('dashboard/properties/' . $p['id'] . '/edit')) ?>"><?= e(t('edit')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></a>
                            <form method="post" action="<?= e(url('dashboard/properties/' . $p['id'] . '/delete')) ?>" data-confirm="<?= e(t('confirm_delete_listing')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('delete')) ?><span class="visually-hidden">: <?= e($p['title']) ?></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_partial('partials/pagination', ['pg' => $pg, 'base' => 'admin/properties', 'query' => ['status' => $status, 'q' => $q]]) ?>
    </div>
</div>
