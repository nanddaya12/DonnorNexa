<section aria-labelledby="em-h" class="emergencies-page">
    <div class="page-header-box">
        <div class="page-header-left">
            <h1 id="em-h">Emergency Blood Coordination Board</h1>
            <p class="muted">Live view of urgent requirements from verified medical centers. Patient names and donor identities are strictly protected.</p>
        </div>
        <div class="live-status-chip">
            <span class="live-status-dot"></span>
            <span>Live Auto-Sync Active (20s)</span>
        </div>
    </div>

    <div class="emergencies-filter-bar">
        <div class="filter-group">
            <span class="filter-label">Filter Urgency:</span>
            <div class="urgency-btn-group" role="group" aria-label="Filter emergencies by urgency">
                <button type="button" class="urgency-filter-btn active" data-filter="all">All Emergencies</button>
                <button type="button" class="urgency-filter-btn filter-critical" data-filter="critical"><span class="legend-dot dot-critical"></span> Critical</button>
                <button type="button" class="urgency-filter-btn filter-urgent" data-filter="urgent"><span class="legend-dot dot-urgent"></span> Urgent</button>
                <button type="button" class="urgency-filter-btn filter-normal" data-filter="normal"><span class="legend-dot dot-normal"></span> Normal</button>
            </div>
        </div>
    </div>

    <div class="urgency-legend">
        <span class="legend-item"><span class="legend-dot dot-critical"></span> <strong>Critical:</strong> Immediate life threat (Nationwide donor pool)</span>
        <span class="legend-item"><span class="legend-dot dot-urgent"></span> <strong>Urgent:</strong> Within 12-24 hours (Provincial pool)</span>
        <span class="legend-item"><span class="legend-dot dot-normal"></span> <strong>Normal:</strong> Scheduled transfusion (City pool)</span>
    </div>

    <div id="emergency-board" class="card-grid" data-endpoint="active_requests.php" data-limit="50" aria-live="polite">
        <?php foreach ($emergencies as $r): require BASE_PATH . '/templates/partials/emergency_card.php'; endforeach; ?>
        <?php if (!$emergencies): ?><p class="empty" id="empty-initial">No active emergencies right now. All requests are currently fulfilled!</p><?php endif; ?>
        <p class="empty" id="empty-filter" style="display:none;">No emergencies matching the selected urgency.</p>
    </div>

    <script>
    (function () {
        var board = document.getElementById('emergency-board');
        if (!board) return;

        var filterBtns = document.querySelectorAll('.urgency-filter-btn');
        var emptyFilter = document.getElementById('empty-filter');
        var emptyInitial = document.getElementById('empty-initial');

        function runFilter(selected) {
            var target = (selected || 'all').toLowerCase().trim();
            var cards = board.querySelectorAll('.emergency-card');
            var matched = 0;

            cards.forEach(function (card) {
                var urg = (
                    card.getAttribute('data-urgency') ||
                    card.dataset.urgency ||
                    (card.className.match(/\burgency-(critical|urgent|normal)\b/i) || [])[1] ||
                    ''
                ).toLowerCase().trim();

                if (target === 'all' || urg === target) {
                    card.style.removeProperty('display');
                    card.classList.remove('is-hidden');
                    matched++;
                } else {
                    card.style.display = 'none';
                    card.classList.add('is-hidden');
                }
            });

            if (emptyFilter) {
                emptyFilter.style.display = (matched === 0 && cards.length > 0) ? 'block' : 'none';
            }
            if (emptyInitial) {
                emptyInitial.style.display = (cards.length === 0) ? 'block' : 'none';
            }
        }

        filterBtns.forEach(function (btn) {
            btn.onclick = function (e) {
                e.preventDefault();
                var filter = (btn.getAttribute('data-filter') || btn.dataset.filter || 'all').toLowerCase().trim();
                filterBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                runFilter(filter);
            };
        });
    })();
    </script>
</section>
