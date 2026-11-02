<?php
/** @var array $items @var ?array $done */
$statusOf = static function (array $o): array {
    if ($o['activated_at'] === null) {
        return ['pending', t('office_status_pending'), 'status-pending'];
    }
    if ($o['active_until'] !== null && strtotime($o['active_until'] . ' UTC') <= time()) {
        return ['expired', t('office_status_expired', ['date' => substr($o['active_until'], 0, 10)]), 'status-rejected'];
    }
    if ($o['active_until'] !== null && strtotime($o['active_until'] . ' UTC') <= time() + RENEW_SOON_DAYS * 86400) {
        return ['soon', t('office_active_until', ['date' => substr($o['active_until'], 0, 10)]), 'status-pending'];
    }
    return ['active', $o['active_until'] ? t('office_active_until', ['date' => substr($o['active_until'], 0, 10)]) : t('office_status_active'), 'status-published'];
};
$pending = count(array_filter($items, static fn($o) => $o['activated_at'] === null));
?>
<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <h1 class="page-title"><?= e(t('offices')) ?></h1>
        <p class="muted"><?= e(t('activation_help')) ?><?php if ($pending): ?> <strong><?= e(t('pending_offices', ['n' => $pending])) ?></strong><?php endif; ?></p>

        <?php if ($done): ?>
            <section class="panel code-panel" aria-labelledby="done-h" tabindex="-1" data-autofocus>
                <h2 id="done-h" class="panel-title"><svg class="inline-icon" aria-hidden="true"><use href="#i-check"></use></svg> <?= e(t('office_activated_now', ['office' => $done['office']])) ?></h2>
                <p class="muted"><?= e($done['until'] ? t('office_active_until', ['date' => $done['until']]) : t('office_status_active')) ?> · <?= e(t('tell_them')) ?></p>
                <div class="btn-row">
                    <?php if ($done['wa']): ?>
                        <a class="btn btn-whatsapp" href="<?= e($done['wa']) ?>" target="_blank" rel="noopener noreferrer"><svg aria-hidden="true"><use href="#i-whatsapp"></use></svg><?= e(t('send_on_whatsapp')) ?></a>
                    <?php endif; ?>
                    <button class="btn btn-outline" type="button" data-copy="<?= e($done['message']) ?>"><svg aria-hidden="true"><use href="#i-copy"></use></svg><?= e(t('copy_message')) ?></button>
                </div>
            </section>
        <?php endif; ?>

        <div class="table-wrap" role="region" aria-label="<?= e(t('offices')) ?>" tabindex="0">
            <table class="table">
                <thead><tr>
                    <th scope="col"><?= e(t('office_name')) ?></th>
                    <th scope="col"><?= e(t('subscription')) ?></th>
                    <th scope="col"><?= e(t('admin_listings')) ?></th>
                    <th scope="col"><?= e(t('verified')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $o): [$st, $label, $cls] = $statusOf($o); ?>
                    <tr>
                        <td data-label="<?= e(t('office_name')) ?>">
                            <a class="row-title" href="<?= e(url('office/' . $o['id'])) ?>"><?= e($o['name']) ?></a>
                            <p class="muted small"><?= implode(' · ', array_filter([($o['whatsapp'] ?: $o['phone']) ? '<span dir="ltr">' . e($o['whatsapp'] ?: $o['phone']) . '</span>' : '', e(city_name($o)), (int) $o['followers'] . ' ' . e(t('followers'))])) ?></p>
                        </td>
                        <td data-label="<?= e(t('subscription')) ?>">
                            <span class="status <?= e($cls) ?>"><?= e($label) ?></span>
                            <form method="post" action="<?= e(url('admin/offices/' . $o['id'] . '/activate')) ?>" class="code-form">
                                <?= csrf_field() ?>
                                <label class="visually-hidden" for="m-<?= (int) $o['id'] ?>"><?= e(t('plan_length')) ?>: <?= e($o['name']) ?></label>
                                <select id="m-<?= (int) $o['id'] ?>" name="months">
                                    <?php foreach (ACTIVATION_MONTHS as $m): ?><option value="<?= $m ?>"<?= $m === 1 ? ' selected' : '' ?>><?= e($m === 1 ? t('month_1') : t('months_n', ['n' => $m])) ?></option><?php endforeach; ?>
                                    <option value="0"><?= e(t('no_end_date')) ?></option>
                                </select>
                                <button class="btn btn-primary btn-sm" type="submit"><svg aria-hidden="true"><use href="#i-check"></use></svg><?= e(in_array($st, ['pending', 'expired'], true) ? t('activate') : t('extend')) ?><span class="visually-hidden">: <?= e($o['name']) ?></span></button>
                            </form>
                            <?php if (in_array($st, ['soon', 'expired'], true) && ($ph = office_contact_phone($o))): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(whatsapp_url($ph, t($st === 'soon' ? 'renew_reminder' : 'renew_ended', ['app' => app_name(), 'office' => $o['name'], 'date' => substr((string) $o['active_until'], 0, 10)]))) ?>" target="_blank" rel="noopener noreferrer"><svg aria-hidden="true"><use href="#i-whatsapp"></use></svg><?= e(t('remind_whatsapp')) ?></a>
                            <?php endif; ?>
                            <?php if ($st !== 'pending'): ?>
                                <form method="post" action="<?= e(url('admin/offices/' . $o['id'] . '/deactivate')) ?>" data-confirm="<?= e(t('deactivate_confirm')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-sm text-danger" type="submit"><?= e(t('deactivate')) ?><span class="visually-hidden">: <?= e($o['name']) ?></span></button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?= e(t('admin_listings')) ?>"><?= (int) $o['listings'] ?></td>
                        <td data-label="<?= e(t('verified')) ?>">
                            <form method="post" action="<?= e(url('admin/offices/' . $o['id'] . '/verify')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm verify-toggle" type="submit" aria-pressed="<?= $o['is_verified'] ? 'true' : 'false' ?>">
                                    <svg aria-hidden="true"><use href="#i-verified"></use></svg><?= e($o['is_verified'] ? t('verified') : t('verify')) ?><span class="visually-hidden">: <?= e($o['name']) ?></span>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
