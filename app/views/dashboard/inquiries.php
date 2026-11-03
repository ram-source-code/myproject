<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <h1 class="page-title"><?= e(t('inquiries')) ?></h1>
        <?php if ($items): ?>
            <ul class="inquiry-list">
                <?php foreach ($items as $q): ?>
                    <li class="panel inquiry<?= $q['is_read'] ? '' : ' is-unread' ?>">
                        <div class="inquiry-head">
                            <div>
                                <p class="inquiry-from">
                                    <?php if (!$q['is_read']): ?><span class="dot" aria-hidden="true"></span><span class="visually-hidden"><?= e(t('unread')) ?>:</span><?php endif; ?>
                                    <?= e($q['name']) ?>
                                </p>
                                <p class="muted small">
                                    <?= e(t('about')) ?> <a href="<?= e(url('property/' . $q['property_id'])) ?>"><?= e($q['property_title']) ?></a>
                                    · <time datetime="<?= e($q['created_at']) ?>"><?= e($q['created_at']) ?></time>
                                </p>
                            </div>
                            <form method="post" action="<?= e(url('dashboard/inquiries/' . $q['id'] . '/read')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm" type="submit"><?= e($q['is_read'] ? t('mark_unread') : t('mark_read')) ?></button>
                            </form>
                        </div>
                        <p class="inquiry-msg"><?= nl2br(e($q['message'])) ?></p>
                        <div class="inquiry-contact">
                            <a class="btn btn-outline btn-sm" href="mailto:<?= e($q['email']) ?>"><svg aria-hidden="true"><use href="#i-mail"></use></svg><span dir="ltr"><?= e($q['email']) ?></span></a>
                            <?php if ($q['phone']): ?>
                                <a class="btn btn-outline btn-sm" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $q['phone'])) ?>"><svg aria-hidden="true"><use href="#i-phone"></use></svg><span dir="ltr"><?= e($q['phone']) ?></span></a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state">
                <?= render_partial('partials/art/empty') ?>
                <p><?= e(t('no_inquiries')) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
