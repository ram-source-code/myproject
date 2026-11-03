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
        <p class="muted"><?= e(t(office_signup_open() ? 'activation_help' : 'activation_help_admin')) ?><?php if ($pending): ?> <strong><?= e(t('pending_offices', ['n' => $pending])) ?></strong><?php endif; ?></p>

        <?php if ($created): ?>
            <section class="panel code-panel" id="created" aria-labelledby="cr-h" tabindex="-1" data-autofocus>
                <h2 id="cr-h" class="panel-title"><svg class="inline-icon" aria-hidden="true"><use href="#i-check"></use></svg> <?= e(t($created['reset'] ? 'handover_reset_title' : 'handover_title', ['office' => $created['office']])) ?></h2>
                <dl class="handover">
                    <div><dt><?= e(t('sign_in_with')) ?></dt><dd dir="ltr"><?= e($created['phone']) ?></dd></div>
                    <div><dt><?= e(t('temporary_password')) ?></dt><dd dir="ltr" class="activation-code"><?= e($created['password']) ?></dd></div>
                </dl>
                <p class="muted small"><?= e(t('handover_note')) ?></p>
                <div class="btn-row">
                    <?php if ($created['wa']): ?>
                        <a class="btn btn-whatsapp" href="<?= e($created['wa']) ?>" target="_blank" rel="noopener noreferrer"><svg aria-hidden="true"><use href="#i-whatsapp"></use></svg><?= e(t('send_on_whatsapp')) ?></a>
                    <?php endif; ?>
                    <button class="btn btn-outline" type="button" data-copy="<?= e($created['message']) ?>"><svg aria-hidden="true"><use href="#i-copy"></use></svg><?= e(t('copy_message')) ?></button>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel" aria-labelledby="mode-h">
            <h2 id="mode-h" class="panel-title"><?= e(t('signup_mode_title')) ?></h2>
            <form method="post" action="<?= e(url('admin/offices/signup-mode')) ?>" class="stack">
                <?= csrf_field() ?>
                <fieldset class="choice-list">
                    <legend class="visually-hidden"><?= e(t('signup_mode_title')) ?></legend>
                    <label><input type="radio" name="mode" value="admin"<?= office_signup_open() ? '' : ' checked' ?>> <span><strong><?= e(t('signup_mode_admin')) ?></strong><br><span class="muted small"><?= e(t('signup_mode_admin_desc')) ?></span></span></label>
                    <label><input type="radio" name="mode" value="open"<?= office_signup_open() ? ' checked' : '' ?>> <span><strong><?= e(t('signup_mode_open')) ?></strong><br><span class="muted small"><?= e(t('signup_mode_open_desc')) ?></span></span></label>
                </fieldset>
                <div><button class="btn btn-outline btn-sm" type="submit"><?= e(t('save')) ?></button></div>
            </form>
        </section>

        <details class="panel new-office" id="new-office"<?= errors() ? ' open' : '' ?>>
            <summary><svg class="inline-icon" aria-hidden="true"><use href="#i-plus"></use></svg> <strong><?= e(t('new_office')) ?></strong> <span class="muted small"><?= e(t('new_office_desc')) ?></span></summary>
            <form method="post" action="<?= e(url('admin/offices/new')) ?>" class="stack" novalidate data-validate>
                <?= csrf_field() ?>
                <div class="grid-2">
                    <div class="field">
                        <label for="no-office"><?= e(t('office_name')) ?></label>
                        <input id="no-office" name="office_name" required minlength="2" maxlength="100" value="<?= e(old('office_name')) ?>"<?= field_aria('office_name') ?>>
                        <?= field_error('office_name') ?>
                    </div>
                    <div class="field">
                        <label for="no-owner"><?= e(t('owner_name')) ?></label>
                        <input id="no-owner" name="owner_name" required minlength="2" maxlength="100" value="<?= e(old('owner_name')) ?>"<?= field_aria('owner_name') ?>>
                        <?= field_error('owner_name') ?>
                    </div>
                    <div class="field">
                        <label for="no-phone"><?= e(t('phone_whatsapp')) ?></label>
                        <input id="no-phone" name="phone" type="tel" required maxlength="30" dir="ltr" placeholder="0750 000 0000" value="<?= e(old('phone')) ?>"<?= field_aria('phone') ?>>
                        <p class="hint"><?= e(t('phone_is_login')) ?></p>
                        <?= field_error('phone') ?>
                    </div>
                    <div class="field">
                        <label for="no-email"><?= e(t('email')) ?> <span class="optional">(<?= e(t('optional')) ?>)</span></label>
                        <input id="no-email" name="email" type="email" maxlength="150" dir="ltr" value="<?= e(old('email')) ?>"<?= field_aria('email') ?>>
                        <?= field_error('email') ?>
                    </div>
                    <div class="field">
                        <label for="no-city"><?= e(t('city')) ?></label>
                        <select id="no-city" name="city_id">
                            <option value=""><?= e(t('choose')) ?></option>
                            <?php foreach (cities() as $c): ?><option value="<?= (int) $c['id'] ?>"<?= old('city_id') === (string) $c['id'] ? ' selected' : '' ?>><?= e(city_name($c)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="no-months"><?= e(t('plan_length')) ?></label>
                        <select id="no-months" name="months">
                            <?php foreach (ACTIVATION_MONTHS as $m): ?><option value="<?= $m ?>"<?= $m === 1 ? ' selected' : '' ?>><?= e($m === 1 ? t('month_1') : t('months_n', ['n' => $m])) ?></option><?php endforeach; ?>
                            <option value="0"><?= e(t('no_end_date')) ?></option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="no-amount"><?= e(t('amount_paid_optional')) ?></label>
                        <div class="code-form">
                            <input id="no-amount" class="amount-input" name="amount" type="number" inputmode="numeric" min="0" step="1" dir="ltr">
                            <label class="visually-hidden" for="no-cur"><?= e(t('currency')) ?></label>
                            <select id="no-cur" name="currency"><option value="IQD">IQD</option><option value="USD">USD</option></select>
                        </div>
                    </div>
                    <div class="field">
                        <label class="check"><input type="checkbox" name="visited" value="1" checked> <?= e(t('i_visited')) ?></label>
                        <p class="hint"><?= e(t('i_visited_hint')) ?></p>
                    </div>
                </div>
                <div><button class="btn btn-primary" type="submit"><svg aria-hidden="true"><use href="#i-check"></use></svg><?= e(t('create_office_now')) ?></button></div>
            </form>
        </details>

        <section class="panel payments-panel" aria-labelledby="pay-h">
            <h2 id="pay-h" class="panel-title"><?= e(t('payments')) ?></h2>
            <dl class="stat-tiles">
                <?php foreach (['IQD', 'USD'] as $c): ?>
                    <div><dt><?= e(t('this_month')) ?> · <?= $c ?></dt><dd dir="ltr"><?= number_format((int) ($payMonth[$c] ?? 0)) ?></dd></div>
                <?php endforeach; ?>
                <div><dt><?= e(t('payments_count_month')) ?></dt><dd><?= (int) ($payMonth['n'] ?? 0) ?></dd></div>
            </dl>
            <?php if ($payments): ?>
                <details>
                    <summary><?= e(t('recent_payments')) ?></summary>
                    <div class="table-wrap" role="region" aria-label="<?= e(t('recent_payments')) ?>" tabindex="0">
                        <table class="table">
                            <thead><tr><th scope="col"><?= e(t('date')) ?></th><th scope="col"><?= e(t('office_name')) ?></th><th scope="col"><?= e(t('amount_paid')) ?></th><th scope="col"><?= e(t('plan_length')) ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td data-label="<?= e(t('date')) ?>" dir="ltr"><?= e(substr($pay['created_at'], 0, 10)) ?></td>
                                    <td data-label="<?= e(t('office_name')) ?>"><?= e($pay['office_name']) ?></td>
                                    <td data-label="<?= e(t('amount_paid')) ?>" dir="ltr"><?= number_format((int) $pay['amount']) ?> <?= e($pay['currency']) ?></td>
                                    <td data-label="<?= e(t('plan_length')) ?>"><?= e(($pay['kind'] ?? 'plan') === 'featured' ? '★ ' . t('featured') : ($pay['months'] === null ? t('no_end_date') : ((int) $pay['months'] === 1 ? t('month_1') : t('months_n', ['n' => (int) $pay['months']])))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </section>

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
                                <label class="visually-hidden" for="amt-<?= (int) $o['id'] ?>"><?= e(t('amount_paid')) ?>: <?= e($o['name']) ?></label>
                                <input id="amt-<?= (int) $o['id'] ?>" class="amount-input" name="amount" type="number" inputmode="numeric" min="0" step="1" placeholder="<?= e(t('amount_paid_optional')) ?>" dir="ltr">
                                <label class="visually-hidden" for="cur-<?= (int) $o['id'] ?>"><?= e(t('currency')) ?>: <?= e($o['name']) ?></label>
                                <select id="cur-<?= (int) $o['id'] ?>" name="currency"><option value="IQD">IQD</option><option value="USD">USD</option></select>
                                <button class="btn btn-primary btn-sm" type="submit"><svg aria-hidden="true"><use href="#i-check"></use></svg><?= e(in_array($st, ['pending', 'expired'], true) ? t('activate') : t('extend')) ?><span class="visually-hidden">: <?= e($o['name']) ?></span></button>
                            </form>
                            <?php if (in_array($st, ['soon', 'expired'], true) && ($ph = office_contact_phone($o))): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(whatsapp_url($ph, t($st === 'soon' ? 'renew_reminder' : 'renew_ended', ['app' => app_name(), 'office' => $o['name'], 'date' => substr((string) $o['active_until'], 0, 10)]))) ?>" target="_blank" rel="noopener noreferrer"><svg aria-hidden="true"><use href="#i-whatsapp"></use></svg><?= e(t('remind_whatsapp')) ?></a>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('admin/offices/' . $o['id'] . '/password')) ?>" data-confirm="<?= e(t('new_password_confirm')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-ghost btn-sm" type="submit"><svg aria-hidden="true"><use href="#i-key"></use></svg><?= e(t('new_password')) ?><span class="visually-hidden">: <?= e($o['name']) ?></span></button>
                            </form>
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
