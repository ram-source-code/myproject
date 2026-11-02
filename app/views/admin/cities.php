<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main narrow">
        <h1 class="page-title"><?= e(t('admin_cities')) ?></h1>
        <section class="panel" aria-labelledby="addc-h">
            <h2 id="addc-h" class="panel-title"><?= e(t('add_city')) ?></h2>
            <form method="post" action="<?= e(url('admin/cities')) ?>" class="grid-3 align-end" novalidate data-validate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="name_ar"><?= e(t('name_ar')) ?></label>
                    <input id="name_ar" name="name_ar" required maxlength="80" lang="ar" dir="rtl">
                </div>
                <div class="field">
                    <label for="name_en"><?= e(t('name_en')) ?></label>
                    <input id="name_en" name="name_en" required maxlength="80" lang="en" dir="ltr"<?= field_aria('name_en') ?>>
                    <?= field_error('name_en') ?>
                </div>
                <div><button class="btn btn-primary" type="submit"><?= e(t('add')) ?></button></div>
            </form>
        </section>
        <div class="table-wrap" role="region" aria-label="<?= e(t('admin_cities')) ?>" tabindex="0">
            <table class="table">
                <thead><tr><th scope="col"><?= e(t('name_ar')) ?></th><th scope="col"><?= e(t('name_en')) ?></th><th scope="col"><?= e(t('admin_listings')) ?></th><th scope="col"><span class="visually-hidden"><?= e(t('actions')) ?></span></th></tr></thead>
                <tbody>
                <?php foreach ($items as $c): ?>
                    <tr>
                        <td data-label="<?= e(t('name_ar')) ?>" lang="ar"><?= e($c['name_ar']) ?></td>
                        <td data-label="<?= e(t('name_en')) ?>" lang="en"><?= e($c['name_en']) ?></td>
                        <td data-label="<?= e(t('admin_listings')) ?>"><?= (int) $c['n'] ?></td>
                        <td class="row-actions">
                            <form method="post" action="<?= e(url('admin/cities/' . $c['id'] . '/delete')) ?>" data-confirm="<?= e(t('confirm_delete_city')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('delete')) ?><span class="visually-hidden">: <?= e($c['name_en']) ?></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
