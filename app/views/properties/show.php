<?php
/** @var array $p @var array $images @var array $similar @var array $features @var ?array $office */
$user = current_user();
$isFav = in_array((int) $p['id'], favorite_ids(), true);
$canManage = can_manage_property($p, $user);
$location = implode('، ', array_filter([$p['address'], $p['district'], city_name($p)]));
$contactPhone = ($office['phone'] ?? '') ?: (string) ($p['owner_phone'] ?? '');
$contactWa = ($office['whatsapp'] ?? '') ?: $contactPhone;
$waText = t('wa_message', ['title' => $p['title'], 'id' => $p['id']]) . ' ' . absolute_base_url() . '/property/' . (int) $p['id'];
$isLive = $p['status'] === 'published';
?>
<div class="container property-page" data-recent-item data-view-beacon="<?= e(url('property/' . $p['id'] . '/view')) ?>"
     data-id="<?= (int) $p['id'] ?>" data-title="<?= e($p['title']) ?>" data-price="<?= e(price_label($p)) ?>"
     data-url="<?= e(property_url($p)) ?>" data-thumb="<?= e($p['cover_thumb'] ? upload_url($p['cover_thumb']) : '') ?>">
    <nav class="breadcrumbs" aria-label="<?= e(t('breadcrumbs')) ?>">
        <ol>
            <li><a href="<?= e(url('/')) ?>"><?= e(t('nav_home')) ?></a></li>
            <li><a href="<?= e(url('properties', ['purpose' => $p['purpose']])) ?>"><?= e(t('purpose_' . $p['purpose'])) ?></a></li>
            <?php if ($p['city_id']): ?><li><a href="<?= e(url('properties', ['purpose' => $p['purpose'], 'city' => $p['city_id']])) ?>"><?= e(city_name($p)) ?></a></li><?php endif; ?>
            <li aria-current="page"><?= e(str_limit($p['title'], 40)) ?></li>
        </ol>
    </nav>

    <?php if (!$isLive): ?>
        <div class="alert alert-<?= $p['status'] === 'sold' ? 'info' : 'warning' ?>">
            <svg aria-hidden="true"><use href="#i-alert"></use></svg>
            <p><?= e(t('status_notice_' . $p['status'])) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($images): ?>
        <section class="gallery" data-count="<?= min(5, count($images)) ?>" aria-label="<?= e(t('photos')) ?>" data-gallery>
            <?php foreach (array_slice($images, 0, 5) as $i => $img): ?>
                <button type="button" class="gallery-item<?= $i === 0 ? ' gallery-main' : '' ?>" data-index="<?= $i ?>"
                        data-color="<?= e(preg_match('/^#[0-9a-f]{6}$/', (string) ($img['color'] ?? '')) ? $img['color'] : '') ?>"
                        aria-label="<?= e(t('open_photo', ['n' => $i + 1, 'total' => count($images)])) ?>">
                    <img src="<?= e(upload_url($i === 0 ? $img['filename'] : $img['thumb'])) ?>" alt=""
                         srcset="<?= e(upload_url($img['thumb'])) ?> 640w, <?= e(upload_url($img['filename'])) ?> <?= (int) $img['width'] ?>w"
                         sizes="<?= $i === 0 ? '(max-width: 720px) 100vw, 60vw' : '(max-width: 720px) 100vw, 20vw' ?>"
                         <?= $i === 0 ? 'class="hero-photo"' : '' ?>
                         width="<?= $i === 0 ? (int) $img['width'] : 640 ?>" height="<?= $i === 0 ? (int) $img['height'] : 427 ?>"
                         <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                    <?php if ($i === 4 && count($images) > 5): ?>
                        <span class="gallery-more">+<?= count($images) - 5 ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
            <span class="gallery-counter" aria-hidden="true"><span data-gallery-pos>1</span> / <?= min(5, count($images)) ?></span>
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

    <header class="property-head">
        <div>
            <div class="card-badges static">
                <span class="badge badge-<?= e($p['purpose']) ?>"><?= e(t('purpose_' . $p['purpose'])) ?></span>
                <span class="badge"><?= e(t('type_' . $p['type'])) ?></span>
                <?php if ($p['deed']): ?><span class="badge badge-deed"><svg aria-hidden="true"><use href="#i-doc"></use></svg><?= e(t('deed_' . $p['deed'])) ?></span><?php endif; ?>
                <?php if ($p['is_featured']): ?><span class="badge badge-featured"><svg aria-hidden="true"><use href="#i-star"></use></svg><?= e(t('featured')) ?></span><?php endif; ?>
            </div>
            <h1 class="property-title"><?= e($p['title']) ?></h1>
            <p class="card-location"><svg aria-hidden="true"><use href="#i-pin"></use></svg><?= e($location) ?><?php if ($p['project']): ?> · <strong><?= e($p['project']) ?></strong><?php endif; ?></p>
        </div>
        <div class="property-head-side">
            <p class="property-price"><?= e(price_label($p)) ?></p>
            <?php if ($p['currency'] === 'IQD'): ?>
                <p class="muted small">≈ <?= e(money((int) $p['price_usd'])) ?></p>
            <?php elseif ($p['purpose'] === 'sale' && $p['area'] > 0): ?>
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

    <div class="property-layout">
        <div class="property-main">
            <section class="panel" aria-labelledby="overview-h">
                <h2 id="overview-h" class="panel-title"><?= e(t('overview')) ?></h2>
                <dl class="facts">
                    <div><dt><svg aria-hidden="true"><use href="#i-area"></use></svg><?= e(t('area')) ?></dt><dd><?= e(number_format((float) $p['area'])) ?> <?= e(t('sqm')) ?></dd></div>
                    <?php if ($p['bedrooms']): ?><div><dt><svg aria-hidden="true"><use href="#i-bed"></use></svg><?= e(t('bedrooms')) ?></dt><dd><?= (int) $p['bedrooms'] ?></dd></div><?php endif; ?>
                    <?php if ($p['bathrooms']): ?><div><dt><svg aria-hidden="true"><use href="#i-bath"></use></svg><?= e(t('bathrooms')) ?></dt><dd><?= (int) $p['bathrooms'] ?></dd></div><?php endif; ?>
                    <?php if ($p['deed']): ?><div><dt><svg aria-hidden="true"><use href="#i-doc"></use></svg><?= e(t('deed')) ?></dt><dd><?= e(t('deed_' . $p['deed'])) ?></dd></div><?php endif; ?>
                    <?php if ($p['floor'] !== null): ?><div><dt><?= e(t('floor')) ?></dt><dd><?= (int) $p['floor'] ?></dd></div><?php endif; ?>
                    <?php if ($p['year_built']): ?><div><dt><?= e(t('year_built')) ?></dt><dd><?= (int) $p['year_built'] ?></dd></div><?php endif; ?>
                    <div><dt><?= e(t('furnished')) ?></dt><dd><?= e($p['furnished'] ? t('yes') : t('no')) ?></dd></div>
                    <div><dt><?= e(t('listing_id')) ?></dt><dd>#<?= (int) $p['id'] ?></dd></div>
                    <div><dt><svg aria-hidden="true"><use href="#i-eye"></use></svg><?= e(t('views')) ?></dt><dd><?= number_format((int) $p['views']) ?></dd></div>
                    <div><dt><svg aria-hidden="true"><use href="#i-clock"></use></svg><?= e(t('listed_on')) ?></dt><dd><time datetime="<?= e(fmt_date($p['created_at'])) ?>"><?= e(fmt_date($p['created_at'])) ?></time></dd></div>
                </dl>
            </section>

            <?php if (trim($p['description']) !== ''): ?>
            <section class="panel" aria-labelledby="desc-h">
                <h2 id="desc-h" class="panel-title"><?= e(t('description')) ?></h2>
                <div class="prose"><?= nl2br(e($p['description'])) ?></div>
            </section>
            <?php endif; ?>

            <?php if (!empty($videos)): ?>
            <section class="panel" aria-labelledby="vid-h">
                <h2 id="vid-h" class="panel-title"><?= e(t('videos')) ?></h2>
                <div class="video-list">
                    <?php foreach ($videos as $v): ?>
                        <video controls playsinline preload="metadata" src="<?= e(upload_url($v['filename'])) ?>#t=0.1"
                               <?php if (!empty($p['cover_thumb'])): ?>poster="<?= e(upload_url($p['cover_thumb'])) ?>"<?php endif; ?>
                               aria-label="<?= e(t('video_of', ['title' => $p['title']])) ?>"></video>
                    <?php endforeach; ?>
                </div>
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
                <div class="mini-map" data-mini-map data-lat="<?= e($p['lat']) ?>" data-lng="<?= e($p['lng']) ?>" role="region" aria-label="<?= e(t('map') . ': ' . $location) ?>"></div>
                <p class="map-actions">
                    <a class="btn btn-outline btn-sm" target="_blank" rel="noopener noreferrer"
                       href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= e($p['lat']) ?>,<?= e($p['lng']) ?>">
                        <svg aria-hidden="true"><use href="#i-pin"></use></svg><?= e(t('directions')) ?><span class="visually-hidden"> (<?= e(t('new_tab')) ?>)</span>
                    </a>
                </p>
            </section>
            <?php endif; ?>
        </div>

        <aside class="property-aside" id="contact" aria-labelledby="contact-h">
            <div class="panel contact-card">
                <h2 id="contact-h" class="panel-title"><?= e(t('contact_advertiser')) ?></h2>
                <?php if ($office): ?>
                    <a class="owner owner-link" href="<?= e(url('office/' . $office['id'])) ?>">
                        <?php if ($office['logo']): ?><img class="owner-logo" src="<?= e(upload_url($office['logo'])) ?>" alt="" width="52" height="52"><?php else: ?><span class="avatar" aria-hidden="true"><?= e(mb_substr($office['name'], 0, 1)) ?></span><?php endif; ?>
                        <span>
                            <span class="owner-name"><?= e($office['name']) ?><?php if ($office['is_verified']): ?> <svg class="verified" aria-label="<?= e(t('verified')) ?>" role="img"><use href="#i-verified"></use></svg><?php endif; ?></span>
                            <span class="muted small d-block"><?= e(t('office')) ?> · <?= e(t('n_listings', ['n' => (int) $office['listings']])) ?></span>
                        </span>
                    </a>
                    <?= render_partial('partials/follow_button', ['o' => $office, 'block' => true]) ?>
                <?php else: ?>
                    <div class="owner">
                        <span class="avatar" aria-hidden="true"><?= e(mb_substr($p['owner_name'], 0, 1)) ?></span>
                        <div>
                            <p class="owner-name"><?= e($p['owner_name']) ?></p>
                            <p class="muted small"><?= e(t('role_' . $p['owner_role'])) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isLive && $contactPhone): ?>
                    <div class="contact-buttons">
                        <a class="btn btn-whatsapp" href="<?= e(whatsapp_url($contactWa, $waText)) ?>" target="_blank" rel="noopener noreferrer">
                            <svg aria-hidden="true"><use href="#i-whatsapp"></use></svg><?= e(t('whatsapp')) ?>
                        </a>
                        <a class="btn btn-primary" href="tel:+<?= e(phone_intl($contactPhone)) ?>">
                            <svg aria-hidden="true"><use href="#i-phone"></use></svg><span dir="ltr"><?= e($contactPhone) ?></span>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($isLive && !$canManage): ?>
                <details class="inquiry-details"<?= errors() ? ' open' : '' ?>>
                    <summary class="btn btn-outline btn-block"><svg aria-hidden="true"><use href="#i-mail"></use></svg><?= e(t('send_inquiry')) ?></summary>
                    <form method="post" action="<?= e(url('property/' . $p['id'] . '/inquiry')) ?>" class="stack" novalidate data-validate>
                        <?= csrf_field() ?>
                        <div class="hp" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                        <?= form_time_field() ?>
                        <div class="field">
                            <label for="iq-name"><?= e(t('your_name')) ?></label>
                            <input id="iq-name" name="name" required minlength="2" maxlength="100" autocomplete="name" value="<?= e(old('name', $user['name'] ?? '')) ?>"<?= field_aria('name') ?>>
                            <?= field_error('name') ?>
                        </div>
                        <div class="field">
                            <label for="iq-phone"><?= e(t('phone')) ?></label>
                            <input id="iq-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" dir="ltr" placeholder="0750 000 0000" value="<?= e(old('phone', $user['phone'] ?? '')) ?>"<?= field_aria('phone') ?>>
                            <?= field_error('phone') ?>
                        </div>
                        <div class="field">
                            <label for="iq-email"><?= e(t('email')) ?></label>
                            <input id="iq-email" name="email" type="email" required maxlength="150" autocomplete="email" dir="ltr" value="<?= e(old('email', $user['email'] ?? '')) ?>"<?= field_aria('email') ?>>
                            <?= field_error('email') ?>
                        </div>
                        <div class="field">
                            <label for="iq-msg"><?= e(t('message')) ?></label>
                            <textarea id="iq-msg" name="message" rows="4" required minlength="10" maxlength="2000"<?= field_aria('message') ?>><?= e(old('message', t('inquiry_default', ['id' => $p['id']]))) ?></textarea>
                            <?= field_error('message') ?>
                        </div>
                        <p class="hint"><?= e(t('inquiry_privacy')) ?> <a href="<?= e(url('privacy')) ?>"><?= e(t('privacy_policy')) ?></a></p>
                        <button class="btn btn-primary btn-block" type="submit"><svg aria-hidden="true"><use href="#i-send"></use></svg><?= e(t('send')) ?></button>
                    </form>
                </details>
                <?php endif; ?>
            </div>
            <?php if ($isLive && !$canManage): ?>
            <details class="report-box" id="report">
                <summary><svg aria-hidden="true"><use href="#i-flag"></use></svg><?= e(t('report_listing')) ?></summary>
                <form method="post" action="<?= e(url('property/' . $p['id'] . '/report')) ?>" class="stack">
                    <?= csrf_field() ?>
                    <?= form_time_field() ?>
                    <fieldset class="stack-sm">
                        <legend><?= e(t('report_reason')) ?></legend>
                        <?php foreach (REPORT_REASONS as $i => $rs): ?>
                            <label class="check"><input type="radio" name="reason" value="<?= e($rs) ?>" <?= $i === 0 ? 'required' : '' ?>><span><?= e(t('report_' . $rs)) ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <div class="field">
                        <label for="rp-details"><?= e(t('report_details')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                        <textarea id="rp-details" name="details" rows="2" maxlength="500"></textarea>
                    </div>
                    <button class="btn btn-outline btn-sm" type="submit"><?= e(t('send_report')) ?></button>
                </form>
            </details>
            <?php endif; ?>
        </aside>
    </div>

    <?php if (!empty($nearby)): ?>
    <section class="section cv" aria-labelledby="nearby-h">
        <h2 id="nearby-h" class="section-title"><svg class="title-icon" aria-hidden="true"><use href="#i-locate"></use></svg><?= e(t('nearby_listings')) ?></h2>
        <p class="muted section-sub"><?= e(t('nearby_sub')) ?></p>
        <div class="grid-cards">
            <?php foreach ($nearby as $np): ?>
                <?= render_partial('partials/card', ['p' => $np]) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($similar): ?>
    <section class="section cv" aria-labelledby="similar-h">
        <h2 id="similar-h" class="section-title"><?= e(t('similar_listings')) ?></h2>
        <div class="grid-cards">
            <?php foreach ($similar as $sp): ?>
                <?= render_partial('partials/card', ['p' => $sp]) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php if ($isLive && $contactPhone && !$canManage): ?>
<div class="sticky-contact" aria-label="<?= e(t('contact_advertiser')) ?>" role="region">
    <p class="sticky-price"><?= e(price_label($p)) ?></p>
    <a class="btn btn-whatsapp" href="<?= e(whatsapp_url($contactWa, $waText)) ?>" target="_blank" rel="noopener noreferrer"><svg aria-hidden="true"><use href="#i-whatsapp"></use></svg><?= e(t('whatsapp')) ?></a>
    <a class="btn btn-primary" href="tel:+<?= e(phone_intl($contactPhone)) ?>" aria-label="<?= e(t('call')) ?>"><svg aria-hidden="true"><use href="#i-phone"></use></svg><?= e(t('call')) ?></a>
</div>
<?php endif; ?>
