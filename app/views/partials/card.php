<?php
/** @var array $p  @var bool $priority */
$favs = favorite_ids();
$isFav = in_array((int) $p['id'], $favs, true);
$href = property_url($p);
$headingTag = $headingTag ?? 'h3';
?>
<article class="card property-card">
    <div class="card-media" data-color="<?= e(preg_match('/^#[0-9a-f]{6}$/', (string) ($p['cover_color'] ?? '')) ? $p['cover_color'] : '') ?>">
        <?php if (!empty($p['cover_thumb'])): ?>
            <img class="card-photo" src="<?= e(upload_url($p['cover_thumb'])) ?>" alt="" width="640" height="427"
                 <?= !empty($priority) ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
        <?php else: ?>
            <div class="card-media-empty"><svg aria-hidden="true"><use href="#i-image"></use></svg></div>
        <?php endif; ?>
        <div class="card-badges">
            <span class="badge badge-<?= e($p['purpose']) ?>"><?= e(t('purpose_' . $p['purpose'])) ?></span>
            <?php if ($drop = price_drop($p)): ?><span class="badge badge-drop"><svg aria-hidden="true"><use href="#i-trend-down"></use></svg><?= e(t('price_dropped')) ?> <?= (int) $drop['pct'] ?>%</span><?php endif; ?>
            <?php if ($p['is_featured']): ?><span class="badge badge-featured"><svg aria-hidden="true"><use href="#i-star"></use></svg><?= e(t('featured')) ?></span><?php endif; ?>
            <?php if ($p['status'] === 'sold'): ?><span class="badge badge-sold"><?= e(t($p['purpose'] === 'rent' ? 'status_rented' : 'status_sold')) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <p class="card-price"><?= e(price_label($p)) ?></p>
        <<?= $headingTag ?> class="card-title"><a href="<?= e($href) ?>" class="stretched-link"><?= e($p['title']) ?></a></<?= $headingTag ?>>
        <p class="card-location">
            <svg aria-hidden="true"><use href="#i-pin"></use></svg>
            <?= e(implode('، ', array_filter([district_label($p), city_name($p)]))) ?>
            <?php if (isset($p['distance_km'])): ?><span class="card-distance"><svg aria-hidden="true"><use href="#i-locate"></use></svg><?= e(format_distance((float) $p['distance_km'])) ?></span><?php endif; ?>
        </p>
        <?php if (!empty($p['project'])): ?><p class="card-project"><?= e($p['project']) ?></p><?php endif; ?>
        <ul class="specs" aria-label="<?= e(t('specs')) ?>">
            <li><span class="spec-type"><?= e(t('type_' . $p['type'])) ?></span></li>
            <?php if ($p['bedrooms']): ?><li><svg aria-hidden="true"><use href="#i-bed"></use></svg><?= (int) $p['bedrooms'] ?> <span class="visually-hidden"><?= e(t('bedrooms')) ?></span></li><?php endif; ?>
            <?php if ($p['bathrooms']): ?><li><svg aria-hidden="true"><use href="#i-bath"></use></svg><?= (int) $p['bathrooms'] ?> <span class="visually-hidden"><?= e(t('bathrooms')) ?></span></li><?php endif; ?>
            <li><svg aria-hidden="true"><use href="#i-area"></use></svg><?= e(number_format((float) $p['area'])) ?> <?= e(t('sqm')) ?></li>
        </ul>
        <?php if (!empty($p['office_name'])): ?>
            <p class="card-office">
                <?php if ($p['office_logo']): ?><img src="<?= e(upload_url($p['office_logo'])) ?>" alt="" width="22" height="22" loading="lazy"><?php else: ?><span class="avatar avatar-xs" aria-hidden="true"><?= e(mb_substr($p['office_name'], 0, 1)) ?></span><?php endif; ?>
                <span><?= e($p['office_name']) ?></span>
                <?php if ($p['office_verified']): ?><svg class="verified" aria-label="<?= e(t('verified')) ?>" role="img"><use href="#i-verified"></use></svg><?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <form class="fav-form" method="post" action="<?= e(url('favorite/' . $p['id'])) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="fav-btn" aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                aria-label="<?= e(($isFav ? t('fav_remove') : t('fav_add')) . ': ' . $p['title']) ?>">
            <svg aria-hidden="true"><use href="#i-heart"></use></svg>
        </button>
    </form>
</article>
