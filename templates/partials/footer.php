<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand-col">
                <div class="brand footer-brand">
                    <span class="brand-mark small" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                    </span>
                    <span>Donor<strong>Nexa</strong></span>
                </div>
                <p class="footer-tagline">Connecting Donors. Bridging Lives. Saving Hope.</p>
            </div>
            <div class="footer-col">
                <h4>Platform</h4>
                <ul>
                    <li><a href="<?= e(url('emergencies')) ?>">Active Emergencies</a></li>
                    <li><a href="<?= e(url('register/donor')) ?>">Register as Donor</a></li>
                    <li><a href="<?= e(url('register/hospital')) ?>">Register Hospital</a></li>
                    <li><a href="<?= e(url('login')) ?>">Sign In</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Privacy & Safety</h4>
                <ul class="security-features">
                    <li>Masked Contact Numbers</li>
                    <li>Automated 90-Day Cool-Down</li>
                    <li>Verified Licences Only</li>
                    <li>Zero Double-Allocation</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <strong>DonorNexa</strong> · Emergency Blood-Donor Coordination Portal</p>
            <p class="muted">DonorNexa Healthcare Coordination Network · Official Production Portal</p>
        </div>
    </div>
</footer>
