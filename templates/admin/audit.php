<?php $route = 'admin/audit'; ?>
<section aria-labelledby="al-h" class="admin-page-view">
    <div class="portal-header-card">
        <div>
            <span class="portal-eyebrow">Tamper-Evident Trail</span>
            <h1 id="al-h">Security & Regulatory Audit Log</h1>
            <p class="muted">Immutable logging of logins, permission changes, contact reveals, status mutations, and admin overrides.</p>
        </div>
        <div class="portal-header-actions">
            <a class="btn btn-small btn-outline btn-export" href="<?= e(url('admin/export', ['type' => 'audit'])) ?>">
                <span>Export Audit CSV</span>
            </a>
        </div>
    </div>

    <form method="get" action="<?= e(app_root_url() . '/public/index.php') ?>" class="filter-bar filter-bar-styled mt-4">
        <input type="hidden" name="r" value="admin/audit">
        <div class="filter-inputs">
            <div class="search-input-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <label class="sr-only" for="q">Search audit logs</label>
                <input id="q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search entity, value or actor…">
            </div>
            <select id="ac" name="action">
                <option value="">All Security Events</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>"<?= selected($filters['action'], $a) ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-small btn-primary" type="submit">Filter Audit Log</button>
        </div>
    </form>

    <?php if (!$list['rows']): ?>
        <div class="empty empty-state-box mt-3">
            <div class="empty-state-icon"></div>
            <h3>No audit records match your query</h3>
            <p>Try broadening your search term or resetting the event filter.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap mt-3">
        <table class="table-modern small-table">
            <caption class="sr-only">System audit trail</caption>
            <thead><tr>
                <th scope="col" aria-sort="<?= aria_sort($list, 'time') ?>"><?= sort_link('Timestamp', 'time', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'user') ?>"><?= sort_link('Actor', 'user', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'action') ?>"><?= sort_link('Event Action', 'action', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'entity') ?>"><?= sort_link('Target Entity', 'entity', $list, $filters, $route) ?></th>
                <th scope="col">Previous State</th>
                <th scope="col">New State</th>
                <th scope="col">IP Address</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $a): ?>
                <tr>
                    <td><span class="time-stamp"><?= e(format_dt($a['created_at'], 'd M, H:i:s')) ?></span></td>
                    <td><strong><?= e($a['user_name'] ?? 'system (cron)') ?></strong></td>
                    <td><code class="code-action"><?= e($a['action']) ?></code></td>
                    <td><span class="entity-badge"><?= e($a['entity']) ?></span></td>
                    <td><span class="state-val old-state"><?= e($a['old_value'] ?? '—') ?></span></td>
                    <td><span class="state-val new-state"><?= e($a['new_value'] ?? '—') ?></span></td>
                    <td><span class="ip-mono"><?= e($a['ip_address']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require BASE_PATH . '/templates/partials/pagination.php'; endif; ?>
</section>
