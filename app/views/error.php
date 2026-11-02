<div class="container error-page">
    <p class="error-code" aria-hidden="true"><?= (int) $code ?></p>
    <h1 class="page-title"><?= e($title) ?></h1>
    <?php if (!empty($message)): ?><p class="muted"><?= e($message) ?></p><?php endif; ?>
    <div class="error-actions">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(t('back_home')) ?></a>
        <a class="btn btn-outline" href="<?= e(url('properties')) ?>"><?= e(t('nav_listings')) ?></a>
    </div>
</div>
