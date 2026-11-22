<section aria-labelledby="dd-h" class="donor-portal-view">
    <div class="donor-hero-banner">
        <div class="donor-hero-content">
            <div class="donor-badge-row">
                <span class="blood-badge" aria-label="Blood group <?= e($donor->blood_group) ?>">
                    <?= e($donor->blood_group) ?>
                </span>
                <div class="donor-welcome-text">
                    <span class="portal-eyebrow">Life Saver Portal</span>
                    <h1 id="dd-h">Welcome back, <?= e(strtok($donor->name, ' ')) ?></h1>
                    <p class="muted">Ready to respond to urgent blood requests in your region.</p>
                </div>
            </div>
        </div>
        <div class="donor-impact-stats">
            <div class="impact-stat-item">
                <span class="impact-stat-num"><?= (int) $count ?></span>
                <span class="impact-stat-label">Donations Completed</span>
            </div>
            <div class="impact-stat-item">
                <span class="impact-stat-num"><?= (int) $count * 3 ?></span>
                <span class="impact-stat-label">Est. Lives Impacted</span>
            </div>
        </div>
    </div>

    <div class="grid-2 mt-4">
        <article class="panel panel-eligibility" aria-labelledby="elig-h">
            <div class="panel-header-row">
                <h2 id="elig-h" class="panel-title">
                    <span>Medical Eligibility</span>
                </h2>
                <?php if ($eligibility['eligible']): ?>
                    <span class="status-pill status-ok">
                        <span class="dot-online"></span> Eligible to Donate Now
                    </span>
                <?php else: ?>
                    <span class="status-pill status-wait">
                        <span class="dot-wait"></span> Cooldown / Ineligible
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!$eligibility['eligible']): ?>
                <div class="eligibility-reasons-box">
                    <span class="reasons-heading">Requirements to resolve:</span>
                    <ul class="plain">
                        <?php foreach ($eligibility['reasons'] as $r): ?>
                            <li><?= e($r) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <dl class="facts facts-styled">
                <div class="fact-row">
                    <dt>Last Recorded Donation</dt>
                    <dd><strong><?= e($donor->last_donation_date ? format_dt($donor->last_donation_date, 'd M Y') : 'No donations recorded yet') ?></strong></dd>
                </div>
                <div class="fact-row">
                    <dt>Eligible Date</dt>
                    <dd>
                        <strong><?= $eligibility['next_date'] ? e($eligibility['next_date']->format('d M Y')) : 'Immediately Available' ?></strong>
                        <span class="muted small">(<?= (int) $cooldown ?>-day standard cooldown)</span>
                    </dd>
                </div>
            </dl>

            <div class="availability-control-card">
                <div class="avail-text">
                    <strong>Emergency Dispatch Status</strong>
                    <p class="muted small">Toggle to pause or resume receiving high-priority hospital notifications.</p>
                </div>
                <form method="post" action="<?= e(url('donor/availability')) ?>" class="toggle-form">
                    <?= csrf_field() ?>
                    <label class="switch">
                        <input type="checkbox" name="available" value="1" data-toggle-availability <?= (int) $donor->is_available ? 'checked' : '' ?>>
                        <span class="slider" aria-hidden="true"></span>
                        <span class="switch-label">Available</span>
                    </label>
                    <button class="btn btn-small no-js-only" type="submit">Save</button>
                    <span class="avail-feedback-pill" id="avail-status" aria-live="polite"></span>
                </form>
            </div>
        </article>

        <article class="panel panel-alerts" aria-labelledby="note-h">
            <div class="panel-header-row">
                <h2 id="note-h" class="panel-title">
                    <span>Recent Notifications</span>
                </h2>
                <?php if ($notes): ?>
                    <a href="<?= e(url('notifications')) ?>" class="small-link">View All</a>
                <?php endif; ?>
            </div>
            <?php if (!$notes): ?>
                <div class="empty-compact">
                    <p>No new alerts. We will ping you when a nearby emergency matches your blood group.</p>
                </div>
            <?php else: ?>
                <ul class="notice-list compact">
                    <?php foreach ($notes as $n): ?>
                        <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
                            <div class="notice-bubble">
                                <span class="notice-dot"></span>
                                <span class="notice-body"><?= e($n['message']) ?></span>
                            </div>
                            <time class="muted small" datetime="<?= e(iso_dt($n['created_at'])) ?>"><?= e(format_dt($n['created_at'])) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </div>

    <div class="section-head mt-5">
        <div>
            <h2>Emergency Requests Matched to You</h2>
            <p class="muted">Hospitals in need of your blood type. Your phone number is only shared if you press <strong>Accept</strong>.</p>
        </div>
    </div>

    <?php if (!$matches): ?>
        <div class="empty empty-state-box">
            <h3>No pending emergency requests</h3>
            <p>You have no pending emergency matches right now. When a regional hospital posts a requirement matching your blood type, it will appear here immediately.</p>
        </div>
    <?php endif; ?>

    <div class="match-list">
    <?php foreach ($matches as $m):
        $actionable = $m['match_status'] === 'notified' && $m['request_status'] === 'open' && strtotime($m['required_by']) > time(); ?>
        <article class="match-card urgency-<?= e(strtolower($m['urgency'])) ?>">
            <div class="match-top">
                <div class="blood-badge-wrap">
                    <div class="blood-badge small"><?= e($m['blood_group']) ?></div>
                </div>
                <div class="match-center">
                    <div class="match-hospital-title">
                        <h3><?= e($m['hospital_name']) ?></h3>
                        <span class="city-tag"><?= e($m['city_name']) ?></span>
                    </div>
                    <div class="match-tags-row">
                        <span class="badge <?= e(urgency_class($m['urgency'])) ?>"><?= e($m['urgency']) ?></span>
                        <span class="status-tag <?= e(status_class($m['match_status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $m['match_status']))) ?></span>
                    </div>
                </div>
                <div class="match-timer-side">
                    <?php if ($m['request_status'] === 'open'): ?>
                        <div class="countdown-wrap">
                            <span class="timer-label">Time Remaining:</span>
                            <div class="countdown" data-countdown="<?= e(iso_dt($m['required_by'])) ?>" role="timer">Calculating...</div>
                        </div>
                    <?php else: ?>
                        <span class="status-tag <?= e(status_class($m['request_status'])) ?>">Request <?= e($m['request_status']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($m['ward_notes']): ?>
                <div class="match-notes-card">
                    <strong>Ward / Instructions:</strong> <?= e($m['ward_notes']) ?>
                </div>
            <?php endif; ?>

            <div class="units-progress-strip">
                <span class="units-label"><?= (int) $m['units_fulfilled'] ?> of <?= (int) $m['units_needed'] ?> units promised</span>
                <progress value="<?= (int) $m['units_fulfilled'] ?>" max="<?= (int) $m['units_needed'] ?>"></progress>
            </div>

            <?php if ($actionable): ?>
            <div class="match-actions">
                <form method="post" action="<?= e(url('donor/respond')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="match_id" value="<?= (int) $m['id'] ?>">
                    <input type="hidden" name="decision" value="accept">
                    <button class="btn btn-primary-glow btn-accept" type="submit">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        <span>Accept & Share Contact with Hospital</span>
                    </button>
                </form>
                <details class="decline-box">
                    <summary class="btn btn-outline btn-decline-toggle">Decline</summary>
                    <form method="post" action="<?= e(url('donor/respond')) ?>" class="decline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="match_id" value="<?= (int) $m['id'] ?>">
                        <input type="hidden" name="decision" value="decline">
                        <label for="reason-<?= (int) $m['id'] ?>" class="small">Optional decline reason:</label>
                        <input id="reason-<?= (int) $m['id'] ?>" name="reason" maxlength="255" placeholder="e.g. travelling out of city this week">
                        <button class="btn btn-small btn-outline" type="submit">Confirm Decline</button>
                    </form>
                </details>
            </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
</section>
