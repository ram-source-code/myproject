<?php
/** @var array $filters @var array $items @var array $pg @var string $viewMode */
$f = $filters;
$query = array_filter($f, static fn($v) => $v !== null && $v !== '' && !($v === 'newest'));
unset($query['office']);
$activeCount = count(array_diff_key($query, ['sort' => 1, 'q' => 1]));
$chip = static function (array $set) use ($query, $viewMode): string {
    $q = array_merge($query, $set);
    foreach ($set as $k => $v) {
        if (($query[$k] ?? null) === $v) {
            unset($q[$k]);       // clicking an active chip toggles it off
        }
    }
    if ($viewMode === 'map') {
        $q['view'] = 'map';
    }
    return url('properties', $q);
};
$isOn = static fn(string $k, $v) => ($query[$k] ?? null) === $v;
?>
<div class="container page-head page-head-row">
    <div>
        <h1 class="page-title">
            <?php if (!empty($heading)): ?><?= e($heading) ?>
            <?php elseif ($f['purpose'] === 'sale'): ?><?= e(t('props_for_sale')) ?>
            <?php elseif ($f['purpose'] === 'rent'): ?><?= e(t('props_for_rent')) ?>
            <?php else: ?><?= e(t('nav_listings')) ?><?php endif; ?>
        </h1>
        <p class="muted" role="status"><?= e(t('results_count', ['n' => number_format($pg['total'])])) ?></p>
    </div>
    <div class="view-toggle" role="group" aria-label="<?= e(t('view_mode')) ?>">
        <a href="<?= e(url('properties', $query)) ?>"<?= $viewMode === 'list' ? ' aria-current="page"' : '' ?>><svg aria-hidden="true"><use href="#i-grid"></use></svg><?= e(t('list')) ?></a>
        <a href="<?= e(url('properties', $query + ['view' => 'map'])) ?>"<?= $viewMode === 'map' ? ' aria-current="page"' : '' ?>><svg aria-hidden="true"><use href="#i-map"></use></svg><?= e(t('map')) ?></a>
    </div>
</div>

<nav class="container quick-chips scroll-x" aria-label="<?= e(t('quick_filters')) ?>">
    <?php if ($f['near'] !== ''): ?>
        <a class="qchip qchip-near" aria-current="true" href="<?= e(url('properties', array_diff_key($query, ['near' => 1, 'r' => 1, 'sort' => 1]) + ($viewMode === 'map' ? ['view' => 'map'] : []))) ?>">
            <svg aria-hidden="true"><use href="#i-locate"></use></svg><?= e(t('near_me_r', ['r' => (int) $f['r']])) ?>
            <svg class="qchip-x" aria-hidden="true"><use href="#i-x"></use></svg><span class="visually-hidden"><?= e(t('remove_location')) ?></span>
        </a>
    <?php else: ?>
        <button type="button" class="qchip qchip-near" data-near-me data-target="<?= e(url('properties', $query + ($viewMode === 'map' ? ['view' => 'map'] : []))) ?>">
            <svg aria-hidden="true"><use href="#i-locate"></use></svg><?= e(t('near_me')) ?>
        </button>
    <?php endif; ?>
    <button type="button" class="qchip qchip-filter" data-open-filters aria-controls="filter-sheet">
        <svg aria-hidden="true"><use href="#i-filter"></use></svg><?= e(t('filters')) ?><?php if ($activeCount): ?> <span class="badge"><?= $activeCount ?></span><?php endif; ?>
    </button>
    <a class="qchip" href="<?= e($chip(['purpose' => 'sale'])) ?>"<?= $isOn('purpose', 'sale') ? ' aria-current="true"' : '' ?>><?= e(t('purpose_sale')) ?></a>
    <a class="qchip" href="<?= e($chip(['purpose' => 'rent'])) ?>"<?= $isOn('purpose', 'rent') ? ' aria-current="true"' : '' ?>><?= e(t('purpose_rent')) ?></a>
    <span class="qchip-sep" aria-hidden="true"></span>
    <?php foreach (['house', 'apartment', 'villa', 'land', 'shop', 'office'] as $tp): ?>
        <a class="qchip" href="<?= e($chip(['type' => $tp])) ?>"<?= $isOn('type', $tp) ? ' aria-current="true"' : '' ?>><?= e(t('type_' . $tp)) ?></a>
    <?php endforeach; ?>
    <span class="qchip-sep" aria-hidden="true"></span>
    <a class="qchip" href="<?= e($chip(['deed' => 'tapo'])) ?>"<?= $isOn('deed', 'tapo') ? ' aria-current="true"' : '' ?>><?= e(t('deed_tapo')) ?></a>
    <a class="qchip" href="<?= e($chip(['furnished' => 1])) ?>"<?= $isOn('furnished', 1) ? ' aria-current="true"' : '' ?>><?= e(t('furnished')) ?></a>
