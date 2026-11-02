<div class="container auth-wrap">
    <section class="panel auth-card" aria-labelledby="reg-h">
        <h1 id="reg-h" class="page-title"><?= e(t('register')) ?></h1>
        <p class="muted"><?= e(t('register_sub')) ?></p>
        <form method="post" action="<?= e(url('register')) ?>" class="stack" novalidate data-validate>
            <?= csrf_field() ?>
            <?= form_time_field() ?>
            <div class="field">
                <label for="name"><?= e(t('full_name')) ?></label>
                <input id="name" name="name" required minlength="2" maxlength="100" autocomplete="name" autofocus value="<?= e(old('name')) ?>"<?= field_aria('name') ?>>
                <?= field_error('name') ?>
            </div>
            <div class="field">
                <label for="email"><?= e(t('email')) ?></label>
                <input id="email" name="email" type="email" required maxlength="150" autocomplete="email" dir="ltr" value="<?= e(old('email')) ?>"<?= field_aria('email') ?>>
                <?= field_error('email') ?>
            </div>
            <div class="field">
                <label for="phone"><?= e(t('phone')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" dir="ltr" placeholder="0750 000 0000" value="<?= e(old('phone')) ?>"<?= field_aria('phone') ?>>
                <p class="hint"><?= e(t('phone_hint')) ?></p>
                <?= field_error('phone') ?>
            </div>
            <?php $defRole = old('role', ($_GET['type'] ?? '') === 'office' ? 'agent' : 'user'); ?>
            <fieldset class="segmented segmented-full">
                <legend><?= e(t('account_type')) ?></legend>
                <label><input type="radio" name="role" value="user" data-role <?= $defRole === 'user' ? 'checked' : '' ?>><span><?= e(t('role_user')) ?></span></label>
                <label><input type="radio" name="role" value="agent" data-role <?= $defRole === 'agent' ? 'checked' : '' ?>><span><?= e(t('role_agent')) ?></span></label>
            </fieldset>
            <div class="field" data-office-only>
                <label for="office_name"><?= e(t('office_name')) ?></label>
                <input id="office_name" name="office_name" maxlength="100" value="<?= e(old('office_name')) ?>" placeholder="<?= e(t('office_name_ph')) ?>"<?= field_aria('office_name') ?>>
                <p class="hint"><?= e(t('office_register_hint')) ?></p>
                <?= field_error('office_name') ?>
            </div>
            <div class="field">
                <label for="password"><?= e(t('password')) ?></label>
                <div class="password-wrap">
                    <input id="password" name="password" type="password" required minlength="8" maxlength="128" autocomplete="new-password" dir="ltr" aria-describedby="pw-hint"<?= isset(errors()['password']) ? ' aria-invalid="true"' : '' ?>>
                    <button type="button" class="pw-toggle" data-pw-toggle aria-controls="password" aria-pressed="false"><?= e(t('show')) ?></button>
                </div>
                <p class="hint" id="pw-hint"><?= e(t('v_password')) ?></p>
                <?= field_error('password') ?>
            </div>
            <div class="field">
                <label for="password_confirm"><?= e(t('password_confirm')) ?></label>
                <input id="password_confirm" name="password_confirm" type="password" required minlength="8" maxlength="128" autocomplete="new-password" dir="ltr" data-match="password"<?= field_aria('password_confirm') ?>>
                <?= field_error('password_confirm') ?>
            </div>
            <p class="hint"><?= e(t('register_privacy')) ?> <a href="<?= e(url('privacy')) ?>"><?= e(t('privacy_policy')) ?></a> · <a href="<?= e(url('terms')) ?>"><?= e(t('terms')) ?></a></p>
            <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('create_account')) ?></button>
        </form>
        <p class="auth-alt"><?= e(t('have_account')) ?> <a href="<?= e(url('login')) ?>"><?= e(t('login')) ?></a></p>
    </section>
</div>
