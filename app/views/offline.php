<div class="container error-page">
    <svg class="offline-icon" aria-hidden="true"><use href="#i-globe"></use></svg>
    <h1 class="page-title"><?= e(t('offline_title')) ?></h1>
    <p class="muted"><?= e(t('offline_text')) ?></p>
    <div class="error-actions">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>" data-reload><?= e(t('try_again')) ?></a>
    </div>
</div>
