<div class="container auth-wrap">
    <section class="panel auth-card" aria-labelledby="inst-h">
        <h1 id="inst-h" class="page-title"><?= e(t('install_heading')) ?></h1>
        <p class="muted"><?= e(t('install_sub')) ?></p>
        <ul class="check-list">
            <?php foreach ($checks as $label => $ok): ?>
                <li class="<?= $ok ? 'ok' : 'bad' ?>"><svg aria-hidden="true"><use href="#i-<?= $ok ? 'check' : 'alert' ?>"></use></svg><span><?= e($label) ?></span><span class="visually-hidden"><?= $ok ? 'OK' : '✗' ?></span></li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= e(url('install')) ?>" class="stack" novalidate data-validate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="name"><?= e(t('full_name')) ?></label>
                <input id="name" name="name" required minlength="2" autocomplete="name" value="<?= e(old('name')) ?>"<?= field_aria('name') ?>>
                <?= field_error('name') ?>
            </div>
            <div class="field">
                <label for="email"><?= e(t('email')) ?></label>
                <input id="email" name="email" type="email" required dir="ltr" autocomplete="username" value="<?= e(old('email')) ?>"<?= field_aria('email') ?>>
                <?= field_error('email') ?>
            </div>
            <div class="field">
                <label for="password"><?= e(t('password')) ?></label>
                <input id="password" name="password" type="password" required minlength="10" dir="ltr" autocomplete="new-password"<?= field_aria('password') ?>>
                <p class="hint"><?= e(t('v_password_admin')) ?></p>
                <?= field_error('password') ?>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('install_button')) ?></button>
        </form>
    </section>
</div>
