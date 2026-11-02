<section class="hero">
    <div class="container hero-inner">
        <h1 class="hero-title"><?= e(t('hero_title')) ?></h1>
        <p class="hero-sub"><?= e(t('hero_sub')) ?></p>
        <?= render_partial('partials/search_form') ?>
        <dl class="hero-stats">
            <div><dt><?= e(t('stat_listings')) ?></dt><dd><?= number_format((int) $stats['listings']) ?></dd></div>
            <div><dt><?= e(t('stat_sale')) ?></dt><dd><?= number_format((int) $stats['sale']) ?></dd></div>
            <div><dt><?= e(t('stat_rent')) ?></dt><dd><?= number_format((int) $stats['rent']) ?></dd></div>
            <div><dt><?= e(t('stat_cities')) ?></dt><dd><?= number_format((int) $stats['cities']) ?></dd></div>
        </dl>
    </div>
</section>

<section class="section container" aria-labelledby="types-h">
    <h2 id="types-h" class="section-title"><?= e(t('browse_by_type')) ?></h2>
    <ul class="type-chips">
        <?php foreach (PROPERTY_TYPES as $tp): ?>
            <li>
                <a class="type-chip" href="<?= e(url('properties', ['type' => $tp])) ?>">
                    <span><?= e(t('type_' . $tp)) ?></span>
                    <span class="chip-count"><?= (int) ($byType[$tp] ?? 0) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($featured): ?>
<section class="section container" aria-labelledby="featured-h">
    <div class="section-head">
        <h2 id="featured-h" class="section-title"><?= e(t('featured_listings')) ?></h2>
        <a class="link-arrow" href="<?= e(url('properties')) ?>"><?= e(t('view_all')) ?><svg aria-hidden="true"><use href="#i-chevron"></use></svg></a>
    </div>
    <div class="grid-cards">
        <?php foreach ($featured as $i => $p): ?>
            <?= render_partial('partials/card', ['p' => $p, 'priority' => false]) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section container" aria-labelledby="latest-h">
    <div class="section-head">
        <h2 id="latest-h" class="section-title"><?= e(t('latest_listings')) ?></h2>
        <a class="link-arrow" href="<?= e(url('properties', ['sort' => 'newest'])) ?>"><?= e(t('view_all')) ?><svg aria-hidden="true"><use href="#i-chevron"></use></svg></a>
    </div>
    <?php if ($latest): ?>
        <div class="grid-cards">
            <?php foreach ($latest as $p): ?>
                <?= render_partial('partials/card', ['p' => $p]) ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <svg aria-hidden="true"><use href="#i-home"></use></svg>
            <p><?= e(t('no_listings_yet')) ?></p>
            <a class="btn btn-primary" href="<?= e(url('dashboard/properties/new')) ?>"><?= e(t('add_first_listing')) ?></a>
        </div>
    <?php endif; ?>
</section>

<section class="section container cta-band" aria-labelledby="cta-h">
    <div>
        <h2 id="cta-h"><?= e(t('cta_title')) ?></h2>
        <p><?= e(t('cta_text')) ?></p>
    </div>
    <a class="btn btn-light btn-lg" href="<?= e(url(current_user() ? 'dashboard/properties/new' : 'register')) ?>"><?= e(t('cta_button')) ?></a>
</section>
