<?php $limits = ['cooldown_days' => [30, 365], 'top_n' => [1, 50], 'response_hours' => [1, 72]]; ?>
<section class="form-page wide auth-page" aria-labelledby="as-h">
    <div class="auth-card-wrap wide">
        <div class="auth-header">
            <div class="auth-avatar avatar-settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </div>
            <h1 id="as-h">System Engine Policies & Configuration</h1>
            <p class="muted">Tune the parameters that control biological safety cooldowns, algorithmic candidate pool sizes, and match response timeouts.</p>
        </div>

        <form method="post" action="<?= e(url('admin/settings/save')) ?>" class="form-card auth-card">
            <?= csrf_field() ?>
            <div class="form-grid">
                <?php foreach ($settings as $s): [$min, $max] = $limits[$s['setting_key']] ?? [1, 999]; ?>
                    <div class="field span-2 settings-field-card">
                        <div class="setting-desc-wrap">
                            <label for="<?= e($s['setting_key']) ?>" class="setting-title"><?= e($s['description']) ?></label>
                            <span class="setting-key-code"><code><?= e($s['setting_key']) ?></code></span>
                        </div>
                        <div class="setting-input-wrap">
                            <input id="<?= e($s['setting_key']) ?>" name="<?= e($s['setting_key']) ?>" type="number" min="<?= $min ?>" max="<?= $max ?>" required value="<?= (int) $s['setting_value'] ?>" aria-describedby="h-<?= e($s['setting_key']) ?>">
                            <span id="h-<?= e($s['setting_key']) ?>" class="hint">Permitted range: <?= $min ?> – <?= $max ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="auth-submit-row mt-4">
                <button class="btn btn-primary-glow btn-lg btn-block" type="submit">
                    <span>Save Platform Settings</span>
                </button>
                <p class="muted center mt-2"><a href="<?= e(url('admin/dashboard')) ?>">&larr; Return to Dashboard</a></p>
            </div>
        </form>
    </div>
</section>