</nav>

<div class="container listing-layout<?= $viewMode === 'map' ? ' is-map' : '' ?>">
    <details class="filters-panel" open>
        <summary class="filters-summary">
            <svg aria-hidden="true"><use href="#i-filter"></use></svg><?= e(t('filters')) ?>
            <?php if ($activeCount): ?><span class="badge"><?= $activeCount ?></span><?php endif; ?>
        </summary>
        <form method="get" action="<?= e(url('properties')) ?>" class="filters-form" role="search" aria-label="<?= e(t('filters')) ?>">
            <?php if ($viewMode === 'map'): ?><input type="hidden" name="view" value="map"><?php endif; ?>
            <?php if ($f['near'] !== ''): ?>
                <input type="hidden" name="near" value="<?= e($f['near']) ?>">
                <div class="field">
                    <label for="f-r"><svg class="inline-icon" aria-hidden="true"><use href="#i-locate"></use></svg> <?= e(t('distance_from_you')) ?></label>
                    <select id="f-r" name="r">
                        <?php foreach (NEAR_RADII as $rad): ?>
                            <option value="<?= $rad ?>" <?= (int) $f['r'] === $rad ? 'selected' : '' ?>><?= e(t('within_km', ['r' => $rad])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="f-q"><?= e(t('keyword')) ?></label>
                <input id="f-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('keyword_ph')) ?>" maxlength="100">
            </div>
            <fieldset class="segmented segmented-full">
                <legend><?= e(t('purpose')) ?></legend>
                <?php foreach (['' => t('all'), 'sale' => t('purpose_sale'), 'rent' => t('purpose_rent')] as $v => $l): ?>
                    <label><input type="radio" name="purpose" value="<?= e($v) ?>" <?= $f['purpose'] === $v ? 'checked' : '' ?>><span><?= e($l) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="field">
                <label for="f-city"><?= e(t('city')) ?></label>
                <select id="f-city" name="city">
                    <option value=""><?= e(t('all_cities')) ?></option>
                    <?php foreach (cities() as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $f['city'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(city_name($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php $cityDistricts = $f['city'] ? (districts_by_city()[$f['city']] ?? []) : []; ?>
            <div class="field" data-district-field<?= $cityDistricts ? '' : ' hidden' ?>>
                <label for="f-district"><?= e(t('field_district')) ?></label>
                <select id="f-district" name="district" data-districts="<?= e(districts_json()) ?>" data-city-select="f-city">
                    <option value=""><?= e(t('all_districts')) ?></option>
                    <?php foreach ($cityDistricts as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= $f['district'] === (int) $d['id'] ? 'selected' : '' ?>><?= e(district_name($d)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-type"><?= e(t('type')) ?></label>
                <select id="f-type" name="type">
                    <option value=""><?= e(t('all_types')) ?></option>
                    <?php foreach (PROPERTY_TYPES as $tp): ?>
                        <option value="<?= e($tp) ?>" <?= $f['type'] === $tp ? 'selected' : '' ?>><?= e(t('type_' . $tp)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <fieldset class="field-row">
                <legend><?= e(t('price_range')) ?> ($)</legend>
                <div class="field">
                    <label for="f-min" class="visually-hidden"><?= e(t('min')) ?></label>
                    <input id="f-min" type="number" name="min_price" inputmode="numeric" min="0" step="1000" placeholder="<?= e(t('min')) ?>" value="<?= e($f['min_price']) ?>">
                </div>
                <div class="field">
                    <label for="f-max" class="visually-hidden"><?= e(t('max')) ?></label>
                    <input id="f-max" type="number" name="max_price" inputmode="numeric" min="0" step="1000" placeholder="<?= e(t('max')) ?>" value="<?= e($f['max_price']) ?>">
                </div>
                <p class="hint field-row-hint"><?= e(t('price_usd_hint')) ?></p>
            </fieldset>
            <div class="field-row">
                <div class="field">
                    <label for="f-beds"><?= e(t('bedrooms')) ?></label>
                    <select id="f-beds" name="beds">
                        <option value=""><?= e(t('any')) ?></option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?= $i ?>" <?= $f['beds'] === $i ? 'selected' : '' ?>><?= $i ?>+</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="f-baths"><?= e(t('bathrooms')) ?></label>
                    <select id="f-baths" name="baths">
                        <option value=""><?= e(t('any')) ?></option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= $f['baths'] === $i ? 'selected' : '' ?>><?= $i ?>+</option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="f-area"><?= e(t('min_area')) ?></label>
                    <input id="f-area" type="number" name="min_area" inputmode="numeric" min="0" value="<?= e($f['min_area']) ?>" placeholder="<?= e(t('sqm')) ?>">
                </div>
                <div class="field">
                    <label for="f-deed"><?= e(t('deed')) ?></label>
                    <select id="f-deed" name="deed">
                        <option value=""><?= e(t('any')) ?></option>
                        <?php foreach (DEED_TYPES as $dt): ?>
                            <option value="<?= $dt ?>" <?= $f['deed'] === $dt ? 'selected' : '' ?>><?= e(t('deed_' . $dt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label class="check">
                <input type="checkbox" name="furnished" value="1" <?= $f['furnished'] ? 'checked' : '' ?>>
                <span><?= e(t('furnished')) ?></span>
            </label>
            <input type="hidden" name="sort" value="<?= e($f['sort']) ?>">
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit" data-live-count data-endpoint="<?= e(url('api/count')) ?>"><?= e(t('show_n_results', ['n' => number_format($pg['total'])])) ?></button>
                <a class="btn btn-ghost" href="<?= e(url('properties', $viewMode === 'map' ? ['view' => 'map'] : [])) ?>"><?= e(t('reset')) ?></a>
            </div>
        </form>
    </details>

    <dialog id="filter-sheet" class="sheet" aria-labelledby="sheet-h" closedby="any">
        <div class="sheet-head">
            <span class="sheet-grip" aria-hidden="true"></span>
            <h2 id="sheet-h" class="panel-title"><?= e(t('filters')) ?></h2>
            <form method="dialog"><button class="icon-btn-plain" aria-label="<?= e(t('close')) ?>"><svg aria-hidden="true"><use href="#i-x"></use></svg></button></form>
        </div>
        <div class="sheet-body" data-sheet-body></div>
    </dialog>

    <section aria-labelledby="results-h">
        <h2 id="results-h" class="visually-hidden"><?= e(t('results')) ?></h2>
        <?php if ($viewMode === 'map'): ?>
            <div class="map-wrap">
                <div id="results-map" class="results-map" data-map-results
                     data-endpoint="<?= e(url('api/map', $query)) ?>"
                     data-near="<?= e($f['near']) ?>" data-radius="<?= (int) ($f['r'] ?? 0) ?>"
                     data-search-area="<?= e(url('properties', array_diff_key($query, ['near' => 1, 'r' => 1, 'sort' => 1]) + ['view' => 'map'])) ?>"
                     data-center="<?= e(implode(',', config('map_center'))) ?>"
                     role="region" aria-label="<?= e(t('map')) ?>"></div>
                <noscript><p class="muted"><?= e(t('map_needs_js')) ?></p></noscript>
            </div>
        <?php else: ?>
            <div class="results-bar">
            <button type="button" class="btn btn-outline btn-sm" <?= $f['near'] !== '' ? 'data-location-note="' . e(t('alert_location_note')) . '"' : '' ?> data-save-search="<?= e(json_encode(compact_filters($f), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>">
                <svg aria-hidden="true"><use href="#i-bell"></use></svg><span><?= e(t('alert_me')) ?></span>
            </button>
            <form method="get" action="<?= e(url('properties')) ?>" class="sort-bar" data-autosubmit>
                <?php foreach ($query as $k => $v): if ($k === 'sort') continue; ?>
                    <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                <?php endforeach; ?>
                <label for="sort"><?= e(t('sort_by')) ?></label>
                <select id="sort" name="sort">
                    <?php foreach (array_merge($f['near'] !== '' ? ['nearest'] : [], ['newest', 'price_asc', 'price_desc', 'area_desc', 'popular']) as $s): ?>
                        <option value="<?= $s ?>" <?= $f['sort'] === $s ? 'selected' : '' ?>><?= e(t('sort_' . $s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-sm btn-outline" type="submit"><?= e(t('apply')) ?></button></noscript>
            </form>
            </div>

            <?php if ($items): ?>
                <div class="grid-cards">
                    <?php foreach ($items as $i => $p): ?>
                        <?= render_partial('partials/card', ['p' => $p, 'priority' => $i === 0]) ?>
                    <?php endforeach; ?>
                </div>
                <?= render_partial('partials/pagination', ['pg' => $pg, 'base' => 'properties', 'query' => $query]) ?>
            <?php else: ?>
                <div class="empty-state">
                    <svg aria-hidden="true"><use href="#i-search"></use></svg>
                    <p><?= e(t('no_results')) ?></p>
                    <a class="btn btn-outline" href="<?= e(url('properties')) ?>"><?= e(t('reset_filters')) ?></a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
