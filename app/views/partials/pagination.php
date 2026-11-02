<?php
/** @var array $pg  @var string $base  @var array $query */
if ($pg['pages'] <= 1) {
    return;
}
$link = static fn(int $n) => url($base, array_merge($query, ['page' => $n > 1 ? $n : null]));
$start = max(1, $pg['page'] - 2);
$end = min($pg['pages'], $pg['page'] + 2);
?>
<nav class="pagination" aria-label="<?= e(t('pagination')) ?>">
    <?php if ($pg['page'] > 1): ?>
        <a href="<?= e($link($pg['page'] - 1)) ?>" rel="prev" class="page-link page-prev"><svg aria-hidden="true"><use href="#i-chevron"></use></svg><?= e(t('prev')) ?></a>
    <?php endif; ?>
    <?php if ($start > 1): ?><a class="page-link" href="<?= e($link(1)) ?>">1</a><?php if ($start > 2): ?><span class="page-gap">…</span><?php endif; ?><?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $pg['page']): ?>
            <span class="page-link is-current" aria-current="page"><?= $i ?></span>
        <?php else: ?>
            <a class="page-link" href="<?= e($link($i)) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($end < $pg['pages']): ?><?php if ($end < $pg['pages'] - 1): ?><span class="page-gap">…</span><?php endif; ?><a class="page-link" href="<?= e($link($pg['pages'])) ?>"><?= $pg['pages'] ?></a><?php endif; ?>
    <?php if ($pg['page'] < $pg['pages']): ?>
        <a href="<?= e($link($pg['page'] + 1)) ?>" rel="next" class="page-link page-next"><?= e(t('next')) ?><svg aria-hidden="true"><use href="#i-chevron"></use></svg></a>
    <?php endif; ?>
</nav>
