<?php /** @var ?array $o */ $v = static fn(string $k) => old($k, (string) ($o[$k] ?? '')); ?>
<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main narrow">
        <div class="section-head">
            <h1 class="page-title"><?= e(t('my_office')) ?></h1>
            <?php if ($o): ?><a class="btn btn-outline btn-sm" href="<?= e(url('office/' . $o['id'])) ?>"><svg aria-hidden="true"><use href="#i-eye"></use></svg><?= e(t('view_public_page')) ?></a><?php endif; ?>
        </div>
        <?php if ($o): ?>
            <dl class="stat-tiles">
                <div><dt><?= e(t('followers')) ?></dt><dd><?= (int) $o['followers'] ?></dd></div>
                <div><dt><?= e(t('status_published')) ?></dt><dd><?= (int) $o['listings'] ?></dd></div>
                <div><dt><?= e(t('verified')) ?></dt><dd><?= $o['is_verified'] ? '✓' : '—' ?></dd></div>
            </dl>
        <?php else: ?>
            <div class="alert alert-info"><svg aria-hidden="true"><use href="#i-alert"></use></svg><p><?= e(t('create_office_first')) ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('dashboard/office')) ?>" enctype="multipart/form-data" class="panel stack" novalidate data-validate>
            <?= csrf_field() ?>
            <div class="logo-field">
                <div class="office-logo office-logo-lg">
                    <?php if (!empty($o['logo'])): ?><img src="<?= e(upload_url($o['logo'])) ?>" alt="" width="112" height="112"><?php else: ?><span aria-hidden="true"><?= e(mb_substr($o['name'] ?? $u['name'], 0, 1)) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="logo"><?= e(t('office_logo')) ?></label>
                    <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
                    <p class="hint"><?= e(t('logo_hint')) ?></p>
                </div>
            </div>
            <div class="field">
                <label for="name"><?= e(t('office_name')) ?></label>
                <input id="name" name="name" required minlength="2" maxlength="100" value="<?= e($v('name')) ?>"<?= field_aria('name') ?>>
                <?= field_error('name') ?>
            </div>
            <div class="field">
                <label for="about"><?= e(t('office_about')) ?></label>
                <textarea id="about" name="about" rows="4" maxlength="2000"><?= e($v('about')) ?></textarea>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="phone"><?= e(t('phone')) ?></label>
                    <input id="phone" name="phone" type="tel" dir="ltr" maxlength="30" placeholder="0750 000 0000" value="<?= e($v('phone')) ?>"<?= field_aria('phone') ?>>
                    <?= field_error('phone') ?>
                </div>
                <div class="field">
                    <label for="whatsapp"><?= e(t('whatsapp')) ?></label>
                    <input id="whatsapp" name="whatsapp" type="tel" dir="ltr" maxlength="30" placeholder="0750 000 0000" value="<?= e($v('whatsapp')) ?>"<?= field_aria('whatsapp') ?>>
                    <?= field_error('whatsapp') ?>
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="city_id"><?= e(t('city')) ?></label>
                    <select id="city_id" name="city_id">
                        <option value=""><?= e(t('choose')) ?></option>
                        <?php foreach (cities() as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $v('city_id') === (string) $c['id'] ? 'selected' : '' ?>><?= e(city_name($c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="address"><?= e(t('field_address')) ?></label>
                    <input id="address" name="address" maxlength="200" value="<?= e($v('address')) ?>">
                </div>
            </div>
            <div><button class="btn btn-primary" type="submit"><?= e(t('save_changes')) ?></button></div>
        </form>
    </div>
</div>
