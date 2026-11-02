<div class="container auth-wrap">
    <section class="panel auth-card" aria-labelledby="login-h">
        <h1 id="login-h" class="page-title"><?= e(t('login')) ?></h1>
        <p class="muted"><?= e(t('login_sub')) ?></p>
        <form method="post" action="<?= e(url('login')) ?>" class="stack" novalidate data-validate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="email"><?= e(t('email')) ?></label>
                <input id="email" name="email" type="email" required autocomplete="username" dir="ltr" autofocus value="<?= e(old('email')) ?>"<?= field_aria('email') ?>>
                <?= field_error('email') ?>
            </div>
            <div class="field">
                <label for="password"><?= e(t('password')) ?></label>
                <div class="password-wrap">
                    <input id="password" name="password" type="password" required autocomplete="current-password" dir="ltr">
                    <button type="button" class="pw-toggle" data-pw-toggle aria-controls="password" aria-pressed="false"><?= e(t('show')) ?></button>
                </div>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('login')) ?></button>
        </form>
        <p class="auth-alt"><?= e(t('no_account')) ?> <a href="<?= e(url('register')) ?>"><?= e(t('register')) ?></a></p>
    </section>
</div>
