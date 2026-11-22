<section class="hero" aria-labelledby="hero-h">
    <h1 id="hero-h">
        Connecting Donors.<br>
        Bridging Lives. <span class="accent-gradient">Saving Hope.</span>
    </h1>
    <p class="lead">
        DonorNexa connects verified hospitals with scored, eligible donors in minutes. Zero public phone numbers, zero spam calls, and strict cryptographic transaction protection against double allocation.
    </p>
    <div class="hero-actions">
        <a class="btn btn-hero btn-primary-glow" href="<?= e(url('register/donor')) ?>">
            <span>Become a Donor</span>
        </a>
        <a class="btn btn-hero btn-outline-hero" href="<?= e(url('register/hospital')) ?>">
            <span>Register Hospital</span>
        </a>
        <a class="btn btn-hero btn-secondary-hero" href="<?= e(url('emergencies')) ?>">
            <span>Live Emergencies Board</span>
        </a>
    </div>

    <div class="trust-strip">
        <div class="trust-item">
            <strong>Privacy Guaranteed</strong>
            <span>Masked phone numbers until accepted</span>
        </div>
        <div class="trust-item">
            <strong>Instant Scoring</strong>
            <span>Proximity, compatibility & rest days</span>
        </div>
        <div class="trust-item">
            <strong>Zero Race Conditions</strong>
            <span>Row-locked atomicity prevents over-allocation</span>
        </div>
    </div>
</section>

<section class="stats-grid-wrap" aria-label="Live statistics">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-num" data-stat="active_requests"><?= (int) $stats['active_requests'] ?></span>
                <span class="stat-label">Active Emergencies</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-num" data-stat="registered_donors"><?= (int) $stats['registered_donors'] ?></span>
                <span class="stat-label">Registered Donors</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-content">
                <span class="stat-num" data-stat="units_fulfilled"><?= (int) $stats['units_fulfilled'] ?></span>
                <span class="stat-label">Units Coordinated</span>
            </div>
        </div>
    </div>
</section>

<section aria-labelledby="live-h" class="section section-emergencies">
    <div class="section-head">
        <div>
            <h2 id="live-h">Active Emergency Requests</h2>
            <p class="section-desc">Broadcasted to eligible, rested donors in proximity. Auto-refreshed in real time.</p>
        </div>
        <a class="btn btn-outline btn-small view-all-link" href="<?= e(url('emergencies')) ?>">
            <span>View All Board</span>
            <?= icon('arrow-right', 16) ?>
        </a>
    </div>
    <div id="emergency-board" class="card-grid" data-endpoint="active_requests.php" data-limit="6" aria-live="polite">
        <?php foreach ($emergencies as $r): require BASE_PATH . '/templates/partials/emergency_card.php'; endforeach; ?>
        <?php if (!$emergencies): ?><p class="empty">No active emergencies right now. All requests are currently fulfilled!</p><?php endif; ?>
    </div>
</section>

<section class="section section-how" aria-labelledby="how-h">
    <div class="section-head-center">
        <h2 id="how-h">How Smart Coordination Works</h2>
        <p class="section-desc max-w-md">DonorNexa replaces chaotic social media forwards with an algorithmic, secure pipeline.</p>
    </div>
    <div class="steps-flow">
        <div class="step-card">
            <div class="step-number">01</div>
            <h3>Hospital Posts Emergency</h3>
            <p>Verified medical personnel specify recipient blood group, required units, urgency, and deadline. Multi-layer validation enforces safety quotas.</p>
        </div>
        <div class="step-arrow"><?= icon('arrow-right', 20) ?></div>
        <div class="step-card">
            <div class="step-number">02</div>
            <h3>Algorithmic Match & Score</h3>
            <p>Our scoring engine checks ABO/Rh compatibility, ensures 90+ days cooldown, measures geographic proximity, and prioritizes highest-score donors.</p>
        </div>
        <div class="step-arrow"><?= icon('arrow-right', 20) ?></div>
        <div class="step-card">
            <div class="step-number">03</div>
            <h3>Accept & Secure Contact Reveal</h3>
            <p>When an available donor accepts, their contact number is securely revealed to the hospital in real-time, locking remaining units with immediate audit tracking.</p>
        </div>
    </div>
</section>

<section class="section section-compatibility" aria-labelledby="compat-h">
    <div class="compat-box">
        <div class="compat-header">
            <div>
                <h2 id="compat-h">Blood Compatibility Quick Reference</h2>
                <p class="muted">Check which blood groups you can donate to or receive from safely.</p>
            </div>
            <span class="blood-universal-pill">O- is Universal Donor · AB+ is Universal Recipient</span>
        </div>
        <div class="compat-grid">
            <div class="compat-card">
                <span class="compat-group">O-</span>
                <span class="compat-role">Universal Donor</span>
                <p class="compat-details">Can donate red cells to <strong>any</strong> blood group. Critical in trauma units.</p>
            </div>
            <div class="compat-card">
                <span class="compat-group">O+</span>
                <span class="compat-role">High Demand</span>
                <p class="compat-details">Can donate to O+, A+, B+, AB+. Most common blood type.</p>
            </div>
            <div class="compat-card">
                <span class="compat-group">A+ / B+</span>
                <span class="compat-role">Vital Support</span>
                <p class="compat-details">Can donate to same group and AB+. Supports hospital regional inventories.</p>
            </div>
            <div class="compat-card">
                <span class="compat-group">AB+</span>
                <span class="compat-role">Universal Recipient</span>
                <p class="compat-details">Can receive red blood cells from <strong>any</strong> blood group safely.</p>
            </div>
        </div>
    </div>
</section>
