<?php
/** Expects $list (paginator result), $route and $filters. */
if ($list['pages'] > 1):
    $from = max(1, $list['page'] - 2);
    $to = min($list['pages'], $list['page'] + 2);
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($list['page'] > 1): ?>
        <a href="<?= e(list_url($route, $filters, ['page' => $list['page'] - 1])) ?>" rel="prev">Previous</a>
    <?php endif; ?>
    <?php for ($p = $from; $p <= $to; $p++): ?>
        <a href="<?= e(list_url($route, $filters, ['page' => $p])) ?>"<?= $p === $list['page'] ? ' aria-current="page" class="current"' : '' ?>><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($list['page'] < $list['pages']): ?>
        <a href="<?= e(list_url($route, $filters, ['page' => $list['page'] + 1])) ?>" rel="next">Next</a>
    <?php endif; ?>
    <span class="muted"><?= (int) $list['total'] ?> records</span>
</nav>
<?php endif; ?>
