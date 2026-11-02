<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main narrow">
        <h1 class="page-title"><?= e(t('profile')) ?></h1>

        <section class="panel" aria-labelledby="pi-h">
            <h2 id="pi-h" class="panel-title"><?= e(t('personal_info')) ?></h2>
            <form method="post" action="<?= e(url('dashboard/profile')) ?>" class="stack" novalidate data-validate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="name"><?= e(t('full_name')) ?></label>
                    <input id="name" name="name" required minlength="2" maxlength="100" autocomplete="name" value="<?= e(old('name', $u['name'])) ?>"<?= field_aria('name') ?>>
                    <?= field_error('name') ?>
                </div>
                <div class="field">
                    <label for="email"><?= e(t('email')) ?></label>
                    <input id="email" type="email" value="<?= e($u['email']) ?>" dir="ltr" readonly aria-describedby="email-hint">
                    <p class="hint" id="email-hint"><?= e(t('email_readonly')) ?></p>
                </div>
                <div class="field">
                    <label for="phone"><?= e(t('phone')) ?></label>
                    <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" dir="ltr" value="<?= e(old('phone', $u['phone'] ?? '')) ?>"<?= field_aria('phone') ?>>
                    <p class="hint"><?= e(t('phone_public_hint')) ?></p>
                    <?= field_error('phone') ?>
                </div>
                <div><button class="btn btn-primary" type="submit"><?= e(t('save_changes')) ?></button></div>
            </form>
        </section>

        <section class="panel" aria-labelledby="pw-h" id="password">
            <h2 id="pw-h" class="panel-title"><?= e(t('change_password')) ?></h2>
            <form method="post" action="<?= e(url('dashboard/password')) ?>" class="stack" novalidate data-validate>
                <?= csrf_field() ?>
                <input type="text" name="username" value="<?= e($u['email']) ?>" autocomplete="username" hidden>
                <div class="field">
                    <label for="current_password"><?= e(t('current_password')) ?></label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" dir="ltr"<?= field_aria('current_password') ?>>
                    <?= field_error('current_password') ?>
                </div>
                <div class="field">
                    <label for="password"><?= e(t('new_password')) ?></label>
                    <input id="password" name="password" type="password" required minlength="8" maxlength="128" autocomplete="new-password" dir="ltr"<?= field_aria('password') ?>>
                    <?= field_error('password') ?>
                </div>
                <div class="field">
                    <label for="password_confirm"><?= e(t('password_confirm')) ?></label>
                    <input id="password_confirm" name="password_confirm" type="password" required minlength="8" maxlength="128" autocomplete="new-password" dir="ltr" data-match="password"<?= field_aria('password_confirm') ?>>
                    <?= field_error('password_confirm') ?>
                </div>
                <div><button class="btn btn-primary" type="submit"><?= e(t('update_password')) ?></button></div>
            </form>
        </section>
    </div>
</div>
