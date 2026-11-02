<?php
/** Compact hero search. */
?>
<form class="hero-search" method="get" action="<?= e(url('properties')) ?>" role="search">
    <fieldset class="segmented">
        <legend class="visually-hidden"><?= e(t('purpose')) ?></legend>
        <label><input type="radio" name="purpose" value="" checked><span><?= e(t('all')) ?></span></label>
        <label><input type="radio" name="purpose" value="sale"><span><?= e(t('purpose_sale')) ?></span></label>
        <label><input type="radio" name="purpose" value="rent"><span><?= e(t('purpose_rent')) ?></span></label>
    </fieldset>
    <div class="hero-search-fields">
        <div class="field">
            <label for="hs-q"><?= e(t('keyword')) ?></label>
            <input id="hs-q" type="search" name="q" placeholder="<?= e(t('keyword_ph')) ?>" maxlength="100">
        </div>
        <div class="field">
            <label for="hs-city"><?= e(t('city')) ?></label>
            <select id="hs-city" name="city">
                <option value=""><?= e(t('all_cities')) ?></option>
                <?php foreach (cities() as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e(city_name($c)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="hs-type"><?= e(t('type')) ?></label>
            <select id="hs-type" name="type">
                <option value=""><?= e(t('all_types')) ?></option>
                <?php foreach (PROPERTY_TYPES as $tp): ?>
                    <option value="<?= e($tp) ?>"><?= e(t('type_' . $tp)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary btn-lg" type="submit">
            <svg aria-hidden="true"><use href="#i-search"></use></svg><?= e(t('search')) ?>
        </button>
    </div>
</form>
