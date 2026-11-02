<?php
/** @var array $p @var array $images @var array $similar @var array $features */
$user = current_user();
$isFav = in_array((int) $p['id'], favorite_ids(), true);
$canManage = can_manage_property($p, $user);
$location = implode('، ', array_filter([$p['address'], $p['district'], city_name($p)]));
?>
<div class="container">
    <nav class="breadcrumbs" aria-label="<?= e(t('breadcrumbs')) ?>">
        <ol>
            <li><a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a></li>
            <li><a href="<?= e(url('properties', ['purpose' => $p['purpose']])) ?>"><?= e(t('purpose_' . $p['purpose'])) ?></a></li>
            <li><a href="<?= e(url('properties', ['purpose' => $p['purpose'], 'type' => $p['type']])) ?>"><?= e(t('type_' . $p['type'])) ?></a></li>
            <li aria-current="page"><?= e(str_limit($p['title'], 40)) ?></li>
        </ol>
    </nav>

    <?php if ($p['status'] !== 'published'): ?>
        <div class="alert alert-<?= $p['status'] === 'sold' ? 'info' : 'warning' ?>">
            <svg aria-hidden="true"><use href="#i-alert"></use></svg>
            <p><?= e(t('status_notice_' . $p['status'])) ?></p>
        </div>
    <?php endif; ?>

    <header class="property-head">
        <div>
            <div class="card-badges static">
                <span class="badge badge-<?= e($p['purpose']) ?>"><?= e(t('purpose_' . $p['purpose'])) ?></span>
                <span class="badge"><?= e(t('type_' . $p['type'])) ?></span>
                <?php if ($p['is_featured']): ?><span class="badge badge-featured"><svg aria-hidden="true"><use href="#i-star"></use></svg><?= e(t('featured')) ?></span><?php endif; ?>
            </div>
            <h1 class="property-title"><?= e($p['title']) ?></h1>
            <p class="card-location"><svg aria-hidden="true"><use href="#i-pin"></use></svg><?= e($location) ?></p>
        </div>
        <div class="property-head-side">
            <p class="property-price"><?= e(price_label($p)) ?></p>
            <?php if ($p['purpose'] === 'sale' && $p['area'] > 0): ?>
                <p class="muted small"><?= e(money((int) round($p['price'] / $p['area']))) ?> / <?= e(t('sqm')) ?></p>
            <?php endif; ?>
            <div class="head-actions">
                <form method="post" action="<?= e(url('favorite/' . $p['id'])) ?>" class="fav-form-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm fav-toggle" aria-pressed="<?= $isFav ? 'true' : 'false' ?>">
                        <svg aria-hidden="true"><use href="#i-heart"></use></svg>
                        <span data-fav-label><?= e($isFav ? t('fav_remove') : t('fav_add')) ?></span>
                    </button>
                </form>
                <button type="button" class="btn btn-outline btn-sm" data-share data-title="<?= e($p['title']) ?>"><?= e(t('share')) ?></button>
                <?php if ($canManage): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('dashboard/properties/' . $p['id'] . '/edit')) ?>"><svg aria-hidden="true"><use href="#i-edit"></use></svg><?= e(t('edit')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($images): ?>
        <section class="gallery" data-count="<?= min(5, count($images)) ?>" aria-label="<?= e(t('photos')) ?>" data-gallery>
            <?php foreach (array_slice($images, 0, 5) as $i => $img): ?>
                <button type="button" class="gallery-item<?= $i === 0 ? ' gallery-main' : '' ?>" data-index="<?= $i ?>"
                        aria-label="<?= e(t('open_photo', ['n' => $i + 1, 'total' => count($images)])) ?>">
                    <img src="<?= e(upload_url($i === 0 ? $img['filename'] : $img['thumb'])) ?>" alt=""
                         width="<?= $i === 0 ? (int) $img['width'] : 640 ?>" height="<?= $i === 0 ? (int) $img['height'] : 427 ?>"
                         <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                    <?php if ($i === 4 && count($images) > 5): ?>
                        <span class="gallery-more">+<?= count($images) - 5 ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
            <button type="button" class="btn btn-light btn-sm gallery-all" data-index="0">
                <svg aria-hidden="true"><use href="#i-expand"></use></svg><?= e(t('all_photos', ['n' => count($images)])) ?>
            </button>
        </section>

        <dialog id="lightbox" class="lightbox" aria-label="<?= e(t('photos')) ?>" closedby="any">
            <div class="lightbox-bar">
                <p class="lightbox-count" aria-live="polite" dir="ltr"><span data-lb-current>1</span> / <?= count($images) ?></p>
                <form method="dialog"><button class="icon-btn" aria-label="<?= e(t('close')) ?>"><svg aria-hidden="true"><use href="#i-x"></use></svg></button></form>
            </div>
            <figure class="lightbox-stage">
                <img data-lb-img src="" alt="" decoding="async">
            </figure>
            <button type="button" class="icon-btn lightbox-nav lightbox-prev" data-lb-prev aria-label="<?= e(t('prev_photo')) ?>"><svg aria-hidden="true"><use href="#i-chevron"></use></svg></button>
            <button type="button" class="icon-btn lightbox-nav lightbox-next" data-lb-next aria-label="<?= e(t('next_photo')) ?>"><svg aria-hidden="true"><use href="#i-chevron"></use></svg></button>
            <ol class="lightbox-thumbs">
                <?php foreach ($images as $i => $img): ?>
                    <li><button type="button" data-lb-go="<?= $i ?>" data-full="<?= e(upload_url($img['filename'])) ?>" aria-label="<?= e(t('photo_n', ['n' => $i + 1])) ?>">
                        <img src="<?= e(upload_url($img['thumb'])) ?>" alt="" width="96" height="64" loading="lazy" fetchpriority="low">
                    </button></li>
                <?php endforeach; ?>
            </ol>
        </dialog>
    <?php else: ?>
        <div class="gallery-empty"><svg aria-hidden="true"><use href="#i-image"></use></svg><p><?= e(t('no_photos')) ?></p></div>
    <?php endif; ?>

    <div class="property-layout">
        <div class="property-main">
            <section class="panel" aria-labelledby="overview-h">
                <h2 id="overview-h" class="panel-title"><?= e(t('overview')) ?></h2>
                <dl class="facts">
                    <div><dt><svg aria-hidden="true"><use href="#i-area"></use></svg><?= e(t('area')) ?></dt><dd><?= e(number_format((float) $p['area'])) ?> <?= e(t('sqm')) ?></dd></div>
                    <?php if ($p['bedrooms']): ?><div><dt><svg aria-hidden="true"><use href="#i-bed"></use></svg><?= e(t('bedrooms')) ?></dt><dd><?= (int) $p['bedrooms'] ?></dd></div><?php endif; ?>
                    <?php if ($p['bathrooms']): ?><div><dt><svg aria-hidden="true"><use href="#i-bath"></use></svg><?= e(t('bathrooms')) ?></dt><dd><?= (int) $p['bathrooms'] ?></dd></div><?php endif; ?>
                    <?php if ($p['floor'] !== null): ?><div><dt><?= e(t('floor')) ?></dt><dd><?= (int) $p['floor'] ?></dd></div><?php endif; ?>
                    <?php if ($p['year_built']): ?><div><dt><?= e(t('year_built')) ?></dt><dd><?= (int) $p['year_built'] ?></dd></div><?php endif; ?>
                    <div><dt><?= e(t('furnished')) ?></dt><dd><?= e($p['furnished'] ? t('yes') : t('no')) ?></dd></div>
                    <div><dt><?= e(t('listing_id')) ?></dt><dd>#<?= (int) $p['id'] ?></dd></div>
                    <div><dt><svg aria-hidden="true"><use href="#i-eye"></use></svg><?= e(t('views')) ?></dt><dd><?= number_format((int) $p['views']) ?></dd></div>
                    <div><dt><?= e(t('listed_on')) ?></dt><dd><time datetime="<?= e(fmt_date($p['created_at'])) ?>"><?= e(fmt_date($p['created_at'])) ?></time></dd></div>
                </dl>
            </section>

            <?php if (trim($p['description']) !== ''): ?>
            <section class="panel" aria-labelledby="desc-h">
                <h2 id="desc-h" class="panel-title"><?= e(t('description')) ?></h2>
                <div class="prose"><?= nl2br(e($p['description'])) ?></div>
            </section>
            <?php endif; ?>

            <?php if ($features): ?>
            <section class="panel" aria-labelledby="feat-h">
                <h2 id="feat-h" class="panel-title"><?= e(t('features')) ?></h2>
                <ul class="feature-list">
                    <?php foreach ($features as $ft): ?>
                        <li><svg aria-hidden="true"><use href="#i-check"></use></svg><?= e(t('feat_' . $ft)) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php if ($p['lat'] !== null && $p['lng'] !== null): ?>
            <section class="panel" aria-labelledby="map-h">
                <h2 id="map-h" class="panel-title"><?= e(t('location')) ?></h2>
                <p class="muted"><?= e($location) ?></p>
                <a class="btn btn-outline" target="_blank" rel="noopener noreferrer"
                   href="https://www.openstreetmap.org/?mlat=<?= e($p['lat']) ?>&amp;mlon=<?= e($p['lng']) ?>#map=16/<?= e($p['lat']) ?>/<?= e($p['lng']) ?>">
                    <svg aria-hidden="true"><use href="#i-pin"></use></svg><?= e(t('open_map')) ?>
                    <span class="visually-hidden">(<?= e(t('new_tab')) ?>)</span>
                </a>
            </section>
            <?php endif; ?>
        </div>

        <aside class="property-aside" id="contact" aria-labelledby="contact-h">
            <div class="panel contact-card">
                <h2 id="contact-h" class="panel-title"><?= e(t('contact_advertiser')) ?></h2>
                <div class="owner">
                    <span class="avatar" aria-hidden="true"><?= e(mb_substr($p['owner_name'], 0, 1)) ?></span>
                    <div>
                        <p class="owner-name"><?= e($p['owner_name']) ?></p>
                        <p class="muted small"><?= e(t('role_' . $p['owner_role'])) ?></p>
                    </div>
                </div>
                <?php if ($p['owner_phone'] && $p['status'] === 'published'): ?>
                    <a class="btn btn-primary btn-block" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p['owner_phone'])) ?>">
                        <svg aria-hidden="true"><use href="#i-phone"></use></svg><span dir="ltr"><?= e($p['owner_phone']) ?></span>
                    </a>
                <?php endif; ?>

                <?php if ($p['status'] === 'published' && !$canManage): ?>
                <form method="post" action="<?= e(url('property/' . $p['id'] . '/inquiry')) ?>" class="stack" novalidate data-validate>
                    <?= csrf_field() ?>
                    <div class="hp" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="field">
                        <label for="iq-name"><?= e(t('your_name')) ?></label>
                        <input id="iq-name" name="name" required minlength="2" maxlength="100" autocomplete="name" value="<?= e(old('name', $user['name'] ?? '')) ?>"<?= field_aria('name') ?>>
                        <?= field_error('name') ?>
                    </div>
                    <div class="field">
                        <label for="iq-email"><?= e(t('email')) ?></label>
                        <input id="iq-email" name="email" type="email" required maxlength="150" autocomplete="email" dir="ltr" value="<?= e(old('email', $user['email'] ?? '')) ?>"<?= field_aria('email') ?>>
                        <?= field_error('email') ?>
                    </div>
                    <div class="field">
                        <label for="iq-phone"><?= e(t('phone')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                        <input id="iq-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" dir="ltr" value="<?= e(old('phone', $user['phone'] ?? '')) ?>"<?= field_aria('phone') ?>>
                        <?= field_error('phone') ?>
                    </div>
                    <div class="field">
                        <label for="iq-msg"><?= e(t('message')) ?></label>
                        <textarea id="iq-msg" name="message" rows="4" required minlength="10" maxlength="2000"<?= field_aria('message') ?>><?= e(old('message', t('inquiry_default', ['id' => $p['id']]))) ?></textarea>
                        <?= field_error('message') ?>
                    </div>
                    <button class="btn btn-outline btn-block" type="submit"><svg aria-hidden="true"><use href="#i-mail"></use></svg><?= e(t('send_inquiry')) ?></button>
                </form>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <?php if ($similar): ?>
    <section class="section" aria-labelledby="similar-h">
        <h2 id="similar-h" class="section-title"><?= e(t('similar_listings')) ?></h2>
        <div class="grid-cards">
            <?php foreach ($similar as $sp): ?>
                <?= render_partial('partials/card', ['p' => $sp]) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

