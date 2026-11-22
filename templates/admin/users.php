<?php $route = 'admin/users'; ?>
<section aria-labelledby="au-h" class="admin-page-view">
    <div class="portal-header-card">
        <div>
            <span class="portal-eyebrow">User Management</span>
            <h1 id="au-h">Registered Platform Accounts</h1>
            <p class="muted">Search across donors, hospitals, and administrative staff. Manage access permissions and suspend abusive accounts.</p>
        </div>
    </div>

    <form method="get" action="<?= e(app_root_url() . '/public/index.php') ?>" class="filter-bar filter-bar-styled mt-4">
        <input type="hidden" name="r" value="admin/users">
        <div class="filter-inputs">
            <div class="search-input-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <label class="sr-only" for="q">Search users</label>
                <input id="q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search by name or email…">
            </div>
            <select id="role" name="role">
                <option value="">All Roles</option>
                <?php foreach (['donor', 'hospital', 'admin'] as $s): ?>
                    <option value="<?= $s ?>"<?= selected($filters['role'], $s) ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="st" name="status">
                <option value="">Any Status</option>
                <?php foreach (['active', 'blocked'] as $s): ?>
                    <option value="<?= $s ?>"<?= selected($filters['status'], $s) ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-small btn-primary" type="submit">Filter Users</button>
        </div>
    </form>

    <?php if (!$list['rows']): ?>
        <div class="empty empty-state-box mt-3">
            <div class="empty-state-icon"></div>
            <h3>No users found</h3>
            <p>Try clearing the filters or searching with a different keyword.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap mt-3">
        <table class="table-modern">
            <caption class="sr-only">All users</caption>
            <thead><tr>
                <th scope="col" aria-sort="<?= aria_sort($list, 'name') ?>"><?= sort_link('Full Name', 'name', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'email') ?>"><?= sort_link('Email Address', 'email', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'role') ?>"><?= sort_link('Role', 'role', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'status') ?>"><?= sort_link('Status', 'status', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'created') ?>"><?= sort_link('Joined Date', 'created', $list, $filters, $route) ?></th>
                <th scope="col" class="center">Account Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $u): ?>
                <tr>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td><span class="email-text"><?= e($u['email']) ?></span></td>
                    <td><span class="role-pill role-<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                    <td><span class="status-tag <?= $u['status'] === 'active' ? 'status-open' : 'status-cancelled' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                    <td><span class="time-stamp"><?= e(format_dt($u['created_at'], 'd M Y')) ?></span></td>
                    <td class="center">
                        <?php if ($u['role'] !== 'admin'): ?>
                        <form method="post" action="<?= e(url('admin/user/block')) ?>" data-confirm="<?= $u['status'] === 'active' ? 'Block this user from accessing DonorNexa?' : 'Unblock this user account?' ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-small <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-outline' ?>" type="submit">
                                <?= $u['status'] === 'active' ? 'Block Account' : 'Unblock Account' ?>
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="muted small">Protected Admin</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require BASE_PATH . '/templates/partials/pagination.php'; endif; ?>
</section>
