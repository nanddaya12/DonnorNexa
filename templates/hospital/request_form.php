<section class="form-page wide request-new-page" aria-labelledby="rq-h">
    <div class="breadcrumb-nav">
        <a href="<?= e(url('hospital/dashboard')) ?>" class="btn-back back-link" title="Return to Hospital Dashboard">
            <?= icon('arrow-left', 16, 'btn-arrow-left') ?>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <div class="portal-header-card mt-3">
        <div>
            <span class="portal-eyebrow">Hospital Operations Console</span>
            <h1 id="rq-h">Broadcast Emergency Blood Request</h1>
            <p class="muted">Post an urgent clinical need to notify eligible, compatible donors in your geographic radius.</p>
        </div>
        <div class="history-count-chip">
            <span class="count-num"><?= (int) $hospital->trust_score ?>/100</span>
            <span class="count-label">Trust Score</span>
        </div>
    </div>

    <?php if ($hospital->verification_status !== 'verified'): ?>
        <div class="alert alert-error mt-3" role="alert">
            <div class="alert-icon-wrap"><?= icon('alert-triangle', 18) ?></div>
            <div class="alert-content">Your hospital account is awaiting admin verification. Requests cannot be broadcasted until verified.</div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('hospital/request/create')) ?>" class="form-card request-form-card mt-4" data-validate novalidate>
        <?= csrf_field() ?>

        <div class="form-section-title">
            <span class="sec-step">1</span>
            <span>Blood Type & Volume</span>
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="blood_group">Recipient Blood Group Needed <span class="req">*</span></label>
                <select id="blood_group" name="blood_group" required aria-describedby="err-blood_group">
                    <option value="">Select blood group…</option>
                    <?php foreach (blood_groups() as $g): ?>
                        <option value="<?= e($g) ?>"<?= selected(old('blood_group'), $g) ?>><?= e($g) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'blood_group') ?>
            </div>
            <div class="field">
                <label for="units_needed">Whole Blood Units Needed <span class="req">*</span></label>
                <input id="units_needed" name="units_needed" type="number" min="1" max="10" required value="<?= old('units_needed', '1') ?>" aria-describedby="err-units_needed">
                <small class="hint">1 to 10 units per emergency posting.</small>
                <?= field_error($errors, 'units_needed') ?>
            </div>
        </div>

        <div class="form-section-title mt-4">
            <span class="sec-step">2</span>
            <span>Urgency Level & Timeframe</span>
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="urgency">Urgency Priority <span class="req">*</span></label>
                <select id="urgency" name="urgency" required aria-describedby="hint-urgency">
                    <?php foreach (['Normal', 'Urgent', 'Critical'] as $u): ?>
                        <option value="<?= e($u) ?>"<?= selected(old('urgency', 'Normal'), $u) ?>><?= e($u) ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="hint-urgency" class="hint">
                    <strong>Critical:</strong> Widens pool nationwide (Max 3 open per hospital).<br>
                    <strong>Urgent:</strong> Provincial radius.<br>
                    <strong>Normal:</strong> City radius.
                </small>
                <?= field_error($errors, 'urgency') ?>
            </div>
            <div class="field">
                <label for="required_by">Deadline / Required By <span class="req">*</span></label>
                <input id="required_by" name="required_by" type="datetime-local" required value="<?= old('required_by') ?>" aria-describedby="err-required_by">
                <div class="quick-time-buttons">
                    <button type="button" class="btn-time-helper" data-hours="4">+4 Hours</button>
                    <button type="button" class="btn-time-helper" data-hours="12">+12 Hours</button>
                    <button type="button" class="btn-time-helper" data-hours="24">+24 Hours</button>
                </div>
                <?= field_error($errors, 'required_by') ?>
            </div>
            <div class="field span-2">
                <label for="ward_notes">Department / Ward Notes <span class="muted">(Strictly NO patient names)</span></label>
                <textarea id="ward_notes" name="ward_notes" rows="3" maxlength="255" placeholder="e.g. ICU Ward 3, 2nd floor, report to Blood Bank reception desk" aria-describedby="err-ward_notes"><?= old('ward_notes') ?></textarea>
                <small class="hint">Patient privacy law strictly prohibits posting personal names or private identifiers.</small>
                <?= field_error($errors, 'ward_notes') ?>
            </div>
        </div>

        <div class="auth-submit-row mt-4">
            <button class="btn btn-primary-glow btn-lg btn-block" type="submit">
                <span>Broadcast Request & Notify Scored Donors</span>
                <?= icon('arrow-right', 16) ?>
            </button>
            <p class="muted center mt-2"><a href="<?= e(url('hospital/dashboard')) ?>">&larr; Return to Dashboard</a></p>
        </div>
    </form>
</section>
