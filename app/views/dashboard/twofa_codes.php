<?php /** @var array $codes */ ?>
<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main narrow">
        <h1 class="page-title"><?= e(t('twofa_codes_title')) ?></h1>
        <section class="panel stack code-panel" tabindex="-1" data-autofocus aria-labelledby="rc-h">
            <h2 id="rc-h" class="panel-title"><?= e(t('twofa_codes_save')) ?></h2>
            <p class="muted"><?= e(t('twofa_codes_desc')) ?></p>
            <ol class="recovery-codes" dir="ltr"><?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?></ol>
            <div class="btn-row">
                <button class="btn btn-primary" type="button" data-copy="<?= e(implode("\n", $codes)) ?>"><svg aria-hidden="true"><use href="#i-copy"></use></svg><?= e(t('copy')) ?></button>
                <a class="btn btn-outline" href="<?= e(url('dashboard/profile')) ?>#twofa"><?= e(t('twofa_codes_done')) ?></a>
            </div>
        </section>
    </div>
</div>
