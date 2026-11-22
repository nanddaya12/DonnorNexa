<?php $active = $request->isActive(); ?>
<section aria-labelledby="rd-h" class="request-detail-view">
    <div class="breadcrumb-nav">
        <a href="<?= e(url('hospital/dashboard')) ?>" class="btn-back back-link" title="Return to Hospital Dashboard">
            <?= icon('arrow-left', 16, 'btn-arrow-left') ?>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <div class="request-header-card">
        <div class="request-header-left">
            <div class="blood-badge-wrap">
                <div class="blood-badge"><?= e($request->blood_group) ?></div>
            </div>
            <div class="request-title-group">
                <span class="portal-eyebrow">Emergency Broadcast Record</span>
                <h1 id="rd-h">Request #<?= (int) $request->id ?> · Blood Group <?= e($request->blood_group) ?></h1>
                <div class="request-status-badges">
                    <span class="badge <?= e(urgency_class($request->urgency)) ?>"><?= e($request->urgency) ?></span>
                    <span class="status-tag <?= e(status_class($request->status)) ?>"><?= e(ucfirst($request->status)) ?></span>
                </div>
            </div>
        </div>

        <div class="request-header-right">
            <?php if ($request->status === 'open'): ?>
                <div class="countdown-metric">
                    <span class="countdown-title">Time to Deadline</span>
                    <div class="countdown" data-countdown="<?= e(iso_dt($request->required_by)) ?>" role="timer">Calculating...</div>
                </div>
            <?php else: ?>
                <span class="status-tag status-large <?= e(status_class($request->status)) ?>">Request <?= e(ucfirst($request->status)) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <article class="panel request-meta-panel">
        <div class="facts-grid">
            <div class="fact-box">
                <span class="fact-label">Units Allocation</span>
                <div class="fact-val">
                    <strong><?= (int) $request->units_fulfilled ?></strong> of <strong><?= (int) $request->units_needed ?></strong> units promised
                </div>
                <div class="meter-bar-wrap">
                    <progress value="<?= (int) $request->units_fulfilled ?>" max="<?= (int) $request->units_needed ?>"></progress>
                </div>
            </div>
            <div class="fact-box">
                <span class="fact-label">Required By Deadline</span>
                <div class="fact-val"><strong><?= e(format_dt($request->required_by)) ?></strong></div>
            </div>
            <?php if ($request->ward_notes): ?>
            <div class="fact-box fact-box-full">
                <span class="fact-label">Ward Instructions / Notes</span>
                <div class="fact-val"><?= e($request->ward_notes) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($active): ?>
        <div class="button-row button-row-actions">
            <?php if ($request->isOpen()): ?>
            <form method="post" action="<?= e(url('hospital/request/notify')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $request->id ?>">
                <button class="btn btn-outline" type="submit">
                    <span>Notify Next Batch of Donors</span>
                </button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('hospital/request/close')) ?>" data-confirm="Close this request?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $request->id ?>">
                <button class="btn btn-outline" type="submit">
                    <span>Close Request</span>
                </button>
            </form>
            <form method="post" action="<?= e(url('hospital/request/cancel')) ?>" data-confirm="Cancel this emergency request? Note: Frequent cancellations reduce hospital trust score.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $request->id ?>">
                <button class="btn btn-danger" type="submit">
                    <span>Cancel Request</span>
                </button>
            </form>
        </div>
        <?php endif; ?>
    </article>

    <div class="section-head">
        <div>
            <h2>Ranked Eligible Donors</h2>
            <p class="muted">Algorithmically scored based on ABO/Rh compatibility (+40), city proximity (+25), rest days (+20), and response rate (+15).</p>
        </div>
    </div>

    <?php if (!$matches): ?>
        <div class="empty empty-state-box">
            <div class="empty-state-icon"><?= icon('users', 32) ?></div>
            <h3>No eligible donors matched yet</h3>
            <p>Donors within geographic radius have been searched. Click "Notify Next Batch" above to widen the notification ring.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-modern">
            <caption class="sr-only">Donors matched to this request, highest score first</caption>
            <thead><tr>
                <th scope="col">Rank</th>
                <th scope="col">Donor</th>
                <th scope="col">Blood Group</th>
                <th scope="col">City</th>
                <th scope="col">Match Score</th>
                <th scope="col">Contact</th>
                <th scope="col">Match Status</th>
                <th scope="col" class="center">Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($matches as $i => $m): ?>
                <tr>
                    <td><span class="rank-badge rank-<?= $i < 3 ? ($i + 1) : 'other' ?>">#<?= $i + 1 ?></span></td>
                    <td>
                        <strong><?= e($m['name']) ?></strong>
                        <?php if ($m['decline_reason']): ?>
                            <div class="decline-reason-pill">Declined: “<?= e($m['decline_reason']) ?>”</div>
                        <?php endif; ?>
                    </td>
                    <td><span class="blood-badge-inline"><?= e($m['blood_group']) ?></span></td>
                    <td><?= e($m['city_name']) ?></td>
                    <td>
                        <div class="score-display">
                            <span class="score-value"><?= e(number_format((float) $m['score'], 1)) ?></span>
                            <div class="score-micro-bar">
                                <div class="score-fill" style="width: <?= min(100, max(0, (float)$m['score'])) ?>%;"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($m['reveal']): ?>
                            <a class="phone-reveal-btn" href="tel:<?= e($m['phone']) ?>" title="Call donor directly">
                                <strong><?= e($m['phone']) ?></strong>
                            </a>
                        <?php else: ?>
                            <span class="masked-phone" title="Confidential: Revealed once donor accepts">
                                <?= e(mask_phone($m['phone'])) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-tag <?= e(status_class($m['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $m['status']))) ?></span></td>
                    <td class="center">
                        <?php if ($m['status'] === 'accepted'): ?>
                        <form method="post" action="<?= e(url('hospital/request/donated')) ?>" data-confirm="Confirm this donor has successfully completed their blood donation?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $request->id ?>">
                            <input type="hidden" name="match_id" value="<?= (int) $m['id'] ?>">
                            <button class="btn btn-small btn-primary" type="submit">Mark Donated</button>
                        </form>
                        <?php else: ?>
                            <span class="muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
