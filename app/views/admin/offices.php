<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <h1 class="page-title"><?= e(t('offices')) ?></h1>
        <div class="table-wrap" role="region" aria-label="<?= e(t('offices')) ?>" tabindex="0">
            <table class="table">
                <thead><tr>
                    <th scope="col"><?= e(t('office_name')) ?></th>
                    <th scope="col"><?= e(t('city')) ?></th>
                    <th scope="col"><?= e(t('admin_listings')) ?></th>
                    <th scope="col"><?= e(t('followers')) ?></th>
                    <th scope="col"><?= e(t('verified')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $o): ?>
                    <tr>
                        <td data-label="<?= e(t('office_name')) ?>"><a class="row-title" href="<?= e(url('office/' . $o['id'])) ?>"><?= e($o['name']) ?></a><p class="muted small" dir="ltr"><?= e($o['phone']) ?></p></td>
                        <td data-label="<?= e(t('city')) ?>"><?= e(city_name($o)) ?></td>
                        <td data-label="<?= e(t('admin_listings')) ?>"><?= (int) $o['listings'] ?></td>
                        <td data-label="<?= e(t('followers')) ?>"><?= (int) $o['followers'] ?></td>
                        <td data-label="<?= e(t('verified')) ?>">
                            <form method="post" action="<?= e(url('admin/offices/' . $o['id'] . '/verify')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm verify-toggle" type="submit" aria-pressed="<?= $o['is_verified'] ? 'true' : 'false' ?>">
                                    <svg aria-hidden="true"><use href="#i-verified"></use></svg><?= e($o['is_verified'] ? t('verified') : t('verify')) ?><span class="visually-hidden">: <?= e($o['name']) ?></span>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
