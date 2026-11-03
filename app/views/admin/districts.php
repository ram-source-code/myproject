<?php /** @var array $city @var array $items @var array $suggest */ ?>
<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <p><a href="<?= e(url('admin/cities')) ?>">← <?= e(t('admin_cities')) ?></a></p>
        <h1 class="page-title"><?= e(t('districts_of', ['city' => city_name($city)])) ?></h1>
        <p class="muted"><?= e(t('districts_help')) ?></p>

        <section class="panel" aria-labelledby="addd-h">
            <h2 id="addd-h" class="panel-title"><?= e(t('add_district')) ?></h2>
            <form method="post" action="<?= e(url('admin/districts')) ?>" class="grid-4 align-end" novalidate data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="city_id" value="<?= (int) $city['id'] ?>">
                <div class="field">
                    <label for="d-ku"><?= e(t('name_ku')) ?></label>
                    <input id="d-ku" name="name_ku" required maxlength="80" lang="ckb" dir="rtl" value="<?= e(old('name_ku')) ?>">
                </div>
                <div class="field">
                    <label for="d-ar"><?= e(t('name_ar')) ?></label>
                    <input id="d-ar" name="name_ar" required maxlength="80" lang="ar" dir="rtl" value="<?= e(old('name_ar')) ?>">
                </div>
                <div class="field">
                    <label for="d-en"><?= e(t('name_en')) ?></label>
                    <input id="d-en" name="name_en" required maxlength="80" lang="en" dir="ltr" value="<?= e(old('name_en')) ?>"<?= field_aria('name_en') ?>>
                    <?= field_error('name_en') ?>
                </div>
                <div><button class="btn btn-primary" type="submit"><?= e(t('add')) ?></button></div>
            </form>
        </section>

        <?php if ($suggest): ?>
            <section class="panel" aria-labelledby="sug-h">
                <h2 id="sug-h" class="panel-title"><?= e(t('district_suggestions')) ?></h2>
                <p class="muted small"><?= e(t('district_suggestions_desc')) ?></p>
                <ul class="stale-list">
                    <?php foreach ($suggest as $s): ?>
                        <li>
                            <span><strong><?= e($s['district']) ?></strong> <span class="muted small"><?= e(t('n_listings', ['n' => (int) $s['n']])) ?></span></span>
                            <form method="post" action="<?= e(url('admin/districts')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="city_id" value="<?= (int) $city['id'] ?>">
                                <?php foreach (['name_ku', 'name_ar', 'name_en'] as $k): ?><input type="hidden" name="<?= $k ?>" value="<?= e($s['district']) ?>"><?php endforeach; ?>
                                <button class="btn btn-outline btn-sm" type="submit"><?= e(t('add')) ?><span class="visually-hidden">: <?= e($s['district']) ?></span></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($items): ?>
        <div class="table-wrap" role="region" aria-label="<?= e(t('districts_of', ['city' => city_name($city)])) ?>" tabindex="0">
            <table class="table">
                <thead><tr><th scope="col"><?= e(t('name_ku')) ?> · <?= e(t('name_ar')) ?> · <?= e(t('name_en')) ?></th><th scope="col"><?= e(t('admin_listings')) ?></th><th scope="col"><span class="visually-hidden"><?= e(t('actions')) ?></span></th></tr></thead>
                <tbody>
                <?php foreach ($items as $d): ?>
                    <tr>
                        <td data-label="<?= e(t('name_en')) ?>">
                            <form method="post" action="<?= e(url('admin/districts/' . $d['id'])) ?>" class="district-edit">
                                <?= csrf_field() ?>
                                <label class="visually-hidden" for="ku-<?= (int) $d['id'] ?>"><?= e(t('name_ku')) ?></label>
                                <input id="ku-<?= (int) $d['id'] ?>" name="name_ku" value="<?= e($d['name_ku']) ?>" maxlength="80" lang="ckb" dir="rtl" required>
                                <label class="visually-hidden" for="ar-<?= (int) $d['id'] ?>"><?= e(t('name_ar')) ?></label>
                                <input id="ar-<?= (int) $d['id'] ?>" name="name_ar" value="<?= e($d['name_ar']) ?>" maxlength="80" lang="ar" dir="rtl" required>
                                <label class="visually-hidden" for="en-<?= (int) $d['id'] ?>"><?= e(t('name_en')) ?></label>
                                <input id="en-<?= (int) $d['id'] ?>" name="name_en" value="<?= e($d['name_en']) ?>" maxlength="80" lang="en" dir="ltr" required>
                                <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('save')) ?><span class="visually-hidden">: <?= e($d['name_en']) ?></span></button>
                            </form>
                        </td>
                        <td data-label="<?= e(t('admin_listings')) ?>"><?= (int) $d['n'] ?></td>
                        <td class="row-actions">
                            <form method="post" action="<?= e(url('admin/districts/' . $d['id'] . '/delete')) ?>" data-confirm="<?= e(t('confirm_delete_district')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('delete')) ?><span class="visually-hidden">: <?= e($d['name_en']) ?></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
