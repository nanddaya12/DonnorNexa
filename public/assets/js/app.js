/* ==========================================================================
   DonorNexa Frontend Engine: Micro-interactions, Live Sync, Form UX
   ========================================================================== */

(function () {
  'use strict';
  document.documentElement.classList.add('js');

  var body = document.body;
  var apiBase = body.dataset.api || '';
  var csrf = body.dataset.csrf || '';

  /* ---------- Utility Helpers ---------- */
  function debounce(fn, wait) {
    var timer;
    return function () {
      var args = arguments, ctx = this;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
    };
  }

  function getJSON(file, params) {
    var url = apiBase + file + (params ? '?' + new URLSearchParams(params).toString() : '');
    return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
      });
  }

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) { node.className = cls; }
    if (text !== undefined) { node.textContent = text; }
    return node;
  }

  /* ---------- Mobile Navigation Drawer ---------- */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    // Close on outside click
    document.addEventListener('click', function (e) {
      if (!toggle.contains(e.target) && !nav.contains(e.target) && nav.classList.contains('open')) {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Alert Dismissal (CSP-Safe) ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.alert-close');
    if (btn) {
      var alert = btn.closest('.alert');
      if (alert) {
        alert.style.transition = 'opacity 0.2s, transform 0.2s';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-6px)';
        setTimeout(function () { alert.remove(); }, 200);
      }
    }
  });

  /* ---------- Countdown Timers ---------- */
  function pad(n) { return String(n).padStart(2, '0'); }

  function tickCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach(function (node) {
      var left = new Date(node.dataset.countdown).getTime() - Date.now();
      if (isNaN(left)) { return; }
      if (left <= 0) {
        node.textContent = 'Expired';
        node.classList.add('done');
        node.classList.remove('soon');
        return;
      }
      var s = Math.floor(left / 1000);
      var d = Math.floor(s / 86400);
      var h = Math.floor((s % 86400) / 3600);
      var m = Math.floor((s % 3600) / 60);
      var sec = s % 60;

      node.textContent = (d > 0 ? d + 'd ' : '') + pad(h) + 'h ' + pad(m) + 'm ' + pad(sec) + 's left';
      node.classList.toggle('soon', left < 3600000); // under 1 hour
    });
  }
  tickCountdowns();
  setInterval(tickCountdowns, 1000);

  /* ---------- Live Statistics Auto-Sync ---------- */
  var statNodes = document.querySelectorAll('[data-stat]');
  if (statNodes.length) {
    var refreshStats = function () {
      getJSON('stats.php').then(function (data) {
        statNodes.forEach(function (n) {
          if (data[n.dataset.stat] !== undefined) {
            n.textContent = data[n.dataset.stat];
          }
        });
      }).catch(function () {});
    };
    setInterval(refreshStats, 30000);
  }

  /* ---------- Public Emergency Board & Dynamic Filter ---------- */
  var board = document.getElementById('emergency-board');
  var activeFilter = 'all';

  if (board) {
    var limit = parseInt(board.dataset.limit || '50', 10);

    var applyFilter = function (filter) {
      activeFilter = (filter || 'all').toLowerCase().trim();
      var cards = board.querySelectorAll('.emergency-card');
      var visibleCount = 0;

      cards.forEach(function (card) {
        var cardUrgency = (
          card.getAttribute('data-urgency') ||
          card.dataset.urgency ||
          (card.className.match(/\burgency-(critical|urgent|normal)\b/i) || [])[1] ||
          ''
        ).toLowerCase().trim();

        if (activeFilter === 'all' || cardUrgency === activeFilter) {
          card.style.removeProperty('display');
          card.classList.remove('is-hidden');
          visibleCount++;
        } else {
          card.style.display = 'none';
          card.classList.add('is-hidden');
        }
      });

      var emptyFilter = document.getElementById('empty-filter');
      var emptyInitial = document.getElementById('empty-initial');
      if (emptyFilter) {
        emptyFilter.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
      }
      if (emptyInitial) {
        emptyInitial.style.display = (cards.length === 0) ? 'block' : 'none';
      }
    };

    var renderBoard = function (rows) {
      if (!rows || !Array.isArray(rows)) return;
      board.innerHTML = '';

      if (!rows.length) {
        var emptyP = el('p', 'empty', 'No active emergencies right now. All requests are currently fulfilled!');
        emptyP.id = 'empty-initial';
        board.appendChild(emptyP);
        return;
      }

      rows.slice(0, limit).forEach(function (r) {
        var card = el('article', 'emergency-card urgency-' + r.urgency.toLowerCase());
        card.dataset.urgency = r.urgency.toLowerCase();

        // Blood badge
        var badgeWrap = el('div', 'blood-badge-wrap');
        var badge = el('div', 'blood-badge');
        badge.setAttribute('aria-label', 'Blood group ' + r.blood_group);
        var grpText = el('span', 'blood-group-text', r.blood_group);
        badge.appendChild(grpText);
        badgeWrap.appendChild(badge);

        if (r.urgency.toLowerCase() === 'critical') {
          var critInd = el('span', 'critical-indicator');
          critInd.title = 'Critical Emergency Active';
          critInd.setAttribute('aria-label', 'Critical');
          badgeWrap.appendChild(critInd);
        }
        card.appendChild(badgeWrap);

        // Emergency Info
        var info = el('div', 'emergency-info');
        var headerRow = el('div', 'emergency-header-row');

        var cityH3 = el('h3', 'emergency-city');
        cityH3.innerHTML = '<svg class="icon-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg> <span>' + (r.city || '') + '</span>';
        headerRow.appendChild(cityH3);

        var urgBadge = el('span', 'badge badge-' + r.urgency.toLowerCase(), r.urgency);
        headerRow.appendChild(urgBadge);
        info.appendChild(headerRow);

        // Countdown wrap
        var cdWrap = el('div', 'countdown-wrap');
        cdWrap.innerHTML = '<svg class="icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
        var cd = el('div', 'countdown', 'Calculating...');
        cd.dataset.countdown = r.required_by;
        cd.setAttribute('role', 'timer');
        cd.setAttribute('aria-label', 'Time left');
        cdWrap.appendChild(cd);
        info.appendChild(cdWrap);

        card.appendChild(info);
        board.appendChild(card);
      });

      var filterEmptyP = el('p', 'empty', 'No emergencies matching the selected urgency.');
      filterEmptyP.id = 'empty-filter';
      filterEmptyP.style.display = 'none';
      board.appendChild(filterEmptyP);

      tickCountdowns();
      applyFilter(activeFilter);
    };

    var refreshBoard = function () {
      getJSON(board.dataset.endpoint).then(function (d) {
        if (d && Array.isArray(d.requests)) {
          renderBoard(d.requests);
        }
      }).catch(function () {});
    };

    // Filter buttons click
    var filterBtns = document.querySelectorAll('.urgency-filter-btn');
    filterBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var f = (btn.getAttribute('data-filter') || btn.dataset.filter || 'all').toLowerCase().trim();
        filterBtns.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        applyFilter(f);
      });
    });

    applyFilter(activeFilter);
    setInterval(refreshBoard, 20000);
  }

  /* ---------- Live Debounced Donor Search (Hospital) ---------- */
  var q = document.getElementById('donor-q');
  var grp = document.getElementById('donor-group');
  var out = document.getElementById('donor-results');
  if (q && grp && out) {
    var runSearch = function () {
      out.textContent = 'Searching active donor registry…';
      getJSON(out.dataset.endpoint, { q: q.value.trim(), blood_group: grp.value }).then(function (d) {
        out.textContent = '';
        if (!d.donors.length) {
          out.appendChild(el('p', 'muted', 'No eligible donors match your criteria right now.'));
          return;
        }
        d.donors.forEach(function (x) {
          var chip = el('div', 'donor-chip');
          var badge = el('div', 'blood-badge small', x.blood_group);
          chip.appendChild(badge);

          var info = el('div');
          var name = el('strong', '', x.name);
          info.appendChild(name);

          var sub = el('div', 'small muted', x.city + ' · Rested ' + (x.days_rested >= 999 ? '999+' : x.days_rested) + ' days');
          info.appendChild(sub);
          chip.appendChild(info);
          out.appendChild(chip);
        });
      }).catch(function () {
        out.textContent = 'Donor search temporarily unavailable.';
      });
    };
    q.addEventListener('input', debounce(runSearch, 300));
    grp.addEventListener('change', runSearch);
    runSearch();
  }

  /* ---------- Availability Switch Toggle (AJAX) ---------- */
  var avail = document.querySelector('[data-toggle-availability]');
  if (avail) {
    var status = document.getElementById('avail-status');
    avail.addEventListener('change', function () {
      var data = new URLSearchParams({ available: avail.checked ? '1' : '0' });
      fetch(apiBase + 'availability.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
      }).then(function (r) {
        if (!r.ok) { throw new Error(); }
        return r.json();
      }).then(function (d) {
        if (status) {
          status.textContent = d.available ? 'Marked Available' : 'Paused / Unavailable';
          status.style.color = d.available ? 'var(--emerald)' : 'var(--ink-500)';
        }
      }).catch(function () {
        avail.checked = !avail.checked;
        if (status) {
          status.textContent = 'Could not save. Please retry.';
          status.style.color = 'var(--primary)';
        }
      });
    });
  }

  /* ---------- Demo Credential Quick Fills ---------- */
  document.querySelectorAll('.btn-demo-fill').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var emailInput = document.getElementById('email');
      var passInput = document.getElementById('password');
      if (emailInput && passInput) {
        emailInput.value = btn.dataset.email;
        passInput.value = btn.dataset.pass;
        emailInput.classList.remove('invalid');
        emailInput.classList.add('valid');
        passInput.classList.remove('invalid');
        passInput.classList.add('valid');
      }
    });
  });

  /* ---------- Password Visibility Toggle ---------- */
  var iconEye = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  var iconEyeOff = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';

  document.querySelectorAll('.btn-pwd-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.previousElementSibling;
      if (input && input.tagName === 'INPUT') {
        var isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
        btn.innerHTML = isPass ? iconEyeOff : iconEye;
      }
    });
  });

  /* ---------- Quick Time Buttons in Request Form ---------- */
  document.querySelectorAll('.btn-time-helper').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var hours = parseInt(btn.dataset.hours, 10);
      var reqInput = document.getElementById('required_by');
      if (reqInput && !isNaN(hours)) {
        var targetDate = new Date(Date.now() + hours * 3600 * 1000);
        // Format YYYY-MM-DDTHH:MM for datetime-local
        var tzOffset = targetDate.getTimezoneOffset() * 60000;
        var localISOTime = new Date(targetDate.getTime() - tzOffset).toISOString().slice(0, 16);
        reqInput.value = localISOTime;
        reqInput.dispatchEvent(new Event('input'));
      }
    });
  });

  /* ---------- Inventory Steppers (+ / -) ---------- */
  document.querySelectorAll('.btn-step').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.dataset.target;
      var input = document.getElementById(targetId);
      if (input) {
        var val = parseInt(input.value || '0', 10);
        if (btn.classList.contains('btn-plus')) {
          val = Math.min(9999, val + 1);
        } else if (btn.classList.contains('btn-minus')) {
          val = Math.max(0, val - 1);
        }
        input.value = val;
      }
    });
  });

  /* ---------- File Upload Dropzone Previews ---------- */
  var photoInput = document.getElementById('photo');
  var photoDropzone = document.getElementById('photo-dropzone') || document.getElementById('profile-photo-dropzone');
  var photoPreview = document.getElementById('photo-preview');
  var photoPreviewWrap = document.getElementById('photo-preview-wrap');
  var photoFilename = document.getElementById('photo-filename');

  if (photoInput) {
    photoInput.addEventListener('change', function () {
      var f = photoInput.files && photoInput.files[0];
      if (f && photoPreview && photoPreviewWrap) {
        var reader = new FileReader();
        reader.onload = function (e) {
          photoPreview.src = e.target.result;
          photoPreviewWrap.style.display = 'flex';
          if (photoFilename) { photoFilename.textContent = f.name; }
          var prompt = photoDropzone ? photoDropzone.querySelector('.dropzone-prompt') : null;
          if (prompt) { prompt.style.display = 'none'; }
        };
        reader.readAsDataURL(f);
      }
    });
  }

  var licenceInput = document.getElementById('licence_file');
  var licenceDropzone = document.getElementById('licence-dropzone');
  var licencePreviewWrap = document.getElementById('licence-preview-wrap');
  var licenceFilename = document.getElementById('licence-filename');

  if (licenceInput && licencePreviewWrap && licenceFilename) {
    licenceInput.addEventListener('change', function () {
      var f = licenceInput.files && licenceInput.files[0];
      if (f) {
        licenceFilename.textContent = f.name + ' (' + (f.size / (1024 * 1024)).toFixed(2) + ' MB)';
        licencePreviewWrap.style.display = 'flex';
        var prompt = licenceDropzone ? licenceDropzone.querySelector('.dropzone-prompt') : null;
        if (prompt) { prompt.style.display = 'none'; }
      }
    });
  }

  /* ---------- In-Page Confirmation Modal (Replaces browser native window.confirm) ---------- */
  function showConfirmModal(message, isDanger, onConfirm) {
    var existing = document.getElementById('dn-confirm-modal');
    if (existing) existing.remove();

    var modalWrap = el('div', 'dn-modal-overlay');
    modalWrap.id = 'dn-confirm-modal';
    modalWrap.setAttribute('role', 'dialog');
    modalWrap.setAttribute('aria-modal', 'true');
    modalWrap.setAttribute('aria-labelledby', 'dn-modal-title');

    var card = el('div', 'dn-modal-card');

    var iconWrap = el('div', 'dn-modal-icon ' + (isDanger ? 'icon-danger' : 'icon-warning'));
    iconWrap.innerHTML = isDanger
      ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
      : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
    card.appendChild(iconWrap);

    var content = el('div', 'dn-modal-content');
    var title = el('h3', 'dn-modal-title', 'Confirm Action');
    title.id = 'dn-modal-title';
    content.appendChild(title);

    var desc = el('p', 'dn-modal-text', message);
    content.appendChild(desc);
    card.appendChild(content);

    var actions = el('div', 'dn-modal-actions');
    var btnCancel = el('button', 'btn btn-outline dn-modal-btn-cancel', 'Never Mind');
    btnCancel.type = 'button';

    var btnConfirm = el('button', 'btn ' + (isDanger ? 'btn-danger' : 'btn-primary') + ' dn-modal-btn-confirm', 'Yes, Proceed');
    btnConfirm.type = 'button';

    actions.appendChild(btnCancel);
    actions.appendChild(btnConfirm);
    card.appendChild(actions);

    modalWrap.appendChild(card);
    document.body.appendChild(modalWrap);

    setTimeout(function () { btnConfirm.focus(); }, 50);

    function closeModal() {
      modalWrap.classList.add('closing');
      setTimeout(function () { modalWrap.remove(); }, 180);
      document.removeEventListener('keydown', keyHandler);
    }

    function keyHandler(e) {
      if (e.key === 'Escape') closeModal();
    }
    document.addEventListener('keydown', keyHandler);

    btnCancel.addEventListener('click', closeModal);
    modalWrap.addEventListener('click', function (e) {
      if (e.target === modalWrap) closeModal();
    });

    btnConfirm.addEventListener('click', function () {
      closeModal();
      onConfirm();
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (f._confirmed) return;
      e.preventDefault();

      var msg = f.dataset.confirm || 'Are you sure you want to proceed?';
      var isDanger = f.querySelector('.btn-danger') !== null || /cancel|delete|block/i.test(msg);

      showConfirmModal(msg, isDanger, function () {
        f._confirmed = true;
        f.submit();
      });
    });
  });

  /* ---------- Inline Form Validation & Submit Prevention ---------- */
  var rules = {
    email: { test: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }, msg: 'Enter a valid email address.' },
    mobile: { test: function (v) { return /^03\d{2}-\d{7}$/.test(v); }, msg: 'Mobile must match 03XX-XXXXXXX format.' },
    cnic: { test: function (v) { return v === '' || /^\d{5}-\d{7}-\d$/.test(v); }, msg: 'CNIC must match 12345-1234567-1 format.' },
    name: { test: function (v) { return /^[A-Za-z\u00C0-\u024F\u0600-\u06FF][A-Za-z\u00C0-\u024F\u0600-\u06FF .'-]{1,99}$/.test(v); }, msg: "Use letters, spaces and . ' - only." },
    password: { test: function (v) { return /^(?=.*[A-Za-z])(?=.*\d).{8,72}$/.test(v); }, msg: 'Must be 8+ characters with at least 1 letter and 1 number.' },
    weight: { test: function (v) { var n = parseFloat(v); return n >= 30 && n <= 250; }, msg: 'Weight must be between 30 and 250 kg.' },
    dob: { test: function (v) {
      var d = new Date(v);
      if (isNaN(d)) { return false; }
      var age = (Date.now() - d.getTime()) / 31557600000;
      return age >= 18 && age <= 60;
    }, msg: 'Donors must be between 18 and 60 years old.' },
    photo: { test: function (v, input) {
      var f = input.files && input.files[0];
      if (!f) { return true; }
      return /^image\/(jpeg|png)$/.test(f.type) && f.size <= 2 * 1024 * 1024;
    }, msg: 'Choose a JPG or PNG up to 2 MB.' },
    pdf: { test: function (v, input) {
      var f = input.files && input.files[0];
      if (!f) { return false; }
      return f.type === 'application/pdf' && f.size <= 5 * 1024 * 1024;
    }, msg: 'Choose a PDF document up to 5 MB.' }
  };

  function checkField(input, form) {
    var rule = input.dataset.rule;
    var msgBox = document.getElementById('err-' + input.name);
    var value = input.value.trim();
    var message = '';

    if (input.required && value === '' && input.type !== 'file') {
      message = 'This field is required.';
    } else if (rule && (value !== '' || input.type === 'file')) {
      if (rule.indexOf('match:') === 0) {
        var other = form.elements[rule.slice(6)];
        if (other && other.value !== input.value) {
          message = 'Passwords do not match.';
        }
      } else if (rules[rule] && !rules[rule].test(value, input)) {
        message = rules[rule].msg;
      }
    }

    input.classList.toggle('invalid', message !== '');
    input.classList.toggle('valid', message === '' && (value !== '' || input.type === 'file'));
    input.setAttribute('aria-invalid', message !== '' ? 'true' : 'false');
    if (msgBox) { msgBox.textContent = message; }
    return message === '';
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    var fields = Array.prototype.filter.call(form.elements, function (f) {
      return f.name && f.type !== 'hidden' && f.type !== 'submit' && f.type !== 'checkbox' && !f.disabled;
    });

    fields.forEach(function (f) {
      f.addEventListener('blur', function () { checkField(f, form); });
      f.addEventListener('input', debounce(function () {
        if (f.classList.contains('invalid')) { checkField(f, form); }
      }, 250));
      f.addEventListener('change', function () { checkField(f, form); });
    });

    form.addEventListener('submit', function (e) {
      var bad = fields.filter(function (f) { return !checkField(f, form); });
      if (bad.length) {
        e.preventDefault();
        bad[0].focus();
      } else {
        // Prevent double submit
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.style.opacity = '0.7';
          submitBtn.style.pointerEvents = 'none';
        }
      }
    });
  });
})();
