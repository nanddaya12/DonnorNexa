<?php $route = 'admin/hospitals'; ?>
<section aria-labelledby="ah-h" class="admin-page-view">
    <div class="portal-header-card">
        <div>
            <span class="portal-eyebrow">Medical Accreditation Queue</span>
            <h1 id="ah-h">Hospital Verification & Licensing</h1>
            <p class="muted">Examine official medical licences, issue approvals, or suspend non-compliant healthcare institutions.</p>
        </div>
        <div class="header-action-pill">
            <span>Filter by: <strong><?= e($filters['status'] ? ucfirst($filters['status']) : 'All Statuses') ?></strong></span>
        </div>
    </div>

    <form method="get" action="<?= e(app_root_url() . '/public/index.php') ?>" class="filter-bar filter-bar-styled mt-4">
        <input type="hidden" name="r" value="admin/hospitals">
        <div class="filter-inputs">
            <div class="search-input-wrap">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <label class="sr-only" for="q">Search hospitals</label>
                <input id="q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search by name, licence # or email…">
            </div>
            <select id="st" name="status">
                <option value="">All Accreditation Statuses</option>
                <?php foreach (['pending', 'verified', 'rejected'] as $s): ?>
                    <option value="<?= $s ?>"<?= selected($filters['status'], $s) ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-small btn-primary" type="submit">Filter List</button>
        </div>
    </form>

    <?php if (!$list['rows']): ?>
        <div class="empty empty-state-box mt-3">
            <div class="empty-state-icon"></div>
            <h3>No hospitals match current criteria</h3>
            <p>Try clearing your search query or changing the accreditation status filter.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap mt-3">
        <table class="table-modern">
            <caption class="sr-only">Registered hospitals</caption>
            <thead><tr>
                <th scope="col" aria-sort="<?= aria_sort($list, 'name') ?>"><?= sort_link('Hospital Institution', 'name', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'city') ?>"><?= sort_link('City', 'city', $list, $filters, $route) ?></th>
                <th scope="col">Medical Licence</th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'trust') ?>"><?= sort_link('Trust Rating', 'trust', $list, $filters, $route) ?></th>
                <th scope="col" aria-sort="<?= aria_sort($list, 'status') ?>"><?= sort_link('Status', 'status', $list, $filters, $route) ?></th>
                <th scope="col" class="center">Accreditation Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list['rows'] as $h): ?>
                <tr>
                    <td>
                        <strong class="hospital-name-text"><?= e($h['hospital_name']) ?></strong>
                        <div class="muted small"><?= e($h['email']) ?></div>
                    </td>
                    <td><span class="city-tag"><?= e($h['city_name']) ?></span></td>
                    <td>
                        <span class="licence-no">#<?= e($h['licence_no']) ?></span>
                        <div class="mt-1">
                            <a class="btn-pdf-link" href="<?= e(url('admin/hospital/licence', ['id' => $h['user_id']])) ?>" target="_blank" rel="noopener">
                                <svg class="pdf-icon-inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                <span>Inspect Licence PDF</span>
                            </a>
                        </div>
                    </td>
                    <td>
                        <span class="trust-meter-val"><strong><?= (int) $h['trust_score'] ?></strong> / 100</span>
                    </td>
                    <td><span class="status-tag status-<?= e($h['verification_status']) ?>"><?= e(ucfirst($h['verification_status'])) ?></span></td>
                    <td class="center">
                        <form method="post" action="<?= e(url('admin/hospital/verify')) ?>" class="button-row tight center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $h['user_id'] ?>">
                            <button class="btn btn-small btn-primary" name="decision" value="verified" type="submit"<?= $h['verification_status'] === 'verified' ? ' disabled' : '' ?>>
                                Approve
                            </button>
                            <button class="btn btn-small btn-danger" name="decision" value="rejected" type="submit"<?= $h['verification_status'] === 'rejected' ? ' disabled' : '' ?>>
                                Reject
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require BASE_PATH . '/templates/partials/pagination.php'; endif; ?>
</section>
