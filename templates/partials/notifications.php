<section aria-labelledby="notif-h" class="notifications-page">
    <div class="portal-header-card">
        <div>
            <span class="portal-eyebrow">Activity & Alerts</span>
            <h1 id="notif-h">Notifications</h1>
            <p class="muted">Recent emergency dispatches, request updates, and system alerts.</p>
        </div>
        <div class="history-count-chip">
            <span class="count-num"><?= count($items) ?></span>
            <span class="count-label">Total Alert<?= count($items) === 1 ? '' : 's' ?></span>
        </div>
    </div>

    <div class="panel notifications-panel mt-4">
        <?php if (!$items): ?>
            <div class="empty empty-state-box">
                <div class="empty-state-icon"><?= icon('bell', 28) ?></div>
                <h3>No notifications yet</h3>
                <p>You have no recent notifications or alerts. New updates will appear here automatically.</p>
            </div>
        <?php else: ?>
            <ul class="notice-feed">
                <?php foreach ($items as $n): ?>
                    <li class="notice-item <?= $n['is_read'] ? 'read' : 'unread' ?>">
                        <div class="notice-icon-box" aria-hidden="true">
                            <?= icon('bell', 16) ?>
                        </div>
                        <div class="notice-main">
                            <p class="notice-text"><?= e($n['message']) ?></p>
                            <time class="notice-time" datetime="<?= e(iso_dt($n['created_at'])) ?>">
                                <?= icon('clock', 12) ?>
                                <span><?= e(format_dt($n['created_at'])) ?></span>
                            </time>
                        </div>
                        <?php if (!$n['is_read']): ?>
                            <span class="notice-unread-dot" title="Unread alert" aria-label="Unread"></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
