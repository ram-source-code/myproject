<?php /** @var array $o */ $compact = $compact ?? false; ?>
<article class="office-card<?= $compact ? ' is-compact' : '' ?>">
    <div class="office-logo">
        <?php if ($o['logo']): ?>
            <img src="<?= e(upload_url($o['logo'])) ?>" alt="" width="64" height="64" loading="lazy">
        <?php else: ?>
            <span aria-hidden="true"><?= e(mb_substr($o['name'], 0, 1)) ?></span>
        <?php endif; ?>
    </div>
    <div class="office-info">
        <h3 class="office-name">
            <a href="<?= e(url('office/' . $o['id'])) ?>" class="stretched-link"><?= e($o['name']) ?></a>
            <?php if ($o['is_verified']): ?><svg class="verified" aria-label="<?= e(t('verified')) ?>" role="img"><use href="#i-verified"></use></svg><?php endif; ?>
        </h3>
        <p class="muted small">
            <?php if (city_name($o) !== ''): ?><?= e(city_name($o)) ?> · <?php endif; ?>
            <?= e(t('n_listings', ['n' => (int) $o['listings']])) ?> · <?= (int) $o['followers'] ?> <?= e(t('followers')) ?>
        </p>
    </div>
</article>
