<div class="container auth-wrap">
    <section class="panel auth-card" aria-labelledby="code-h">
        <h1 id="code-h" class="page-title"><?= e(t('twofa_title')) ?></h1>
        <p class="muted"><?= e(t('twofa_enter')) ?></p>
        <form method="post" action="<?= e(url('login/code')) ?>" class="stack" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="code"><?= e(t('twofa_code')) ?></label>
                <input id="code" name="code" class="otp-input" required autocomplete="one-time-code" inputmode="numeric" maxlength="12" dir="ltr" autofocus<?= field_aria('code') ?>>
                <?= field_error('code') ?>
                <p class="hint"><?= e(t('twofa_lost_phone')) ?></p>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('login')) ?></button>
        </form>
        <p class="auth-alt"><a href="<?= e(url('login')) ?>"><?= e(t('back')) ?></a></p>
    </section>
</div>
