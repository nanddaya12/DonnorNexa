<section aria-labelledby="hh-h" class="donor-history-view">
    <div class="portal-header-card">
        <div>
            <span class="portal-eyebrow">Life Saver Record</span>
            <h1 id="hh-h">Donation History & Badges</h1>
            <p class="muted">Lifetime verification record of blood donations fulfilled through DonorNexa.</p>
        </div>
        <div class="history-count-chip">
            <span class="count-num"><?= (int) $count ?></span>
            <span class="count-label">Verified Donation<?= $count === 1 ? '' : 's' ?></span>
        </div>
    </div>

    <div class="badges-section mt-4">
        <h2>Achievement Badges</h2>
        <p class="muted small">Honoring your ongoing dedication to saving emergency patients.</p>
        <ul class="badge-row mt-2">
            <?php foreach ($badges as $b): ?>
                <li class="award <?= $b['earned'] ? 'earned' : 'locked' ?>">
                    <div class="award-icon-wrap">
                        <span class="award-icon" aria-hidden="true"><?= $b['earned'] ? icon('trophy', 20) : icon('lock', 20) ?></span>
                    </div>
                    <strong class="award-title"><?= e($b['label']) ?></strong>
                    <span class="award-status-pill <?= $b['earned'] ? 'pill-earned' : 'pill-locked' ?>">
                        <?= $b['earned'] ? icon('check', 12) . ' Earned' : 'Locked' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="section-head mt-5">
        <div>
            <h2>Verified Hospital Transfusions</h2>
            <p class="muted">Confirmed by attending hospital blood banks upon donation completion.</p>
        </div>
    </div>

    <?php if (!$history): ?>
        <div class="empty empty-state-box mt-3">
            <div class="empty-state-icon"></div>
            <h3>No completed donations recorded yet</h3>
            <p>When you accept a matched request and donate at the hospital, they will mark it confirmed and your record will appear here!</p>
        </div>
    <?php else: ?>
        <div class="table-wrap mt-3">
            <table class="table-modern">
                <caption class="sr-only">Donations you have made</caption>
                <thead><tr>
                    <th scope="col">Date Confirmed</th>
                    <th scope="col">Hospital Healthcare Center</th>
                    <th scope="col">City</th>
                    <th scope="col">Blood Group</th>
                    <th scope="col">Units Donated</th>
                </tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><span class="time-stamp"><?= e(format_dt($h['donated_on'], 'd M Y')) ?></span></td>
                        <td><strong><?= e($h['hospital_name']) ?></strong></td>
                        <td><span class="city-tag"><?= e($h['city_name']) ?></span></td>
                        <td><span class="blood-badge-inline"><?= e($h['blood_group']) ?></span></td>
                        <td><span class="units-badge"><?= (int) $h['units'] ?> unit<?= (int)$h['units'] === 1 ? '' : 's' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
