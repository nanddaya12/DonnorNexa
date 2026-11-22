<section aria-labelledby="ac-h" class="admin-page-view">
    <div class="portal-header-card">
        <div>
            <span class="portal-eyebrow">Geographic Coverage</span>
            <h1 id="ac-h">Cities & Provincial Directory</h1>
            <p class="muted">Add new municipal jurisdictions and verify active counts of hospitals and registered donors across regions.</p>
        </div>
    </div>

    <form method="post" action="<?= e(url('admin/city/add')) ?>" class="filter-bar filter-bar-styled mt-4">
        <?= csrf_field() ?>
        <div class="filter-inputs">
            <input id="city_name" name="city_name" required maxlength="80" placeholder="New City Name (e.g. Sukkur)">
            <input id="province" name="province" required maxlength="40" placeholder="Province (e.g. Sindh)">
            <button class="btn btn-small btn-primary" type="submit">+ Add New City</button>
        </div>
    </form>

    <div class="table-wrap mt-3">
        <table class="table-modern">
            <caption class="sr-only">Cities in the system</caption>
            <thead><tr>
                <th scope="col">City Name</th>
                <th scope="col">Province / Region</th>
                <th scope="col">Registered Donors</th>
                <th scope="col">Registered Hospitals</th>
                <th scope="col" class="center">Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($cities as $c): ?>
                <tr>
                    <td><strong><?= e($c['city_name']) ?></strong></td>
                    <td><span class="province-tag"><?= e($c['province']) ?></span></td>
                    <td><span class="count-tag count-donors"><?= (int) $c['donors'] ?> donors</span></td>
                    <td><span class="count-tag count-hospitals"><?= (int) $c['hospitals'] ?> hospitals</span></td>
                    <td class="center">
                        <?php if ((int) $c['donors'] + (int) $c['hospitals'] === 0): ?>
                        <form method="post" action="<?= e(url('admin/city/delete')) ?>" data-confirm="Are you sure you want to delete this city?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-small btn-danger" type="submit">Delete</button>
                        </form>
                        <?php else: ?>
                            <span class="muted small" title="Cannot delete city with active users or hospitals">In Use</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
