<?php /** @var string $secret @var string $uri */ ?>
<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main narrow">
        <h1 class="page-title"><?= e(t('twofa_title')) ?></h1>
        <section class="panel stack" aria-labelledby="s1-h">
            <h2 id="s1-h" class="panel-title">1. <?= e(t('twofa_step_scan')) ?></h2>
            <p class="muted"><?= e(t('twofa_apps')) ?></p>
            <div class="qr-box" data-qr="<?= e($uri) ?>" role="img" aria-label="<?= e(t('twofa_qr_label')) ?>"></div>
            <p class="small"><?= e(t('twofa_or_key')) ?></p>
            <p class="secret-key" dir="ltr"><code><?= e(trim(chunk_split($secret, 4, ' '))) ?></code>
                <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($secret) ?>"><svg aria-hidden="true"><use href="#i-copy"></use></svg><?= e(t('copy')) ?></button></p>
            <p><a class="btn btn-outline btn-sm" href="<?= e($uri) ?>"><?= e(t('twofa_open_app')) ?></a></p>
        </section>
        <section class="panel stack" aria-labelledby="s2-h">
            <h2 id="s2-h" class="panel-title">2. <?= e(t('twofa_step_confirm')) ?></h2>
            <form method="post" action="<?= e(url('dashboard/2fa')) ?>" class="activation-form" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="code"><?= e(t('twofa_code')) ?></label>
                    <input id="code" name="code" class="otp-input" required autocomplete="one-time-code" inputmode="numeric" maxlength="6" dir="ltr"<?= field_aria('code') ?>>
                    <?= field_error('code') ?>
                </div>
                <button class="btn btn-primary" type="submit"><svg aria-hidden="true"><use href="#i-check"></use></svg><?= e(t('twofa_turn_on')) ?></button>
            </form>
        </section>
    </div>
</div>
