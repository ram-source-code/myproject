<?php
/** @var array $p  @var bool $priority */
$favs = favorite_ids();
$isFav = in_array((int) $p['id'], $favs, true);
$href = url('property/' . $p['id']);
$headingTag = $headingTag ?? 'h3';
?>
<article class="card property-card">
    <div class="card-media">
        <?php if (!empty($p['cover_thumb'])): ?>
            <img src="<?= e(upload_url($p['cover_thumb'])) ?>" alt="" width="640" height="427"
                 <?= !empty($priority) ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
        <?php else: ?>
            <div class="card-media-empty"><svg aria-hidden="true"><use href="#i-image"></use></svg></div>
        <?php endif; ?>
        <div class="card-badges">
            <span class="badge badge-<?= e($p['purpose']) ?>"><?= e(t('purpose_' . $p['purpose'])) ?></span>
            <?php if ($p['is_featured']): ?><span class="badge badge-featured"><svg aria-hidden="true"><use href="#i-star"></use></svg><?= e(t('featured')) ?></span><?php endif; ?>
            <?php if ($p['status'] === 'sold'): ?><span class="badge badge-sold"><?= e(t($p['purpose'] === 'rent' ? 'status_rented' : 'status_sold')) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <p class="card-price"><?= e(price_label($p)) ?></p>
        <<?= $headingTag ?> class="card-title"><a href="<?= e($href) ?>" class="stretched-link"><?= e($p['title']) ?></a></<?= $headingTag ?>>
        <p class="card-location">
            <svg aria-hidden="true"><use href="#i-pin"></use></svg>
            <?= e(implode('، ', array_filter([$p['district'], city_name($p)]))) ?>
        </p>
        <ul class="specs" aria-label="<?= e(t('specs')) ?>">
            <li><span class="spec-type"><?= e(t('type_' . $p['type'])) ?></span></li>
            <?php if ($p['bedrooms']): ?><li><svg aria-hidden="true"><use href="#i-bed"></use></svg><?= (int) $p['bedrooms'] ?> <span class="visually-hidden"><?= e(t('bedrooms')) ?></span></li><?php endif; ?>
            <?php if ($p['bathrooms']): ?><li><svg aria-hidden="true"><use href="#i-bath"></use></svg><?= (int) $p['bathrooms'] ?> <span class="visually-hidden"><?= e(t('bathrooms')) ?></span></li><?php endif; ?>
            <li><svg aria-hidden="true"><use href="#i-area"></use></svg><?= e(number_format((float) $p['area'])) ?> <?= e(t('sqm')) ?></li>
        </ul>
    </div>
    <form class="fav-form" method="post" action="<?= e(url('favorite/' . $p['id'])) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="fav-btn" aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                aria-label="<?= e(($isFav ? t('fav_remove') : t('fav_add')) . ': ' . $p['title']) ?>">
            <svg aria-hidden="true"><use href="#i-heart"></use></svg>
        </button>
    </form>
</article>
