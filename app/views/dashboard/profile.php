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
        <section class="panel stack" aria-labelledby="tf-h" id="twofa">
            <h2 id="tf-h" class="panel-title"><?= e(t('twofa_title')) ?></h2>
            <?php $tf = user_2fa((int) $u['id']); ?>
            <?php if (!$tf): ?>
                <p class="muted"><?= e(t('twofa_why')) ?><?php if ($u['role'] === 'admin'): ?> <strong><?= e(t('twofa_admin_recommended')) ?></strong><?php endif; ?></p>
                <p><a class="btn btn-primary" href="<?= e(url('dashboard/2fa')) ?>"><svg aria-hidden="true"><use href="#i-key"></use></svg><?= e(t('twofa_set_up')) ?></a></p>
            <?php else: ?>
                <p><span class="status status-published"><?= e(t('twofa_is_on')) ?></span> <span class="muted small"><?= e(t('twofa_codes_left', ['n' => count((array) json_decode($tf['recovery_codes'], true))])) ?></span></p>
                <form method="post" action="<?= e(url('dashboard/2fa/new-codes')) ?>" class="activation-form">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="tf-pw"><?= e(t('current_password')) ?></label>
                        <input id="tf-pw" name="password" type="password" required autocomplete="current-password" dir="ltr"<?= field_aria('twofa_password') ?>>
                        <?= field_error('twofa_password') ?>
                    </div>
                    <button class="btn btn-outline" type="submit"><?= e(t('twofa_new_codes')) ?></button>
                    <button class="btn btn-ghost text-danger" type="submit" formaction="<?= e(url('dashboard/2fa/disable')) ?>" data-confirm-click="<?= e(t('twofa_off_confirm')) ?>"><?= e(t('twofa_turn_off')) ?></button>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="data-h" id="delete">
            <h2 id="data-h" class="panel-title"><?= e(t('your_data')) ?></h2>
            <p class="muted"><?= e(t('your_data_desc')) ?> <a href="<?= e(url('privacy')) ?>"><?= e(t('privacy_policy')) ?></a></p>
            <p><a class="btn btn-outline" href="<?= e(url('dashboard/export')) ?>" download><svg aria-hidden="true"><use href="#i-download"></use></svg><?= e(t('export_data')) ?></a></p>
            <details class="danger-details">
                <summary class="btn btn-ghost text-danger"><svg aria-hidden="true"><use href="#i-trash"></use></svg><?= e(t('delete_account')) ?></summary>
                <form method="post" action="<?= e(url('dashboard/delete-account')) ?>" class="stack" data-confirm="<?= e(t('delete_account_confirm')) ?>">
                    <?= csrf_field() ?>
                    <p class="muted"><?= e(t('delete_account_desc')) ?></p>
                    <div class="field">
                        <label for="delete_password"><?= e(t('current_password')) ?></label>
                        <input id="delete_password" name="password" type="password" required autocomplete="current-password" dir="ltr"<?= field_aria('delete_password') ?>>
                        <?= field_error('delete_password') ?>
                    </div>
                    <div><button class="btn btn-danger" type="submit"><?= e(t('delete_account')) ?></button></div>
                </form>
            </details>
        </section>
    </div>
</div>
