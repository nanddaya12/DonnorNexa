<section class="form-page wide inventory-page" aria-labelledby="inv-h">
    <div class="portal-header-card">
        <div>
            <span class="portal-eyebrow">Hospital Blood Bank Storage</span>
            <h1 id="inv-h">Live Blood Unit Inventory</h1>
            <p class="muted">Automatic increments occur when donors are marked as donated. Adjust reserves manually below as needed.</p>
        </div>
        <div class="inventory-total-chip">
            <span class="total-count"><?= array_sum($inventory) ?></span>
            <span class="total-label">Total Units in Stock</span>
        </div>
    </div>

    <form method="post" action="<?= e(url('hospital/inventory/save')) ?>" class="form-card inventory-card mt-4">
        <?= csrf_field() ?>
        <div class="inventory-grid">
            <?php foreach ($inventory as $group => $units): ?>
                <div class="inventory-vial-card">
                    <div class="vial-top">
                        <span class="blood-badge small"><?= e($group) ?></span>
                        <span class="stock-status <?= $units > 0 ? 'stock-ok' : 'stock-zero' ?>">
                            <?= $units > 0 ? (int)$units . ' in stock' : 'Depleted' ?>
                        </span>
                    </div>
                    <div class="vial-control">
                        <label for="u-<?= e($group) ?>" class="sr-only"><?= e($group) ?> Units</label>
                        <div class="counter-input-group">
                            <button type="button" class="btn-step btn-minus" data-target="u-<?= e($group) ?>" aria-label="Decrease <?= e($group) ?>">-</button>
                            <input id="u-<?= e($group) ?>" name="units[<?= e($group) ?>]" type="number" min="0" max="9999" required value="<?= (int) $units ?>" class="counter-input">
                            <button type="button" class="btn-step btn-plus" data-target="u-<?= e($group) ?>" aria-label="Increase <?= e($group) ?>">+</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="inventory-submit-strip">
            <button class="btn btn-primary-glow btn-lg" type="submit">
                <span>Save Inventory Changes</span>
            </button>
            <a href="<?= e(url('hospital/dashboard')) ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</section>
