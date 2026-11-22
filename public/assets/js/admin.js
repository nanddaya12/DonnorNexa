/* ==========================================================================
   DonorNexa Admin Console Charts & Telemetry (Chart.js Modern Theme)
   ========================================================================== */

(function () {
  'use strict';
  var api = document.body.dataset.api || '';
  var note = document.getElementById('chart-note');
  if (typeof Chart === 'undefined') {
    if (note) { note.textContent = 'Telemetry chart library unavailable.'; }
    return;
  }

  // Set Chart.js global defaults
  Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, -apple-system, sans-serif";
  Chart.defaults.color = '#64748b';

  var primaryRed = '#dc2626';
  var medicalBlue = '#0284c7';
  var statusColours = {
    open: '#0284c7',       // Sky Blue
    fulfilled: '#059669',  // Emerald
    closed: '#64748b',     // Slate
    cancelled: '#d97706',  // Amber
    expired: '#dc2626'     // Crimson
  };

  function refresh() {
    fetch(api + 'admin_stats.php', { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
      })
      .then(draw)
      .catch(function () {
        if (note) { note.textContent = 'Could not sync real-time telemetry.'; }
      });
  }

  var charts = {};
  function make(id, config) {
    if (charts[id]) { charts[id].destroy(); }
    var canvas = document.getElementById(id);
    if (canvas) {
      charts[id] = new Chart(canvas, config);
    }
  }

  function draw(d) {
    document.querySelectorAll('[data-sum]').forEach(function (n) {
      var v = d.summary[n.dataset.sum];
      if (v !== undefined) {
        n.textContent = n.dataset.sum === 'fulfilment_rate' ? v + '%' : v;
      }
    });

    // 1. Weekly Requests Bar Chart
    make('chart-weekly', {
      type: 'bar',
      data: {
        labels: d.weekly.map(function (w) { return w.label; }),
        datasets: [{
          label: 'Emergency Postings',
          data: d.weekly.map(function (w) { return w.total; }),
          backgroundColor: '#dc2626',
          borderRadius: 8,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#0f172a',
            padding: 12,
            cornerRadius: 8,
            titleFont: { weight: 'bold' }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            ticks: { precision: 0 },
            grid: { color: 'rgba(226, 232, 240, 0.6)' }
          }
        }
      }
    });

    // 2. Request Status Distribution Doughnut Chart
    make('chart-status', {
      type: 'doughnut',
      data: {
        labels: d.status.map(function (s) { return s.status.charAt(0).toUpperCase() + s.status.slice(1); }),
        datasets: [{
          data: d.status.map(function (s) { return s.total; }),
          backgroundColor: d.status.map(function (s) { return statusColours[s.status] || '#94a3b8'; }),
          borderWidth: 3,
          borderColor: '#ffffff',
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 12, padding: 16, font: { weight: '600' } }
          },
          tooltip: {
            backgroundColor: '#0f172a',
            padding: 12,
            cornerRadius: 8
          }
        }
      }
    });

    // 3. Demand vs Supply Group Bar Chart
    make('chart-groups', {
      type: 'bar',
      data: {
        labels: d.groups.map(function (g) { return g.group; }),
        datasets: [
          {
            label: 'Demand (Units Needed)',
            data: d.groups.map(function (g) { return g.demand; }),
            backgroundColor: '#dc2626',
            borderRadius: 6,
            borderSkipped: false
          },
          {
            label: 'Supply (Eligible Donors)',
            data: d.groups.map(function (g) { return g.supply; }),
            backgroundColor: '#0284c7',
            borderRadius: 6,
            borderSkipped: false
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
            labels: { boxWidth: 14, padding: 14, font: { weight: '600' } }
          },
          tooltip: {
            backgroundColor: '#0f172a',
            padding: 12,
            cornerRadius: 8
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            ticks: { precision: 0 },
            grid: { color: 'rgba(226, 232, 240, 0.6)' }
          }
        }
      }
    });

    if (note) {
      note.textContent = 'Telemetry synced in real time (refreshes every 60s).';
    }
  }

  refresh();
  setInterval(refresh, 60000);
})();
