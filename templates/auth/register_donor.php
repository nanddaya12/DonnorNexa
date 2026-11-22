<section class="form-page wide auth-page" aria-labelledby="rd-h">
    <div class="auth-card-wrap wide">
        <div class="auth-header">
            <div class="auth-avatar avatar-donor">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            </div>
            <h1 id="rd-h">Register as a Blood Donor</h1>
            <p class="muted">Join our verified network of life savers. Your contact number remains completely private until you explicitly accept a request.</p>
        </div>

        <form method="post" action="<?= e(url('register/donor/submit')) ?>" enctype="multipart/form-data" class="form-card auth-card" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-section-title">
                <span class="sec-step">1</span>
                <span>Account Credentials</span>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="name">Full Name <span class="req">*</span></label>
                    <input id="name" name="name" required maxlength="100" autocomplete="name" placeholder="e.g. Ahmed Ali" value="<?= old('name') ?>" data-rule="name" aria-describedby="err-name">
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="field">
                    <label for="email">Email Address <span class="req">*</span></label>
                    <input id="email" name="email" type="email" required autocomplete="email" placeholder="ahmed@example.com" value="<?= old('email') ?>" data-rule="email" aria-describedby="err-email">
                    <?= field_error($errors, 'email') ?>
                </div>
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
            </div>

            <div class="form-section-title mt-4">
                <span class="sec-step">2</span>
                <span>Medical & Biological Profile</span>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="blood_group">Blood Group <span class="req">*</span></label>
                    <select id="blood_group" name="blood_group" required aria-describedby="err-blood_group">
                        <option value="">Choose blood group…</option>
                        <?php foreach (blood_groups() as $g): ?><option value="<?= e($g) ?>"<?= selected(old('blood_group'), $g) ?>><?= e($g) ?></option><?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'blood_group') ?>
                </div>
                <div class="field">
                    <label for="dob">Date of Birth <span class="req">*</span></label>
                    <input id="dob" name="dob" type="date" required value="<?= old('dob') ?>" data-rule="dob" aria-describedby="err-dob">
                    <small class="hint">Donors must be between 18 and 60 years of age.</small>
                    <?= field_error($errors, 'dob') ?>
                </div>
                <div class="field">
                    <label for="weight">Weight (kg) <span class="req">*</span></label>
                    <input id="weight" name="weight" type="number" inputmode="decimal" min="30" max="250" step="0.1" placeholder="e.g. 68" required value="<?= old('weight') ?>" data-rule="weight" aria-describedby="err-weight">
                    <small class="hint">Minimum 50 kg for emergency blood donation.</small>
                    <?= field_error($errors, 'weight') ?>
                </div>
                <div class="field">
                    <label for="city_id">Residential City <span class="req">*</span></label>
                    <select id="city_id" name="city_id" required aria-describedby="err-city_id">
                        <option value="">Choose city…</option>
                        <?php foreach ($cities as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected(old('city_id'), $c['id']) ?>><?= e($c['city_name']) ?> (<?= e($c['province']) ?>)</option><?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'city_id') ?>
                </div>
            </div>

            <div class="form-section-title mt-4">
                <span class="sec-step">3</span>
                <span>Confidential Contact & Identification</span>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="phone">Mobile Number <span class="req">*</span></label>
                    <input id="phone" name="phone" type="tel" inputmode="numeric" placeholder="0300-1234567" required value="<?= old('phone') ?>" data-rule="mobile" aria-describedby="err-phone">
                    <small class="hint">Format: 03XX-XXXXXXX (Strictly masked from public view)</small>
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="field">
                    <label for="cnic">CNIC <span class="muted">(optional)</span></label>
                    <input id="cnic" name="cnic" inputmode="numeric" placeholder="41303-1234567-1" value="<?= old('cnic') ?>" data-rule="cnic" aria-describedby="err-cnic">
                    <small class="hint">Format: #####-#######-#</small>
                    <?= field_error($errors, 'cnic') ?>
                </div>
                <div class="field span-2">
                    <label for="photo">Profile Photo <span class="muted">(optional, JPG/PNG up to 2 MB)</span></label>
                    <div class="custom-file-dropzone" id="photo-dropzone">
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" data-rule="photo" aria-describedby="err-photo">
                        <div class="dropzone-prompt">
                            <span class="dropzone-icon"><?= icon('camera', 24) ?></span>
                            <span>Click or drag photo here to upload</span>
                            <span class="dropzone-sub">JPG, PNG (Max 2MB)</span>
                        </div>
                        <div class="file-preview-wrap" id="photo-preview-wrap" style="display:none;">
                            <img id="photo-preview" src="" alt="Preview" class="avatar-preview">
                            <span id="photo-filename" class="preview-filename"></span>
                        </div>
                    </div>
                    <?= field_error($errors, 'photo') ?>
                </div>
            </div>

            <div class="auth-submit-row">
                <button class="btn btn-primary-glow btn-lg btn-block" type="submit">
                    <span>Complete Donor Registration</span>
                    <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
                <p class="muted center mt-2">Already registered? <a href="<?= e(url('login')) ?>">Sign in here</a></p>
            </div>
        </form>
    </div>
</section>
