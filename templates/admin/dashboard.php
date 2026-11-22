<?php $scripts = ['js/vendor/chart.umd.js', 'js/admin.js']; ?>
<section aria-labelledby="ad-h" class="admin-dashboard-view">
    <div class="portal-header-card admin-header-card">
        <div class="portal-header-left">
            <div class="admin-avatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            </div>
            <div>
                <span class="portal-eyebrow">System Command Center</span>
                <h1 id="ad-h">Administrator Executive Console</h1>
                <p class="muted">Platform health, live algorithmic telemetry, audit compliance, and verification queues.</p>
            </div>
        </div>
        <div class="portal-header-actions">
            <div class="csv-export-dropdown">
                <a class="btn btn-small btn-outline btn-export" href="<?= e(url('admin/export', ['type' => 'requests'])) ?>">
                    <span>Export Requests CSV</span>
                </a>
                <a class="btn btn-small btn-outline btn-export" href="<?= e(url('admin/export', ['type' => 'donors'])) ?>">
                    <span>Export Donors CSV</span>
                </a>
                <a class="btn btn-small btn-outline btn-export" href="<?= e(url('admin/export', ['type' => 'audit'])) ?>">
                    <span>Export Audit CSV</span>
                </a>
            </div>
        </div>
    </div>

    <div class="stats-grid five admin-stats mt-4">
        <div class="stat-card">
            <span class="stat-num" data-sum="users"><?= (int) $summary['users'] ?></span>
            <span class="stat-label">Active Users</span>
        </div>
        <div class="stat-card">
            <span class="stat-num" data-sum="eligible_donors"><?= (int) $summary['eligible_donors'] ?></span>
            <span class="stat-label">Eligible Donors Now</span>
        </div>
        <div class="stat-card">
            <span class="stat-num" data-sum="open_requests"><?= (int) $summary['open_requests'] ?></span>
            <span class="stat-label">Active Open Requests</span>
        </div>
        <div class="stat-card">
            <span class="stat-num stat-num-green" data-sum="fulfilment_rate"><?= (int) $summary['fulfilment_rate'] ?>%</span>
            <span class="stat-label">Fulfilment Rate</span>
        </div>
        <div class="stat-card <?= (int)$summary['pending_hospitals'] > 0 ? 'stat-card-highlight' : '' ?>">
            <a href="<?= e(url('admin/hospitals', ['status' => 'pending'])) ?>" class="stat-link">
                <span class="stat-num <?= (int)$summary['pending_hospitals'] > 0 ? 'stat-num-amber' : '' ?>" data-sum="pending_hospitals"><?= (int) $summary['pending_hospitals'] ?></span>
                <span class="stat-label">Hospitals Awaiting Review &rarr;</span>
            </a>
        </div>
    </div>

    <div class="grid-2 mt-4">
        <figure class="panel chart-box">
            <div class="chart-header">
                <figcaption>Weekly Emergency Request Flow</figcaption>
                <span class="muted small">Trailing 6 Weeks</span>
            </div>
            <div class="canvas-wrap">
                <canvas id="chart-weekly" role="img" aria-label="Bar chart of requests per week"></canvas>
            </div>
        </figure>
        <figure class="panel chart-box">
            <div class="chart-header">
                <figcaption>Request Fulfilment Breakdown</figcaption>
                <span class="muted small">Status Distribution</span>
            </div>
            <div class="canvas-wrap">
                <canvas id="chart-status" role="img" aria-label="Doughnut chart of request statuses"></canvas>
            </div>
        </figure>
    </div>

    <figure class="panel chart-box wide-chart mt-4">
        <div class="chart-header">
            <div>
                <figcaption>Regional ABO/Rh Balance: Blood Group Demand vs Supply</figcaption>
                <p class="muted small">Comparing required units (demand) against eligible registered donors (supply) by group.</p>
            </div>
        </div>
        <div class="canvas-wrap wide-canvas">
            <canvas id="chart-groups" role="img" aria-label="Bar chart comparing demand and supply by blood group"></canvas>
        </div>
    </figure>

    <div class="chart-footer-strip">
        <span class="sync-dot"></span>
        <p id="chart-note" class="muted small" aria-live="polite">Telemetry updates every 60 seconds.</p>
    </div>

    <nav class="quick-admin-grid mt-4" aria-label="Admin quick sections">
        <a href="<?= e(url('admin/hospitals')) ?>" class="admin-quick-card">
            <span class="quick-card-icon"></span>
            <div class="quick-card-body">
                <strong>Manage Hospitals</strong>
                <span>Review licence documents, verify and suspend hospital accounts</span>
            </div>
            <span class="quick-card-arrow"><?= icon('arrow-right', 16) ?></span>
        </a>
        <a href="<?= e(url('admin/users')) ?>" class="admin-quick-card">
            <span class="quick-card-icon"></span>
            <div class="quick-card-body">
                <strong>User Administration</strong>
                <span>Search donors and hospitals, view statuses, and manage access</span>
            </div>
            <span class="quick-card-arrow"><?= icon('arrow-right', 16) ?></span>
        </a>
        <a href="<?= e(url('admin/cities')) ?>" class="admin-quick-card">
            <span class="quick-card-icon"></span>
            <div class="quick-card-body">
                <strong>Geographic Directory</strong>
                <span>Manage supported cities, postal coordinates and provinces</span>
            </div>
            <span class="quick-card-arrow"><?= icon('arrow-right', 16) ?></span>
        </a>
        <a href="<?= e(url('admin/settings')) ?>" class="admin-quick-card">
            <span class="quick-card-icon"></span>
            <div class="quick-card-body">
                <strong>System Policies</strong>
                <span>Configure biological cool-down days, scoring weights and limits</span>
            </div>
            <span class="quick-card-arrow"><?= icon('arrow-right', 16) ?></span>
        </a>
        <a href="<?= e(url('admin/audit')) ?>" class="admin-quick-card">
            <span class="quick-card-icon"></span>
            <div class="quick-card-body">
                <strong>Security Audit Log</strong>
                <span>Track logins, contact reveals, verification decisions and status changes</span>
            </div>
            <span class="quick-card-arrow"><?= icon('arrow-right', 16) ?></span>
        </a>
    </nav>
</section>
