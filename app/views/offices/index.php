<div class="container page-head">
    <h1 class="page-title"><?= e(t('offices')) ?></h1>
    <p class="muted"><?= e(t('offices_sub')) ?></p>
</div>
<div class="container">
    <form method="get" action="<?= e(url('offices')) ?>" class="office-search" role="search">
        <div class="field">
            <label for="oq" class="visually-hidden"><?= e(t('search_offices')) ?></label>
            <input id="oq" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search_offices')) ?>">
        </div>
        <div class="field">
            <label for="oc" class="visually-hidden"><?= e(t('city')) ?></label>
            <select id="oc" name="city">
                <option value=""><?= e(t('all_cities')) ?></option>
                <?php foreach (cities() as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $city === (int) $c['id'] ? 'selected' : '' ?>><?= e(city_name($c)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><svg aria-hidden="true"><use href="#i-search"></use></svg><?= e(t('search')) ?></button>
    </form>

    <?php if ($items): ?>
        <h2 class="visually-hidden"><?= e(t('results')) ?></h2>
        <ul class="office-grid">
            <?php foreach ($items as $o): ?>
                <li><?= render_partial('partials/office_card', ['o' => $o]) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="empty-state">
            <?= render_partial('partials/art/empty') ?>
            <p><?= e(t('no_offices')) ?></p>
        </div>
    <?php endif; ?>

    <section class="cta-band cta-soft" aria-labelledby="oc-h">
        <div>
            <h2 id="oc-h"><?= e(t('are_you_office')) ?></h2>
            <p><?= e(t('are_you_office_text')) ?></p>
        </div>
        <a class="btn btn-light btn-lg" href="<?= e(url('register', ['type' => 'office'])) ?>"><?= e(t('register_office')) ?></a>
    </section>
</div>
