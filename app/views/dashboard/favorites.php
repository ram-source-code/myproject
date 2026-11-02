<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <h1 class="page-title"><?= e(t('favorites')) ?></h1>
        <?php if ($items): ?>
            <div class="grid-cards">
                <?php foreach ($items as $p): ?>
                    <?= render_partial('partials/card', ['p' => $p, 'headingTag' => 'h2']) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg aria-hidden="true"><use href="#i-heart"></use></svg>
                <p><?= e(t('no_favorites')) ?></p>
                <a class="btn btn-primary" href="<?= e(url('properties')) ?>"><?= e(t('browse_listings')) ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
