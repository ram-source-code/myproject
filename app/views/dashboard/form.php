<?php
/** @var ?array $p @var array $images */
$isEdit = $p !== null;
$v = static function (string $k, $default = '') use ($p) {
    return old($k, $p[$k] ?? $default);
};
$hasOld = isset($_SESSION['_old']);
$selFeatures = $hasOld ? old_array('features') : ($isEdit ? (json_decode($p['features'], true) ?: []) : []);
$furnished = $hasOld ? !empty($_SESSION['_old']['furnished']) : (bool) ($p['furnished'] ?? false);
$action = $isEdit ? url('dashboard/properties/' . $p['id']) : url('dashboard/properties');
$maxMb = (int) (config('max_upload_bytes') / 1048576);
$errs = errors();
?>
<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <div class="section-head">
            <h1 class="page-title"><?= e($isEdit ? t('edit_listing') : t('add_listing')) ?></h1>
            <?php if ($isEdit): ?>
                <div class="head-actions">
                    <span class="status status-<?= e($p['status']) ?>"><?= e(t('status_' . $p['status'])) ?></span>
                    <a class="btn btn-outline btn-sm" href="<?= e(property_url($p)) ?>"><svg aria-hidden="true"><use href="#i-eye"></use></svg><?= e(t('preview')) ?></a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($errs): ?>
            <div class="alert alert-error" role="alert" tabindex="-1" data-focus>
                <svg aria-hidden="true"><use href="#i-alert"></use></svg>
                <div>
                    <p><strong><?= e(t('form_has_errors')) ?></strong></p>
                    <ul><?php foreach ($errs as $field => $msg): ?><li><a href="#f-<?= e($field) ?>"><?= e(t('field_' . $field)) ?>: <?= e($msg) ?></a></li><?php endforeach; ?></ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="listing-form" novalidate data-validate>
            <?= csrf_field() ?>

            <section class="panel" aria-labelledby="sec-basic">
                <h2 id="sec-basic" class="panel-title"><?= e(t('basic_info')) ?></h2>
                <div class="field">
                    <label for="f-title"><?= e(t('field_title')) ?></label>
                    <input id="f-title" name="title" required minlength="5" maxlength="150" value="<?= e($v('title')) ?>"<?= field_aria('title') ?>>
                    <p class="hint"><?= e(t('title_hint')) ?></p>
                    <?= field_error('title') ?>
                </div>
                <div class="grid-2">
                    <fieldset class="segmented segmented-full">
                        <legend><?= e(t('purpose')) ?></legend>
                        <?php foreach (PROPERTY_PURPOSES as $pp): ?>
                            <label><input type="radio" name="purpose" value="<?= $pp ?>" required <?= $v('purpose', 'sale') === $pp ? 'checked' : '' ?> data-purpose><span><?= e(t('purpose_' . $pp)) ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <div class="field">
                        <label for="f-type"><?= e(t('field_type')) ?></label>
                        <select id="f-type" name="type" required<?= field_aria('type') ?>>
                            <option value=""><?= e(t('choose')) ?></option>
                            <?php foreach (PROPERTY_TYPES as $tp): ?>
                                <option value="<?= $tp ?>" <?= $v('type') === $tp ? 'selected' : '' ?>><?= e(t('type_' . $tp)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error('type') ?>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label for="f-price"><?= e(t('field_price')) ?></label>
                        <div class="input-group">
                            <input id="f-price" name="price" type="number" inputmode="numeric" min="0" step="1" required value="<?= e($v('price')) ?>"<?= field_aria('price') ?>>
                            <label for="f-currency" class="visually-hidden"><?= e(t('currency_label')) ?></label>
                            <select id="f-currency" name="currency">
                                <option value="USD" <?= $v('currency', 'USD') === 'USD' ? 'selected' : '' ?>>$ USD</option>
                                <option value="IQD" <?= $v('currency') === 'IQD' ? 'selected' : '' ?>><?= e(t('iqd_short')) ?> IQD</option>
                            </select>
                        </div>
                        <?= field_error('price') ?>
                    </div>
                    <div class="field" data-rent-only>
                        <label for="f-rent_period"><?= e(t('field_rent_period')) ?></label>
                        <select id="f-rent_period" name="rent_period">
                            <?php foreach (RENT_PERIODS as $rp): ?>
                                <option value="<?= $rp ?>" <?= $v('rent_period', 'yearly') === $rp ? 'selected' : '' ?>><?= e(t('period_' . $rp)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="f-description"><?= e(t('field_description')) ?></label>
                    <textarea id="f-description" name="description" rows="7" maxlength="5000" data-counter<?= field_aria('description') ?>><?= e($v('description')) ?></textarea>
                    <p class="hint"><span data-count-for="f-description">0</span> / 5000</p>
                    <?= field_error('description') ?>
                </div>
            </section>

            <section class="panel" aria-labelledby="sec-loc">
                <h2 id="sec-loc" class="panel-title"><?= e(t('location')) ?></h2>
                <div class="grid-2">
                    <div class="field">
                        <label for="f-city_id"><?= e(t('field_city_id')) ?></label>
                        <select id="f-city_id" name="city_id" required<?= field_aria('city_id') ?>>
                            <option value=""><?= e(t('choose')) ?></option>
                            <?php foreach (cities() as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (string) $v('city_id') === (string) $c['id'] ? 'selected' : '' ?>><?= e(city_name($c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error('city_id') ?>
                    </div>
                    <div class="field">
                        <label for="f-district"><?= e(t('field_district')) ?></label>
                        <input id="f-district" name="district" maxlength="100" value="<?= e($v('district')) ?>" placeholder="<?= e(t('district_ph')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="f-project"><?= e(t('field_project')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                    <input id="f-project" name="project" maxlength="100" value="<?= e($v('project')) ?>" placeholder="<?= e(t('project_ph')) ?>">
                </div>
                <div class="field">
                    <label for="f-address"><?= e(t('field_address')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                    <input id="f-address" name="address" maxlength="200" autocomplete="street-address" value="<?= e($v('address')) ?>">
                </div>
                <div class="field">
                    <p class="field-label"><?= e(t('pin_on_map')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></p>
                    <div class="picker-map" data-picker-map data-center="<?= e(implode(',', config('map_center'))) ?>" role="application" aria-label="<?= e(t('pin_on_map')) ?>"></div>
                    <p class="hint"><?= e(t('pin_hint')) ?></p>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label for="f-lat"><?= e(t('field_lat')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                        <input id="f-lat" name="lat" inputmode="decimal" dir="ltr" placeholder="36.1911" value="<?= e($v('lat')) ?>"<?= field_aria('lat') ?>>
                        <?= field_error('lat') ?>
                    </div>
                    <div class="field">
                        <label for="f-lng"><?= e(t('field_lng')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                        <input id="f-lng" name="lng" inputmode="decimal" dir="ltr" placeholder="44.0092" value="<?= e($v('lng')) ?>"<?= field_aria('lng') ?>>
                        <?= field_error('lng') ?>
                    </div>
                </div>
            </section>

            <section class="panel" aria-labelledby="sec-details">
                <h2 id="sec-details" class="panel-title"><?= e(t('details')) ?></h2>
                <div class="grid-3">
                    <div class="field">
                        <label for="f-area"><?= e(t('field_area')) ?> (<?= e(t('sqm')) ?>)</label>
                        <input id="f-area" name="area" type="number" inputmode="decimal" min="1" step="0.01" required value="<?= e($v('area')) ?>"<?= field_aria('area') ?>>
                        <?= field_error('area') ?>
                    </div>
                    <div class="field">
                        <label for="f-bedrooms"><?= e(t('field_bedrooms')) ?></label>
                        <input id="f-bedrooms" name="bedrooms" type="number" inputmode="numeric" min="0" max="50" value="<?= e($v('bedrooms', '0')) ?>"<?= field_aria('bedrooms') ?>>
                        <?= field_error('bedrooms') ?>
                    </div>
                    <div class="field">
                        <label for="f-bathrooms"><?= e(t('field_bathrooms')) ?></label>
                        <input id="f-bathrooms" name="bathrooms" type="number" inputmode="numeric" min="0" max="50" value="<?= e($v('bathrooms', '0')) ?>"<?= field_aria('bathrooms') ?>>
                        <?= field_error('bathrooms') ?>
                    </div>
                    <div class="field">
                        <label for="f-floor"><?= e(t('field_floor')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                        <input id="f-floor" name="floor" type="number" inputmode="numeric" min="-5" max="200" value="<?= e($v('floor')) ?>"<?= field_aria('floor') ?>>
                        <?= field_error('floor') ?>
                    </div>
                    <div class="field">
                        <label for="f-year_built"><?= e(t('field_year_built')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                        <input id="f-year_built" name="year_built" type="number" inputmode="numeric" min="1900" max="<?= (int) date('Y') + 5 ?>" value="<?= e($v('year_built')) ?>"<?= field_aria('year_built') ?>>
                        <?= field_error('year_built') ?>
                    </div>
                    <div class="field">
                        <label for="f-deed"><?= e(t('deed')) ?></label>
                        <select id="f-deed" name="deed">
                            <option value=""><?= e(t('choose')) ?></option>
                            <?php foreach (DEED_TYPES as $dt): ?>
                                <option value="<?= $dt ?>" <?= $v('deed') === $dt ? 'selected' : '' ?>><?= e(t('deed_' . $dt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="check check-boxed">
                        <input type="checkbox" name="furnished" value="1" <?= $furnished ? 'checked' : '' ?>>
                        <span><?= e(t('furnished')) ?></span>
                    </label>
                </div>
                <fieldset class="feature-picker">
                    <legend><?= e(t('features')) ?></legend>
                    <?php foreach (PROPERTY_FEATURES as $ft): ?>
                        <label class="chip-check"><input type="checkbox" name="features[]" value="<?= $ft ?>" <?= in_array($ft, $selFeatures, true) ? 'checked' : '' ?>><span><?= e(t('feat_' . $ft)) ?></span></label>
                    <?php endforeach; ?>
                </fieldset>
            </section>

            <section class="panel" aria-labelledby="sec-images" id="images">
                <h2 id="sec-images" class="panel-title"><?= e(t('photos')) ?></h2>
                <p class="hint"><?= e(t('upload_hint', ['n' => config('max_images'), 'mb' => $maxMb])) ?></p>

                <?php if ($images): ?>
                    <ul class="image-manager">
                        <?php foreach ($images as $i => $img): ?>
                            <li>
                                <img src="<?= e(upload_url($img['thumb'])) ?>" alt="<?= e(t('photo_n', ['n' => $i + 1])) ?>" width="640" height="427" loading="lazy">
                                <?php if ($i === 0): ?><span class="badge badge-featured image-cover-badge"><?= e(t('cover')) ?></span><?php endif; ?>
                                <div class="image-actions">
                                    <?php if ($i !== 0): ?>
                                        <button class="btn btn-light btn-sm" type="submit" form="cover-<?= (int) $img['id'] ?>"><?= e(t('set_cover')) ?></button>
                                    <?php endif; ?>
                                    <button class="btn btn-light btn-sm text-danger" type="submit" form="delimg-<?= (int) $img['id'] ?>" aria-label="<?= e(t('delete_photo_n', ['n' => $i + 1])) ?>"><svg aria-hidden="true"><use href="#i-trash"></use></svg></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="dropzone" data-dropzone>
                    <svg aria-hidden="true"><use href="#i-upload"></use></svg>
                    <label for="f-images" class="dropzone-label">
                        <strong><?= e(t('choose_photos')) ?></strong>
                        <span class="muted"><?= e(t('or_drag')) ?></span>
                    </label>
                    <input id="f-images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,image/*"
                           data-max-bytes="<?= (int) config('max_upload_bytes') ?>" data-max-files="<?= (int) config('max_images') - count($images) ?>"
                           aria-describedby="upload-status"<?= field_aria('images') ?>>
                    <?= field_error('images') ?>
                </div>
                <p id="upload-status" class="hint" aria-live="polite"></p>
                <ul class="upload-preview" data-preview></ul>
            </section>

            <section class="panel" aria-labelledby="sec-videos" id="videos">
                <h2 id="sec-videos" class="panel-title"><?= e(t('videos')) ?></h2>
                <p class="hint"><?= e(t('video_hint', ['n' => config('max_videos'), 'mb' => (int) (config('max_video_bytes') / 1048576)])) ?></p>
                <?php if (!empty($videos)): ?>
                    <ul class="video-manager">
                        <?php foreach ($videos as $i => $vid): ?>
                            <li>
                                <video controls playsinline preload="metadata" src="<?= e(upload_url($vid['filename'])) ?>#t=0.1" aria-label="<?= e(t('video_n', ['n' => $i + 1])) ?>"></video>
                                <button class="btn btn-light btn-sm text-danger" type="submit" form="delvid-<?= (int) $vid['id'] ?>"><svg aria-hidden="true"><use href="#i-trash"></use></svg><?= e(t('delete')) ?><span class="visually-hidden"> <?= e(t('video_n', ['n' => $i + 1])) ?></span></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (count($videos ?? []) < (int) config('max_videos')): ?>
                    <div class="field">
                        <label for="f-videos"><?= e(t('choose_videos')) ?></label>
                        <input id="f-videos" type="file" name="videos[]" multiple accept="video/mp4,video/webm,video/quicktime"
                               data-max-bytes="<?= (int) config('max_video_bytes') ?>" data-video-input>
                        <p class="hint" data-video-status aria-live="polite"></p>
                    </div>
                <?php endif; ?>
            </section>

            <div class="form-footer">
                <button class="btn btn-primary btn-lg" type="submit"><?= e($isEdit ? t('save_changes') : t('publish_listing')) ?></button>
                <a class="btn btn-ghost" href="<?= e(url('dashboard')) ?>"><?= e(t('cancel')) ?></a>
            </div>
        </form>

        <?php foreach ($images as $img): ?>
            <form id="cover-<?= (int) $img['id'] ?>" method="post" action="<?= e(url('dashboard/images/' . $img['id'] . '/cover')) ?>" hidden><?= csrf_field() ?></form>
            <form id="delimg-<?= (int) $img['id'] ?>" method="post" action="<?= e(url('dashboard/images/' . $img['id'] . '/delete')) ?>" hidden data-confirm="<?= e(t('confirm_delete_photo')) ?>"><?= csrf_field() ?></form>
        <?php endforeach; ?>
        <?php foreach ($videos ?? [] as $vid): ?>
            <form id="delvid-<?= (int) $vid['id'] ?>" method="post" action="<?= e(url('dashboard/videos/' . $vid['id'] . '/delete')) ?>" hidden data-confirm="<?= e(t('confirm_delete_video')) ?>"><?= csrf_field() ?></form>
        <?php endforeach; ?>

        <?php if ($isEdit): ?>
            <section class="panel danger-zone" aria-labelledby="dz-h">
                <h2 id="dz-h" class="panel-title"><?= e(t('danger_zone')) ?></h2>
                <p class="muted"><?= e(t('delete_listing_text')) ?></p>
                <form method="post" action="<?= e(url('dashboard/properties/' . $p['id'] . '/delete')) ?>" data-confirm="<?= e(t('confirm_delete_listing')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger" type="submit"><svg aria-hidden="true"><use href="#i-trash"></use></svg><?= e(t('delete_listing')) ?></button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
