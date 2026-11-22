<section aria-labelledby="hd-h" class="hospital-portal-view">
    <div class="portal-header-card">
        <div class="portal-header-left">
            <div>
                <span class="portal-eyebrow">Hospital Operations Console</span>
                <h1 id="hd-h"><?= e($hospital->hospital_name) ?></h1>
                <div class="hospital-sub-info">
                    <span class="verification-badge status-<?= e($hospital->verification_status) ?>">
                        <?= $hospital->verification_status === 'verified' ? 'Verified Institution' : 'Pending Review' ?>
                    </span>
                    <span class="trust-badge">Trust Score: <strong><?= (int) $hospital->trust_score ?>/100</strong></span>
                </div>
            </div>
        </div>
        <div class="portal-header-actions">
            <a class="btn btn-primary-glow" href="<?= e(url('hospital/request/new')) ?>">
                <span>New Blood Request</span>
            </a>
            <a class="btn btn-outline" href="<?= e(url('hospital/inventory')) ?>">
                <span>Manage Inventory</span>
            </a>
        </div>
    </div>

    <?php if ($hospital->verification_status !== 'verified'): ?>
        <div class="alert alert-info alert-dismissible" role="status">
            <div class="alert-icon-wrap"><?= icon('info', 18) ?></div>
            <div class="alert-content">
                Verification status: <strong><?= e(ucfirst($hospital->verification_status)) ?></strong>.
                <?= $hospital->verification_status === 'pending' ? 'You can raise emergency requests once the administrator completes your licence audit.' : 'Please contact the administrator.' ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="stats-grid hospital-stats mt-4">
        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-num"><?= (int) $activeCount ?></span>
                <span class="stat-label">Active Open Requests</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-num"><?= (int) $hospital->trust_score ?></span>
                <span class="stat-label">Institutional Trust Rating</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-num"><?= array_sum($inventory) ?></span>
                <span class="stat-label">Total Blood Bank Units</span>
            </div>
        </div>
    </div>

    <section class="panel panel-search-donors mt-4" aria-labelledby="find-h">
        <div class="panel-header-row">
            <div>
                <h2 id="find-h" class="panel-title">
                    <svg class="panel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <span>Instant Regional Donor Directory</span>
                </h2>
                <p class="muted small">Real-time database of eligible, rested donors. Contact numbers are protected until a donor accepts your emergency request.</p>
            </div>
        </div>

        <div class="search-row-elevated">
            <div class="search-input-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <label class="sr-only" for="donor-q">Search by donor name</label>
                <input id="donor-q" type="search" placeholder="Type donor name to filter…" autocomplete="off">
            </div>
            <div class="group-select-wrap">
                <label class="sr-only" for="donor-group">Filter blood group</label>
                <select id="donor-group">
                    <option value="">All Blood Groups</option>
                    <?php foreach (blood_groups() as $g): ?><option><?= e($g) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div id="donor-results" class="results results-cards" aria-live="polite" data-endpoint="donor_search.php"></div>
    </section>

    <div class="section-head mt-5">
        <div>
            <h2>Broadcasted Blood Requests</h2>
            <p class="muted">Track response status, match rankings, and confirm donations.</p>
        </div>
    </div>

    <form method="get" action="<?= e(app_root_url() . '/public/index.php') ?>" class="filter-bar filter-bar-styled">
        <input type="hidden" name="r" value="hospital/dashboard">
        <div class="filter-inputs">
            <select id="f-status" name="status">
                <option value="">All Statuses</option>
                <?php foreach (['open', 'fulfilled', 'closed', 'cancelled', 'expired'] as $s): ?>
                    <option value="<?= $s ?>"<?= selected($filters['status'], $s) ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="f-urg" name="urgency">
                <option value="">All Urgencies</option>
                <?php foreach (['Critical', 'Urgent', 'Normal'] as $s): ?>
                    <option<?= selected($filters['urgency'], $s) ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-small btn-primary" type="submit">Apply Filter</button>
        </div>
    </form>

    <?php $route = 'hospital/dashboard'; ?>
    <?php if (!$list['rows']): ?>
        <div class="empty empty-state-box">
            <div class="empty-state-icon"><?= icon('clipboard', 32) ?></div>
            <h3>No requests match your filter</h3>
            <p>Ready to post an emergency? Click <strong>New Blood Request</strong> above.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-modern">
            <caption class="sr-only">Your blood requests</caption>
            <thead><tr>
                <th scope="col">ID</th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'group') ?>"><?= sort_link('Group', 'group', $list, $filters, $route) ?></th>
                <th scope="col">Units Fulfilled</th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'urgency') ?>"><?= sort_link('Urgency', 'urgency', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'deadline') ?>"><?= sort_link('Required By', 'deadline', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'status') ?>"><?= sort_link('Status', 'status', $list, $filters, $route) ?></th>
                <th scope="col" class="center">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $r): ?>
                <tr>
                    <td><span class="id-tag">#<?= (int) $r['id'] ?></span></td>
                    <td>
                        <span class="blood-badge-inline"><?= e($r['blood_group']) ?></span>
                    </td>
                    <td>
                        <div class="table-progress-wrap">
                            <span class="progress-ratio"><strong><?= (int) $r['units_fulfilled'] ?></strong> / <?= (int) $r['units_needed'] ?></span>
                            <progress value="<?= (int) $r['units_fulfilled'] ?>" max="<?= (int) $r['units_needed'] ?>"></progress>
                        </div>
                    </td>
                    <td><span class="badge <?= e(urgency_class($r['urgency'])) ?>"><?= e($r['urgency']) ?></span></td>
                    <td><span class="time-stamp"><?= e(format_dt($r['required_by'])) ?></span></td>
                    <td><span class="status-tag <?= e(status_class($r['status'])) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                    <td class="center">
                        <a class="btn btn-small btn-manage" href="<?= e(url('hospital/request', ['id' => $r['id']])) ?>">
                            <span>Manage Request</span>
                            <span class="btn-arrow">&rarr;</span>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require BASE_PATH . '/templates/partials/pagination.php'; endif; ?>
</section>
