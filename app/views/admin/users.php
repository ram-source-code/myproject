<?php $me = current_user(); ?>
<div class="container dash-layout">
    <?= render_partial('partials/dash_nav', ['active' => $active]) ?>
    <div class="dash-main">
        <div class="section-head">
            <h1 class="page-title"><?= e(t('admin_users')) ?></h1>
            <form method="get" action="<?= e(url('admin/users')) ?>" class="inline-search" role="search">
                <label for="uq" class="visually-hidden"><?= e(t('search')) ?></label>
                <input id="uq" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search_users')) ?>">
                <button class="btn btn-outline btn-sm" type="submit"><svg aria-hidden="true"><use href="#i-search"></use></svg><span class="visually-hidden"><?= e(t('search')) ?></span></button>
            </form>
        </div>
        <div class="table-wrap" role="region" aria-label="<?= e(t('admin_users')) ?>" tabindex="0">
            <table class="table">
                <thead><tr>
                    <th scope="col"><?= e(t('full_name')) ?></th>
                    <th scope="col"><?= e(t('email')) ?></th>
                    <th scope="col"><?= e(t('admin_listings')) ?></th>
                    <th scope="col"><?= e(t('joined')) ?></th>
                    <th scope="col"><?= e(t('role_status')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($items as $u): ?>
                    <tr>
                        <td data-label="<?= e(t('full_name')) ?>"><?= e($u['name']) ?></td>
                        <td data-label="<?= e(t('email')) ?>" dir="ltr"><?= e($u['email']) ?></td>
                        <td data-label="<?= e(t('admin_listings')) ?>"><?= (int) $u['listings'] ?></td>
                        <td data-label="<?= e(t('joined')) ?>"><?= e(fmt_date($u['created_at'])) ?></td>
                        <td data-label="<?= e(t('role_status')) ?>">
                            <?php if ((int) $u['id'] === (int) $me['id']): ?>
                                <span class="muted small"><?= e(t('role_' . $u['role'])) ?> (<?= e(t('you')) ?>)</span>
                            <?php else: ?>
                            <form method="post" action="<?= e(url('admin/users/' . $u['id'])) ?>" class="inline-select">
                                <?= csrf_field() ?>
                                <label class="visually-hidden" for="r-<?= (int) $u['id'] ?>"><?= e(t('role')) ?>: <?= e($u['name']) ?></label>
                                <select id="r-<?= (int) $u['id'] ?>" name="role">
                                    <?php foreach (['user', 'agent', 'admin'] as $r): ?>
                                        <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(t('role_' . $r)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="visually-hidden" for="s-<?= (int) $u['id'] ?>"><?= e(t('status')) ?>: <?= e($u['name']) ?></label>
                                <select id="s-<?= (int) $u['id'] ?>" name="status">
                                    <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>><?= e(t('user_active')) ?></option>
                                    <option value="banned" <?= $u['status'] === 'banned' ? 'selected' : '' ?>><?= e(t('user_banned')) ?></option>
                                </select>
                                <button class="btn btn-outline btn-sm" type="submit"><?= e(t('save')) ?><span class="visually-hidden">: <?= e($u['name']) ?></span></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
