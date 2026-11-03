<section class="hero" aria-labelledby="hero-h">
    <div class="container hero-inner">
        <h1 id="hero-h" class="hero-title"><?= e(t('hero_question')) ?></h1>
        <div class="hero-skyline"><?= render_partial('partials/art/skyline') ?></div>
        <?php if ($topCities): ?>
            <ul class="hero-cities">
                <?php foreach ($topCities as $c): ?>
                    <li><a href="<?= e(url('city/' . $c['slug'])) ?>"><span class="hero-city"><?= e(city_name($c)) ?></span><span class="hero-city-count"><?= e(t('n_listings', ['n' => (int) $c['n']])) ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?= render_partial('partials/search_form') ?>
    </div>
</section>

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

<section class="section section-tight container for-you" aria-labelledby="near-h" data-near-section
         data-endpoint="<?= e(url('api/nearby')) ?>">
    <div class="section-head">
        <h2 id="near-h" class="section-title"><svg class="title-icon" aria-hidden="true"><use href="#i-locate"></use></svg><span data-near-title><?= e(t('near_you')) ?></span></h2>
        <a class="link-arrow" href="<?= e(url('properties')) ?>" data-near-all hidden><?= e(t('view_all')) ?><svg aria-hidden="true"><use href="#i-chevron"></use></svg></a>
    </div>
    <div class="near-prompt" data-near-prompt>
        <p class="muted"><?= e(t('near_prompt')) ?></p>
        <button type="button" class="btn btn-primary" data-near-load><svg aria-hidden="true"><use href="#i-locate"></use></svg><?= e(t('show_near_me')) ?></button>
        <p class="muted small"><svg class="inline-icon" aria-hidden="true"><use href="#i-eye"></use></svg> <?= e(t('loc_privacy')) ?></p>
    </div>
    <div class="near-fallback" data-near-fallback hidden>
        <p class="muted" data-near-msg><?= e(t('pick_city')) ?></p>
        <ul class="type-chips scroll-x">
            <?php foreach ($topCities as $c): ?>
                <li><a class="type-chip" href="<?= e(url('city/' . $c['slug'])) ?>"><svg aria-hidden="true"><use href="#i-pin"></use></svg><?= e(city_name($c)) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="grid-cards" data-near-list aria-live="polite"></div>
</section>

<section class="section section-tight container" data-reco-section hidden aria-labelledby="reco-h" data-endpoint="<?= e(url('api/recommend')) ?>">
    <div class="section-head">
        <h2 id="reco-h" class="section-title"><svg class="title-icon" aria-hidden="true"><use href="#i-sparkles"></use></svg><?= e(t('recommended_for_you')) ?></h2>
        <p class="muted small"><?= e(t('reco_why')) ?></p>
    </div>
    <div class="grid-cards" data-reco-list></div>
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
            <?= render_partial('partials/art/empty') ?>
            <p><?= e(t('no_listings_yet')) ?></p>
            <?php if (can_post_listings(current_user())): ?><a class="btn btn-primary" href="<?= e(url('dashboard/properties/new')) ?>"><?= e(t('add_first_listing')) ?></a><?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section container cv" aria-labelledby="how-h">
    <h2 id="how-h" class="section-title"><?= e(t('how_title')) ?></h2>
    <ol class="how-steps">
        <li><?= render_partial('partials/art/how-1') ?><h3><?= e(t('how_1_t')) ?></h3><p class="muted"><?= e(t('how_1')) ?></p></li>
        <li><?= render_partial('partials/art/how-2') ?><h3><?= e(t('how_2_t')) ?></h3><p class="muted"><?= e(t('how_2')) ?></p></li>
        <li><?= render_partial('partials/art/how-3') ?><h3><?= e(t('how_3_t')) ?></h3><p class="muted"><?= e(t('how_3')) ?></p></li>
    </ol>
</section>

<section class="section container cta-band" aria-labelledby="cta-h">
    <div>
        <h2 id="cta-h"><?= e(t('cta_title')) ?></h2>
        <p><?= e(t('cta_text')) ?></p>
    </div>
    <a class="btn btn-light btn-lg" href="<?= e(can_post_listings(current_user()) ? url('dashboard/properties/new') : (office_signup_open() ? (current_user() ? url('dashboard/office') : url('register', ['type' => 'office'])) : url('register') . '#for-offices')) ?>"><?= e(t('cta_button')) ?></a>
</section>
