<section class="form-page auth-page" aria-labelledby="login-h">
    <div class="auth-card-wrap">
        <div class="auth-header">
            <h1 id="login-h">Sign in to DonorNexa</h1>
            <p class="muted">Access your hospital console, donor portal, or administrator dashboard.</p>
        </div>

        <form method="post" action="<?= e(url('login/submit')) ?>" class="form-card auth-card" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email Address</label>
                <div class="input-icon-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <input id="email" name="email" type="email" autocomplete="username" placeholder="name@organization.pk" required value="<?= old('email') ?>" data-rule="email" aria-describedby="err-email">
                </div>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="field">
                <div class="field-label-row">
                    <label for="password">Password</label>
                </div>
                <div class="input-icon-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Password" required aria-describedby="err-password">
                    <button type="button" class="btn-pwd-toggle" aria-label="Toggle password visibility"><?= icon('eye', 16) ?></button>
                </div>
                <?= field_error($errors, 'password') ?>
            </div>
            <label class="check custom-checkbox">
                <input type="checkbox" name="remember" value="1">
                <span>Remember me for 30 days</span>
            </label>
            <button class="btn btn-block btn-primary-glow btn-lg" type="submit">
                <span>Sign In</span>
                <?= icon('arrow-right', 16) ?>
            </button>

            <div class="auth-divider"><span>OR</span></div>
            <div class="auth-links center">
                <span>Don't have an account yet?</span><br>
                <a href="<?= e(url('register/donor')) ?>" class="auth-alt-link">Register as a Donor</a> · 
                <a href="<?= e(url('register/hospital')) ?>" class="auth-alt-link">Register a Hospital</a>
            </div>
        </form>
    </div>
</section>
