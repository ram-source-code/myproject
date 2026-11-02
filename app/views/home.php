<section class="hero">
    <div class="container hero-inner">
        <p class="hero-kicker"><svg aria-hidden="true"><use href="#i-pin"></use></svg><?= e(t('hero_kicker')) ?></p>
        <h1 class="hero-title"><?= e(t('hero_title')) ?></h1>
        <p class="hero-sub"><?= e(t('hero_sub')) ?></p>
        <?= render_partial('partials/search_form') ?>
        <dl class="hero-stats">
            <div><dt><?= e(t('stat_listings')) ?></dt><dd><?= number_format((int) $stats['listings']) ?></dd></div>
            <div><dt><?= e(t('stat_sale')) ?></dt><dd><?= number_format((int) $stats['sale']) ?></dd></div>
            <div><dt><?= e(t('stat_rent')) ?></dt><dd><?= number_format((int) $stats['rent']) ?></dd></div>
            <div><dt><?= e(t('offices')) ?></dt><dd><?= number_format((int) $stats['offices']) ?></dd></div>
        </dl>
    </div>
</section>

<?php if ($topCities): ?>
<section class="section container" aria-labelledby="cities-h">
    <h2 id="cities-h" class="section-title"><?= e(t('browse_by_city')) ?></h2>
    <ul class="city-tiles">
        <?php foreach ($topCities as $i => $c): ?>
            <li>
                <a class="city-tile city-tile-<?= $i % 6 ?>" href="<?= e(url('city/' . $c['slug'])) ?>">
                    <span class="city-name"><?= e(city_name($c)) ?></span>
                    <span class="city-count"><?= e(t('n_listings', ['n' => (int) $c['n']])) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="section section-tight container" aria-labelledby="types-h">
    <h2 id="types-h" class="section-title"><?= e(t('browse_by_type')) ?></h2>
    <ul class="type-chips scroll-x">
        <?php foreach (PROPERTY_TYPES as $tp): if (empty($byType[$tp])) continue; ?>
            <li>
                <a class="type-chip" href="<?= e(url('properties', ['type' => $tp])) ?>">
                    <span><?= e(t('type_' . $tp)) ?></span>
                    <span class="chip-count"><?= (int) $byType[$tp] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="section section-tight container" data-recent-section hidden aria-labelledby="recent-h">
    <div class="section-head">
        <h2 id="recent-h" class="section-title"><?= e(t('recently_viewed')) ?></h2>
        <button type="button" class="btn btn-ghost btn-sm" data-recent-clear><?= e(t('reset')) ?></button>
    </div>
    <ul class="recent-strip scroll-x" data-recent-list></ul>
</section>

<?php if ($featured): ?>
<section class="section container" aria-labelledby="featured-h">
    <div class="section-head">
        <h2 id="featured-h" class="section-title"><?= e(t('featured_listings')) ?></h2>
        <a class="link-arrow" href="<?= e(url('properties')) ?>"><?= e(t('view_all')) ?><svg aria-hidden="true"><use href="#i-chevron"></use></svg></a>
    </div>
    <div class="grid-cards">
        <?php foreach ($featured as $p): ?>
            <?= render_partial('partials/card', ['p' => $p]) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($offices): ?>
<section class="section container cv" aria-labelledby="offices-h">
    <div class="section-head">
        <h2 id="offices-h" class="section-title"><?= e(t('top_offices')) ?></h2>
        <a class="link-arrow" href="<?= e(url('offices')) ?>"><?= e(t('view_all')) ?><svg aria-hidden="true"><use href="#i-chevron"></use></svg></a>
    </div>
    <ul class="office-strip scroll-x">
        <?php foreach ($offices as $o): ?>
            <li><?= render_partial('partials/office_card', ['o' => $o, 'compact' => true]) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="section container cv" aria-labelledby="latest-h">
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

<section class="section container cv" aria-labelledby="how-h">
    <h2 id="how-h" class="section-title"><?= e(t('how_title')) ?></h2>
    <ol class="how-steps">
        <li><svg aria-hidden="true"><use href="#i-search"></use></svg><h3><?= e(t('how_1_t')) ?></h3><p class="muted"><?= e(t('how_1')) ?></p></li>
        <li><svg aria-hidden="true"><use href="#i-bell"></use></svg><h3><?= e(t('how_2_t')) ?></h3><p class="muted"><?= e(t('how_2')) ?></p></li>
        <li><svg aria-hidden="true"><use href="#i-whatsapp"></use></svg><h3><?= e(t('how_3_t')) ?></h3><p class="muted"><?= e(t('how_3')) ?></p></li>
    </ol>
</section>

<section class="section container cta-band" aria-labelledby="cta-h">
    <div>
        <h2 id="cta-h"><?= e(t('cta_title')) ?></h2>
        <p><?= e(t('cta_text')) ?></p>
    </div>
    <a class="btn btn-light btn-lg" href="<?= e(url(current_user() ? 'dashboard/properties/new' : 'register', current_user() ? [] : ['type' => 'office'])) ?>"><?= e(t('cta_button')) ?></a>
</section>
