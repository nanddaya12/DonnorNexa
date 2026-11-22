<?php foreach ($messages as $m):
    $type = $m['type'] === 'error' ? 'error' : ($m['type'] === 'success' ? 'success' : 'info');
?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
        <div class="alert-icon-wrap">
            <?php if ($type === 'success'): ?>
                <?= icon('check', 18) ?>
            <?php elseif ($type === 'error'): ?>
                <?= icon('alert-triangle', 18) ?>
            <?php else: ?>
                <?= icon('info', 18) ?>
            <?php endif; ?>
        </div>
        <div class="alert-content"><?= e($m['message']) ?></div>
        <button type="button" class="alert-close" aria-label="Dismiss notification"><?= icon('x', 14) ?></button>
    </div>
<?php endforeach; ?>
