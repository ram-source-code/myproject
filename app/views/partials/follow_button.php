<?php /** @var array $o office row with id, name, followers */ ?>
<div class="follow-wrap" data-follow-office="<?= (int) $o['id'] ?>">
    <button type="button" class="btn btn-primary follow-btn<?= !empty($block) ? ' btn-block' : '' ?>" aria-pressed="false" data-follow-btn>
        <svg aria-hidden="true"><use href="#i-bell"></use></svg>
        <span data-follow-label><?= e(t('follow')) ?></span>
    </button>
    <p class="follow-hint muted small"><span data-follow-count><?= (int) ($o['followers'] ?? 0) ?></span> <?= e(t('followers')) ?> · <?= e(t('follow_hint')) ?></p>
</div>
