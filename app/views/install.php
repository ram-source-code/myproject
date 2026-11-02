<?php /** @var array $checks @var string $detected */
$errs = errors();
$v = static fn(string $k, string $d = '') => old($k, $d);
$okAll = !array_filter($checks, static fn($c) => $c[2] && !$c[1]);
?>
<div class="container install-wrap">
    <header class="install-head">
        <span class="brand-mark brand-mark-lg" aria-hidden="true"><svg><use href="#i-home"></use></svg></span>
        <h1 class="page-title"><?= e(t('install_heading')) ?></h1>
        <p class="muted"><?= e(t('install_sub')) ?></p>
        <ul class="lang-pills">
            <?php foreach (LANG_NAMES as $code => $nm): ?>
                <li><a href="<?= e(url('lang/' . $code)) ?>" lang="<?= e($code) ?>"<?= lang() === $code ? ' aria-current="true"' : '' ?>><?= e($nm) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </header>

    <ol class="install-steps">
        <li class="panel">
            <h2 class="panel-title"><span class="step-no">1</span><?= e(t('install_step_server')) ?></h2>
            <ul class="check-list">
                <?php foreach ($checks as [$label, $ok, $required, $hint]): ?>
                    <li class="<?= $ok ? 'ok' : ($required ? 'bad' : 'warn') ?>">
                        <svg aria-hidden="true"><use href="#i-<?= $ok ? 'check' : 'alert' ?>"></use></svg>
                        <div>
                            <span><?= e($label) ?></span>
                            <span class="visually-hidden"><?= $ok ? 'OK' : ($required ? t('required') : t('recommended')) ?></span>
                            <?php if (!$ok): ?><p class="hint"><?= e($hint) ?></p><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="muted small"><?= e(t('install_auto_done')) ?></p>
            <p class="small"><?= e(t('install_url')) ?> <code dir="ltr"><?= e($detected) ?></code></p>
            <?php if (isset($errs['checks'])): ?><p class="field-error"><?= e($errs['checks']) ?></p><?php endif; ?>
        </li>

        <li class="panel">
            <form method="post" action="<?= e(url('install')) ?>" class="stack" novalidate data-validate data-install-form>
                <?= csrf_field() ?>
                <h2 class="panel-title"><span class="step-no">2</span><?= e(t('install_step_site')) ?></h2>
                <div class="grid-2">
                    <div class="field">
                        <label for="default_lang"><?= e(t('default_language')) ?></label>
                        <select id="default_lang" name="default_lang">
                            <?php foreach (LANG_NAMES as $code => $nm): ?>
                                <option value="<?= e($code) ?>" <?= $v('default_lang', lang()) === $code ? 'selected' : '' ?>><?= e($nm) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="iqd_per_usd"><?= e(t('iqd_rate')) ?></label>
                        <input id="iqd_per_usd" name="iqd_per_usd" type="number" inputmode="numeric" min="100" max="100000" value="<?= e($v('iqd_per_usd', '1450')) ?>" dir="ltr">
                    </div>
                </div>

                <h2 class="panel-title"><span class="step-no">3</span><?= e(t('install_step_admin')) ?></h2>
                <div class="grid-2">
                    <div class="field">
                        <label for="name"><?= e(t('full_name')) ?></label>
                        <input id="name" name="name" required minlength="2" autocomplete="name" value="<?= e($v('name')) ?>"<?= field_aria('name') ?>>
                        <?= field_error('name') ?>
                    </div>
                    <div class="field">
                        <label for="phone"><?= e(t('phone')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                        <input id="phone" name="phone" type="tel" dir="ltr" autocomplete="tel" placeholder="0750 000 0000" value="<?= e($v('phone')) ?>"<?= field_aria('phone') ?>>
                        <?= field_error('phone') ?>
                    </div>
                </div>
                <div class="field">
                    <label for="email"><?= e(t('email')) ?></label>
                    <input id="email" name="email" type="email" required dir="ltr" autocomplete="username" value="<?= e($v('email')) ?>"<?= field_aria('email') ?>>
                    <p class="hint"><?= e(t('install_email_hint')) ?></p>
                    <?= field_error('email') ?>
                </div>
                <div class="field">
                    <label for="password"><?= e(t('password')) ?></label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required minlength="10" dir="ltr" autocomplete="new-password"<?= field_aria('password') ?>>
                        <button type="button" class="pw-toggle" data-pw-toggle aria-controls="password" aria-pressed="false"><?= e(t('show')) ?></button>
                    </div>
                    <p class="hint"><?= e(t('v_password_admin')) ?></p>
                    <?= field_error('password') ?>
                </div>

                <label class="check check-boxed">
                    <input type="checkbox" name="demo" value="1" <?= $v('demo') ? 'checked' : '' ?>>
                    <span><strong><?= e(t('install_demo')) ?></strong><span class="hint d-block"><?= e(t('install_demo_hint')) ?></span></span>
                </label>

                <button class="btn btn-primary btn-block btn-lg" type="submit" <?= $okAll ? '' : 'disabled' ?> data-busy-label="<?= e(t('installing')) ?>">
                    <svg aria-hidden="true"><use href="#i-sparkles"></use></svg><?= e(t('install_button')) ?>
                </button>
            </form>
        </li>
    </ol>
</div>
