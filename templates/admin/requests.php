<?php $route = 'admin/requests'; ?>
<section aria-labelledby="ar-h" class="admin-page-view">
    <div class="portal-header-card">
        <div>
            <span class="portal-eyebrow">Platform-Wide Registry</span>
            <h1 id="ar-h">All Blood Requests</h1>
            <p class="muted">Live monitoring of all hospital postings, fulfillment progression, urgency distribution, and lifecycle statuses.</p>
        </div>
        <div class="portal-header-actions">
            <a class="btn btn-small btn-outline btn-export" href="<?= e(url('admin/export', ['type' => 'requests'])) ?>">
                <span>Export Requests CSV</span>
            </a>
        </div>
    </div>

    <form method="get" action="<?= e(app_root_url() . '/public/index.php') ?>" class="filter-bar filter-bar-styled mt-4">
        <input type="hidden" name="r" value="admin/requests">
        <div class="filter-inputs">
            <div class="search-input-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <label class="sr-only" for="q">Search requests</label>
                <input id="q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search hospital name or ward notes…">
            </div>
            <select id="st" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['open', 'fulfilled', 'closed', 'cancelled', 'expired'] as $s): ?>
                    <option value="<?= $s ?>"<?= selected($filters['status'], $s) ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="ug" name="urgency">
                <option value="">All Urgencies</option>
                <?php foreach (['Critical', 'Urgent', 'Normal'] as $s): ?>
                    <option<?= selected($filters['urgency'], $s) ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <select id="bg" name="blood_group">
                <option value="">Any Blood Group</option>
                <?php foreach (blood_groups() as $g): ?>
                    <option<?= selected($filters['blood_group'], $g) ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-small btn-primary" type="submit">Filter Requests</button>
        </div>
    </form>

    <?php if (!$list['rows']): ?>
        <div class="empty empty-state-box mt-3">
            <div class="empty-state-icon"></div>
            <h3>No requests match your filter criteria</h3>
            <p>Try resetting filters to show all historic and active blood requests.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap mt-3">
        <table class="table-modern">
            <caption class="sr-only">All blood requests</caption>
            <thead><tr>
                <th scope="col">ID</th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'hospital') ?>"><?= sort_link('Hospital & City', 'hospital', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'group') ?>"><?= sort_link('Group', 'group', $list, $filters, $route) ?></th>
                <th scope="col">Units Allocation</th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'urgency') ?>"><?= sort_link('Urgency', 'urgency', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'deadline') ?>"><?= sort_link('Deadline', 'deadline', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'status') ?>"><?= sort_link('Status', 'status', $list, $filters, $route) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $r): ?>
                <tr>
                    <td><span class="id-tag">#<?= (int) $r['id'] ?></span></td>
                    <td>
                        <strong><?= e($r['hospital_name']) ?></strong>
                        <div><span class="city-tag"><?= e($r['city_name']) ?></span></div>
                    </td>
                    <td><span class="blood-badge-inline"><?= e($r['blood_group']) ?></span></td>
                    <td>
                        <div class="table-progress-wrap">
                            <span class="progress-ratio"><strong><?= (int) $r['units_fulfilled'] ?></strong> / <?= (int) $r['units_needed'] ?></span>
                            <progress value="<?= (int) $r['units_fulfilled'] ?>" max="<?= (int) $r['units_needed'] ?>"></progress>
                        </div>
                    </td>
                    <td><span class="badge <?= e(urgency_class($r['urgency'])) ?>"><?= e($r['urgency']) ?></span></td>
                    <td><span class="time-stamp"><?= e(format_dt($r['required_by'])) ?></span></td>
                    <td><span class="status-tag <?= e(status_class($r['status'])) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require BASE_PATH . '/templates/partials/pagination.php'; endif; ?>
</section>
