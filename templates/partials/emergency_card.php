<?php /** Public emergency card: blood group, city, urgency and countdown only. Expects $r. */ ?>
<article class="emergency-card urgency-<?= e(strtolower($r['urgency'])) ?>" data-urgency="<?= e(strtolower($r['urgency'])) ?>">
    <div class="blood-badge-wrap">
        <div class="blood-badge" aria-label="Blood group <?= e($r['blood_group']) ?>">
            <span class="blood-group-text"><?= e($r['blood_group']) ?></span>
        </div>
        <?php if (strtolower($r['urgency']) === 'critical'): ?>
            <span class="critical-indicator" title="Critical Emergency Active" aria-label="Critical"></span>
        <?php endif; ?>
    </div>
    <div class="emergency-info">
        <div class="emergency-header-row">
            <h3 class="emergency-city">
                <svg class="icon-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                <span><?= e($r['city_name']) ?></span>
            </h3>
            <span class="badge <?= e(urgency_class($r['urgency'])) ?>">
                <?= e($r['urgency']) ?>
            </span>
        </div>
        <div class="countdown-wrap">
            <svg class="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <div class="countdown" data-countdown="<?= e(iso_dt($r['required_by'])) ?>" role="timer" aria-label="Time left">Calculating...</div>
        </div>
    </div>
</article>
