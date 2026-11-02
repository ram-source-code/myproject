<?php /** @var ?array $o @var array $history @var array $listings */ ?>
<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main narrow">
        <h1 class="page-title"><?= e(t('send_notification')) ?></h1>
        <?php if (!$o): ?>
            <div class="alert alert-info"><svg aria-hidden="true"><use href="#i-alert"></use></svg><p><?= e(t('create_office_first')) ?> <a href="<?= e(url('dashboard/office')) ?>"><?= e(t('my_office')) ?></a></p></div>
        <?php else: ?>
            <p class="muted"><?= e(t('push_explainer', ['n' => (int) $o['followers']])) ?></p>
            <dl class="stat-tiles">
                <div><dt><?= e(t('followers')) ?></dt><dd><?= (int) $o['followers'] ?></dd></div>
                <div><dt><?= e(t('sent_today')) ?></dt><dd><?= (int) $sentToday ?> / <?= (int) $limit ?></dd></div>
            </dl>
            <form method="post" action="<?= e(url('dashboard/notifications')) ?>" class="panel stack" novalidate data-validate data-push-preview>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="n-title"><?= e(t('notif_title')) ?></label>
                    <input id="n-title" name="title" required minlength="3" maxlength="80" value="<?= e(old('title')) ?>" placeholder="<?= e(t('notif_title_ph')) ?>"<?= field_aria('title') ?>>
                    <?= field_error('title') ?>
                </div>
                <div class="field">
                    <label for="n-body"><?= e(t('notif_body')) ?></label>
                    <textarea id="n-body" name="body" rows="3" required minlength="3" maxlength="240" placeholder="<?= e(t('notif_body_ph')) ?>"<?= field_aria('body') ?>><?= e(old('body')) ?></textarea>
                    <?= field_error('body') ?>
                </div>
                <div class="field">
                    <label for="n-prop"><?= e(t('notif_link')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                    <select id="n-prop" name="property_id"<?= field_aria('property_id') ?>>
                        <option value=""><?= e(t('notif_link_office')) ?></option>
                        <?php foreach ($listings as $l): ?>
                            <option value="<?= (int) $l['id'] ?>" <?= old('property_id') === (string) $l['id'] ? 'selected' : '' ?>>#<?= (int) $l['id'] ?> — <?= e(str_limit($l['title'], 60)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('property_id') ?>
                </div>
                <div class="push-preview" aria-hidden="true">
                    <img src="<?= e($o['logo'] ? upload_url($o['logo']) : asset('img/icon-192.png')) ?>" alt="" width="40" height="40">
                    <div><strong data-preview-title><?= e($o['name']) ?>: …</strong><p data-preview-body class="muted small">…</p></div>
                </div>
                <div>
                    <button class="btn btn-primary" type="submit" <?= ($sentToday >= $limit && !is_admin()) || !$o['followers'] ? 'disabled' : '' ?>>
                        <svg aria-hidden="true"><use href="#i-send"></use></svg><?= e(t('send_to_followers', ['n' => (int) $o['followers']])) ?>
                    </button>
                    <?php if (!$o['followers']): ?><p class="hint"><?= e(t('no_followers_yet')) ?></p><?php endif; ?>
                </div>
            </form>

            <section class="panel" aria-labelledby="hist-h">
                <h2 id="hist-h" class="panel-title"><?= e(t('sent_history')) ?></h2>
                <?php if ($history): ?>
                    <ul class="notif-list">
                        <?php foreach ($history as $n): ?>
                            <li>
                                <p><strong><?= e($n['title']) ?></strong> <?php if ($n['kind'] === 'auto'): ?><span class="badge"><?= e(t('auto')) ?></span><?php endif; ?></p>
                                <p class="muted small"><?= e($n['body']) ?></p>
                                <p class="muted small"><?= e(fmt_date($n['created_at'])) ?> · <?= e(t('delivered_n', ['n' => (int) $n['sent']])) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted"><?= e(t('nothing_sent')) ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
