<?php /** @var array $o @var array $items @var array $pg @var array $filters */
$phone = $o['phone'];
$wa = $o['whatsapp'] ?: $o['phone'];
?>
<section class="office-hero">
    <div class="container office-hero-inner">
        <div class="office-logo office-logo-lg">
            <?php if ($o['logo']): ?>
                <img src="<?= e(upload_url($o['logo'])) ?>" alt="<?= e($o['name']) ?>" width="112" height="112">
            <?php else: ?>
                <span aria-hidden="true"><?= e(mb_substr($o['name'], 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <div class="office-hero-text">
            <p class="muted small"><?= e(t('office')) ?></p>
            <h1 class="page-title">
                <?= e($o['name']) ?>
                <?php if ($o['is_verified']): ?><svg class="verified verified-lg" aria-label="<?= e(t('verified')) ?>" role="img"><use href="#i-verified"></use></svg><?php endif; ?>
            </h1>
            <?php if ($o['is_verified']): ?>
                <p class="verified-note"><svg aria-hidden="true"><use href="#i-verified"></use></svg><?= e(t('verified_visited', ['app' => app_name(), 'date' => $o['verified_at'] ? substr($o['verified_at'], 0, 10) : ''])) ?></p>
            <?php endif; ?>
            <p class="office-meta">
                <?php if (city_name($o) !== ''): ?><span><svg aria-hidden="true"><use href="#i-pin"></use></svg><?= e(implode('، ', array_filter([$o['address'], city_name($o)]))) ?></span><?php endif; ?>
                <span><svg aria-hidden="true"><use href="#i-home"></use></svg><?= e(t('n_listings', ['n' => (int) $o['listings']])) ?></span>
            </p>
            <?php if (trim($o['about']) !== ''): ?><p class="office-about"><?= nl2br(e($o['about'])) ?></p><?php endif; ?>
        </div>
        <div class="office-actions">
            <?= render_partial('partials/follow_button', ['o' => $o, 'block' => true]) ?>
            <?php if ($phone): ?>
                <div class="contact-buttons">
                    <a class="btn btn-whatsapp" href="<?= e(whatsapp_url($wa)) ?>" target="_blank" rel="noopener noreferrer"><svg aria-hidden="true"><use href="#i-whatsapp"></use></svg><?= e(t('whatsapp')) ?></a>
                    <a class="btn btn-outline" href="tel:+<?= e(phone_intl($phone)) ?>"><svg aria-hidden="true"><use href="#i-phone"></use></svg><span dir="ltr"><?= e($phone) ?></span></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="container section">
    <div class="section-head">
        <h2 class="section-title"><?= e(t('office_listings')) ?> <span class="muted">(<?= (int) $pg['total'] ?>)</span></h2>
        <nav class="tabs tabs-inline" aria-label="<?= e(t('purpose')) ?>">
            <?php foreach (['' => t('all'), 'sale' => t('purpose_sale'), 'rent' => t('purpose_rent')] as $v => $l): ?>
                <a href="<?= e(url('office/' . $o['id'], ['purpose' => $v])) ?>"<?= $filters['purpose'] === $v ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php if ($items): ?>
        <div class="grid-cards">
            <?php foreach ($items as $p): ?>
                <?= render_partial('partials/card', ['p' => $p]) ?>
            <?php endforeach; ?>
        </div>
        <?= render_partial('partials/pagination', ['pg' => $pg, 'base' => 'office/' . $o['id'], 'query' => ['purpose' => $filters['purpose']]]) ?>
    <?php else: ?>
        <div class="empty-state"><svg aria-hidden="true"><use href="#i-home"></use></svg><p><?= e(t('no_results')) ?></p></div>
    <?php endif; ?>
</div>
