<section class="form-page wide auth-page" aria-labelledby="pf-h">
    <div class="auth-card-wrap wide">
        <div class="auth-header">
            <div class="auth-avatar avatar-donor">
                <?php if ($donor->photo): ?>
                    <img class="avatar-img-circle" src="<?= e(upload_url($donor->photo)) ?>" alt="<?= e($donor->name) ?>" width="96" height="96">
                <?php else: ?>
                    <span class="blood-badge-avatar"><?= e($donor->blood_group) ?></span>
                <?php endif; ?>
            </div>
            <h1 id="pf-h">My Donor Profile</h1>
            <p class="muted">Manage your personal information, contact number, and biological parameters.</p>
        </div>

        <form method="post" action="<?= e(url('donor/profile/save')) ?>" enctype="multipart/form-data" class="form-card auth-card" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="form-section-title">
                <span class="sec-step">1</span>
                <span>Personal & Account Details</span>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="name">Full Name <span class="req">*</span></label>
                    <input id="name" name="name" required maxlength="100" value="<?= old('name') ?: e($donor->name) ?>" data-rule="name" aria-describedby="err-name">
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="field">
                    <label for="email">Email Address <span class="muted">(Immutable account key)</span></label>
                    <input id="email" type="email" value="<?= e($donor->email) ?>" disabled class="input-disabled">
                </div>
            </div>

            <div class="form-section-title mt-4">
                <span class="sec-step">2</span>
                <span>Biological & Medical Information</span>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="blood_group">Blood Group <span class="req">*</span></label>
                    <select id="blood_group" name="blood_group" required>
                        <?php $bg = old('blood_group') ?: e($donor->blood_group); foreach (blood_groups() as $g): ?>
                            <option value="<?= e($g) ?>"<?= selected($bg, $g) ?>><?= e($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'blood_group') ?>
                </div>
                <div class="field">
                    <label for="dob">Date of Birth <span class="req">*</span></label>
                    <input id="dob" name="dob" type="date" required value="<?= old('dob') ?: e($donor->dob) ?>" data-rule="dob" aria-describedby="err-dob">
                    <?= field_error($errors, 'dob') ?>
                </div>
                <div class="field">
                    <label for="weight">Weight (kg) <span class="req">*</span></label>
                    <input id="weight" name="weight" type="number" min="30" max="250" step="0.1" required value="<?= old('weight') ?: e($donor->weight) ?>" data-rule="weight" aria-describedby="err-weight">
                    <?= field_error($errors, 'weight') ?>
                </div>
                <div class="field">
                    <label for="city_id">Residential City <span class="req">*</span></label>
                    <select id="city_id" name="city_id" required>
                        <?php $cid = old('city_id') ?: e($donor->city_id); foreach ($cities as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= selected($cid, $c['id']) ?>><?= e($c['city_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'city_id') ?>
                </div>
            </div>

            <div class="form-section-title mt-4">
                <span class="sec-step">3</span>
                <span>Contact & Identification</span>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="phone">Mobile Number <span class="req">*</span></label>
                    <input id="phone" name="phone" type="tel" required value="<?= old('phone') ?: e($donor->phone) ?>" data-rule="mobile" aria-describedby="err-phone">
                    <small class="hint">Masked from hospitals until you accept an emergency match.</small>
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="field">
                    <label for="cnic">CNIC <span class="muted">(National ID)</span></label>
                    <input id="cnic" name="cnic" value="<?= old('cnic') ?: e($donor->cnic) ?>" data-rule="cnic" aria-describedby="err-cnic">
                    <?= field_error($errors, 'cnic') ?>
                </div>
                <div class="field span-2">
                    <label for="photo">Update Profile Photo <span class="muted">(JPG/PNG up to 2 MB)</span></label>
                    <div class="custom-file-dropzone" id="profile-photo-dropzone">
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" data-rule="photo" aria-describedby="err-photo">
                        <div class="dropzone-prompt">
                            <span class="dropzone-icon"></span>
                            <span>Choose a new picture or drag here</span>
                            <span class="dropzone-sub">JPG, PNG (Max 2MB)</span>
                        </div>
                    </div>
                    <?= field_error($errors, 'photo') ?>
                </div>
            </div>

            <div class="auth-submit-row mt-4">
                <button class="btn btn-primary-glow btn-lg btn-block" type="submit">
                    <span>Save Profile Changes</span>
                </button>
                <p class="muted center mt-2"><a href="<?= e(url('donor/dashboard')) ?>">&larr; Return to Dashboard</a></p>
            </div>
        </form>
    </div>
</section>
