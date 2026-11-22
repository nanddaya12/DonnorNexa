<section class="form-page wide auth-page" aria-labelledby="rh-h">
    <div class="auth-card-wrap wide">
        <div class="auth-header">
            <div class="auth-avatar avatar-hospital">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            </div>
            <h1 id="rh-h">Register a Medical Center / Hospital</h1>
            <p class="muted">Join Pakistan's emergency blood coordination network. Accounts require administrator licence verification before posting live requests.</p>
        </div>

        <div class="verification-notice-banner">
            <?= icon('shield', 20) ?>
            <p><strong>Licence Verification:</strong> To protect donor wellbeing and patient safety, hospitals must upload a valid medical licence PDF. Once approved by the administrator, you can broadcast emergency blood requests.</p>
        </div>

        <form method="post" action="<?= e(url('register/hospital/submit')) ?>" enctype="multipart/form-data" class="form-card auth-card" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="form-section-title">
                <span class="sec-step">1</span>
                <span>Institution Profile</span>
            </div>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="hospital_name">Hospital / Medical Center Name <span class="req">*</span></label>
                    <input id="hospital_name" name="hospital_name" required maxlength="150" placeholder="e.g. Liaquat University Hospital" value="<?= old('hospital_name') ?>" aria-describedby="err-hospital_name">
                    <?= field_error($errors, 'hospital_name') ?>
                </div>
                <div class="field">
                    <label for="email">Official Hospital Email <span class="req">*</span></label>
                    <input id="email" name="email" type="email" required autocomplete="email" placeholder="bloodbank@hospital.edu.pk" value="<?= old('email') ?>" data-rule="email" aria-describedby="err-email">
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="field">
                    <label for="phone">Official Landline / Mobile <span class="req">*</span></label>
                    <input id="phone" name="phone" type="tel" inputmode="numeric" placeholder="03XX-XXXXXXX" required value="<?= old('phone') ?>" data-rule="mobile" aria-describedby="err-phone">
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="field">
                    <label for="licence_no">Healthcare Commission / Licence No. <span class="req">*</span></label>
                    <input id="licence_no" name="licence_no" required maxlength="50" placeholder="e.g. SHCC-2024-891" value="<?= old('licence_no') ?>" aria-describedby="err-licence_no">
                    <?= field_error($errors, 'licence_no') ?>
                </div>
                <div class="field">
                    <label for="city_id">City Location <span class="req">*</span></label>
                    <select id="city_id" name="city_id" required aria-describedby="err-city_id">
                        <option value="">Choose city…</option>
                        <?php foreach ($cities as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('city_id'), $c['id']) ?>><?= e($c['city_name']) ?> (<?= e($c['province']) ?>)</option><?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'city_id') ?>
                </div>
            </div>

            <div class="form-section-title mt-4">
                <span class="sec-step">2</span>
                <span>Security & Credentials</span>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="password">Password <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="At least 8 chars" data-rule="password" aria-describedby="hint-password err-password">
                        <button type="button" class="btn-pwd-toggle" aria-label="Toggle password visibility"><?= icon('eye', 16) ?></button>
                    </div>
                    <small id="hint-password" class="hint">8+ characters, at least one letter and one number.</small>
                    <?= field_error($errors, 'password') ?>
                </div>
                <div class="field">
                    <label for="password_confirm">Confirm Password <span class="req">*</span></label>
                    <div class="input-icon-wrap">
                        <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password" placeholder="Repeat password" data-rule="match:password" aria-describedby="err-password_confirm">
                        <button type="button" class="btn-pwd-toggle" aria-label="Toggle password visibility"><?= icon('eye', 16) ?></button>
                    </div>
                    <?= field_error($errors, 'password_confirm') ?>
                </div>
                <div class="field span-2">
                    <label for="licence_file">Official Medical Licence Document <span class="req">*</span> <span class="muted">(PDF up to 5 MB)</span></label>
                    <div class="custom-file-dropzone pdf-dropzone" id="licence-dropzone">
                        <input id="licence_file" name="licence_file" type="file" accept="application/pdf" required data-rule="pdf" aria-describedby="err-licence_file">
                        <div class="dropzone-prompt">
                            <span class="dropzone-icon"><?= icon('file-text', 24) ?></span>
                            <span>Click or drag your hospital registration licence PDF here</span>
                            <span class="dropzone-sub">Official PDF only (Max 5MB) · Strictly confidential for admin audit</span>
                        </div>
                        <div class="file-preview-wrap" id="licence-preview-wrap" style="display:none;">
                            <span class="pdf-icon"><?= icon('file', 20) ?></span>
                            <span id="licence-filename" class="preview-filename"></span>
                        </div>
                    </div>
                    <?= field_error($errors, 'licence_file') ?>
                </div>
            </div>

            <div class="auth-submit-row">
                <button class="btn btn-primary-glow btn-lg btn-block" type="submit">
                    <span>Submit Hospital for Verification</span>
                    <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
                <p class="muted center mt-2">Already have credentials? <a href="<?= e(url('login')) ?>">Sign in here</a></p>
            </div>
        </form>
    </div>
</section>
