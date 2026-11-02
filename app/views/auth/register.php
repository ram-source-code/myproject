<div class="container auth-wrap">
    <section class="panel auth-card" aria-labelledby="reg-h">
        <h1 id="reg-h" class="page-title"><?= e(t('register')) ?></h1>
        <p class="muted"><?= e(t('register_sub')) ?></p>
        <form method="post" action="<?= e(url('register')) ?>" class="stack" novalidate data-validate>
            <?= csrf_field() ?>
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
                <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" dir="ltr" value="<?= e(old('phone')) ?>"<?= field_aria('phone') ?>>
                <p class="hint"><?= e(t('phone_hint')) ?></p>
                <?= field_error('phone') ?>
            </div>
            <fieldset class="segmented segmented-full">
                <legend><?= e(t('account_type')) ?></legend>
                <label><input type="radio" name="role" value="user" <?= old('role', 'user') === 'user' ? 'checked' : '' ?>><span><?= e(t('role_user')) ?></span></label>
                <label><input type="radio" name="role" value="agent" <?= old('role') === 'agent' ? 'checked' : '' ?>><span><?= e(t('role_agent')) ?></span></label>
            </fieldset>
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
            <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('create_account')) ?></button>
        </form>
        <p class="auth-alt"><?= e(t('have_account')) ?> <a href="<?= e(url('login')) ?>"><?= e(t('login')) ?></a></p>
    </section>
</div>
