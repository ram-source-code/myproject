<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main narrow">
        <h1 class="page-title"><?= e(t('broadcast')) ?></h1>
        <dl class="stat-tiles">
            <div><dt><?= e(t('devices')) ?></dt><dd><?= (int) $stats['devices'] ?></dd></div>
            <div><dt><?= e(t('site_news_subs')) ?></dt><dd><?= (int) $stats['news'] ?></dd></div>
            <div><dt><?= e(t('office_follows')) ?></dt><dd><?= (int) $stats['follows'] ?></dd></div>
        </dl>
        <form method="post" action="<?= e(url('admin/notifications')) ?>" class="panel stack" novalidate data-validate data-push-preview>
            <?= csrf_field() ?>
            <div class="field">
                <label for="n-target"><?= e(t('send_to')) ?></label>
                <select id="n-target" name="target"<?= field_aria('target') ?>>
                    <option value="all"><?= e(t('everyone_news', ['n' => (int) $stats['news']])) ?></option>
                    <?php foreach ($offices as $o): ?>
                        <option value="<?= (int) $o['id'] ?>" <?= old('target') === (string) $o['id'] ? 'selected' : '' ?>><?= e(t('followers_of', ['name' => $o['name']])) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('target') ?>
            </div>
            <div class="field">
                <label for="n-title"><?= e(t('notif_title')) ?></label>
                <input id="n-title" name="title" required minlength="3" maxlength="80" value="<?= e(old('title')) ?>"<?= field_aria('title') ?>>
                <?= field_error('title') ?>
            </div>
            <div class="field">
                <label for="n-body"><?= e(t('notif_body')) ?></label>
                <textarea id="n-body" name="body" rows="3" required minlength="3" maxlength="240"<?= field_aria('body') ?>><?= e(old('body')) ?></textarea>
                <?= field_error('body') ?>
            </div>
            <div class="field">
                <label for="n-prop"><?= e(t('notif_link_id')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                <input id="n-prop" name="property_id" inputmode="numeric" value="<?= e(old('property_id')) ?>" placeholder="#"<?= field_aria('property_id') ?>>
                <?= field_error('property_id') ?>
            </div>
            <div class="push-preview" aria-hidden="true">
                <img src="<?= e(asset('img/icon-192.png')) ?>" alt="" width="40" height="40">
                <div><strong data-preview-title>…</strong><p data-preview-body class="muted small">…</p></div>
            </div>
            <div><button class="btn btn-primary" type="submit"><svg aria-hidden="true"><use href="#i-send"></use></svg><?= e(t('send')) ?></button></div>
        </form>
        <section class="panel" aria-labelledby="ah-h">
            <h2 id="ah-h" class="panel-title"><?= e(t('sent_history')) ?></h2>
            <?php if ($history): ?>
                <ul class="notif-list">
                    <?php foreach ($history as $n): ?>
                        <li>
                            <p><strong><?= e($n['title']) ?></strong> <span class="badge"><?= e($n['audience'] === 'all' ? t('everyone') : ($n['office_name'] ?? '')) ?></span><?php if ($n['kind'] === 'auto'): ?> <span class="badge"><?= e(t('auto')) ?></span><?php endif; ?></p>
                            <p class="muted small"><?= e($n['body']) ?></p>
                            <p class="muted small"><?= e(fmt_date($n['created_at'])) ?> · <?= e(t('delivered_n', ['n' => (int) $n['sent']])) ?><?php if ($n['failed']): ?> · <?= e(t('failed_n', ['n' => (int) $n['failed']])) ?><?php endif; ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?><p class="muted"><?= e(t('nothing_sent')) ?></p><?php endif; ?>
        </section>
    </div>
</div>
