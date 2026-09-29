{{--
  Complaint action modal — shared by complaints/index, complaints/show,
  assignments/index. Opens via openComplaintActions(id); all traffic is
  fetch + JSON against the complaints.modal / route endpoints.

  Category correction is the only write. Status and resolution notes are
  owned by the offsite team and are shown here read-only, so there is no
  status form and no resolve form to submit.
--}}
<div class="modal-overlay" id="complaint-modal" hidden>
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="complaint-modal-title">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="complaint-modal-title">Complaint <span id="cm-code"></span></div>
        <div class="modal-sub" id="cm-customer"></div>
      </div>
      <button type="button" class="modal-close" data-cm-close title="Close"><i data-lucide="x"></i></button>
    </div>

    <div class="modal-body">
      <p class="cm-desc" id="cm-description"></p>

      <div class="cm-meta" id="cm-meta"></div>

      <div class="cm-section" id="cm-assigned">
        <span class="cm-label">Assigned to</span>
        <span id="cm-leader" class="cm-value"></span>
      </div>

      {{-- 1. Status (read-only) --}}
      <div class="cm-section">
        <span class="cm-label">Status</span>
        <div class="cm-row">
          <span class="cm-value" id="cm-status">—</span>
        </div>
        <div class="cm-hint">Read-only. Moving a complaint along is the offsite team's task, so it is changed from the mobile app.</div>
      </div>

      {{-- 2. Route / Assign --}}
      <form class="cm-form" id="cm-route-form">
        @csrf
        <div class="cm-section">
          <span class="cm-label" for="cm-category">Correct category &amp; auto-route</span>
          <div class="cm-row">
            <div class="cat-dd cm-cat-dd" id="cm-category">
              <input type="hidden" name="category" value="" data-cat-dd-value />
              <button type="button" class="cat-dd-btn" data-cat-dd-toggle title="Select category">
                <span class="cat-dd-label">
                  <span class="cat-dd-dot"></span>
                  <span data-cat-dd-text>— Select category —</span>
                </span>
                <span class="cat-dd-caret">&#9662;</span>
              </button>
              <div class="cat-dd-panel" role="listbox">
                <button type="button" class="cat-dd-row" data-value="" data-color="#cbd5e1"><span class="cat-dd-dot" style="background:#cbd5e1;"></span>— Select category —</button>
              </div>
            </div>
          </div>
          <span id="cm-auto" class="cm-auto"></span>
          <div class="cm-hint">Picking a different category updates the complaint and AI-routes it to the best available staff in that department (no extra click).</div>
        </div>
      </form>

      {{-- 3. Resolution notes (read-only) --}}
      <div class="cm-section">
        <span class="cm-label">Resolution notes</span>
        <p class="cm-desc" id="cm-notes">—</p>
      </div>

      <div id="cm-status-msg" class="cm-status-msg" hidden></div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  (function () {
    const overlay = document.getElementById('complaint-modal');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const modalUrl   = "{{ route('complaints.modal', '_ID_') }}".replace(/_ID_/g, '_ID');
    const routeUrl   = "{{ route('complaints.route', '_ID_') }}".replace(/_ID_/g, '_ID');
    let currentId = null;
    let currentCategory = '';

    // Default refresh hook. Complaints on screen call this after the category
    // changes so their table re-renders. A page that can reload itself should
    // assign window.onComplaintChanged; the no-op here keeps the call sites
    // safe when it is never defined, which is what happens today — the
    // category change landed but the list stayed stale.
    if (typeof window.onComplaintChanged !== 'function') {
      window.onComplaintChanged = function () {};
    }

    window.openComplaintActions = function (id) {
      currentId = id;
      overlay.hidden = false;
      document.body.style.overflow = 'hidden';
      const msg = document.getElementById('cm-status-msg');
      msg.hidden = true;
      msg.textContent = '';
      document.getElementById('cm-auto').textContent = '';

      fetch(modalUrl.replace('_ID', id), { headers: { 'X-CSRF-TOKEN': csrfToken } })
        .then(readJson)
        .then(({ ok, status, json }) => {
          if (!ok) { showMsg(failureMessage(json, 'Could not load complaint details.', status), false); return; }
          fill(json.data);
          currentCategory = json.data.category || '';
        })
        .catch(() => showMsg('Could not reach the server.', false));
    };

    window.closeComplaintActions = function () {
      overlay.hidden = true;
      document.body.style.overflow = '';
      currentId = null;
    };

    overlay.querySelector('[data-cm-close]').addEventListener('click', closeComplaintActions);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeComplaintActions(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeComplaintActions(); });

    function fill(data) {
      document.getElementById('cm-code').textContent = data.code;
      document.getElementById('cm-customer').textContent = data.customer || 'Unknown customer';
      document.getElementById('cm-description').textContent = data.description;
      setStatus(data.status);
      document.getElementById('cm-notes').textContent =
        (data.current && data.current.resolution_notes) || '—';

      const meta = document.getElementById('cm-meta');
      meta.innerHTML = [
        ['Category', data.category || '—'],
        ['Severity', data.severity || '—'],
        ['Status', data.status.replace('_', ' ')],
        ['Location', data.location || '—'],
        ['Submitted', data.submitted_at ? new Date(data.submitted_at).toLocaleString() : '—']
      ].map(([k, v]) => `<span class="cm-chip"><b>${k}</b> ${v}</span>`).join('');

      const leaderEl = document.getElementById('cm-leader');
      leaderEl.textContent = data.current
        ? (data.current.team_leader || '—') + ' (' + data.current.action_status.replace('_', ' ') + ')'
        : 'Unassigned';

      window.catDDSetOptions('cm-category',
        data.categories.map(function (c) {
          return { value: c.category_name, label: c.label, color: c.color || '#cbd5e1' };
        }),
        data.category || '',
        '— Select category —');

      if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function showMsg(text, ok) {
      const msg = document.getElementById('cm-status-msg');
      msg.textContent = text;
      msg.classList.toggle('ok', !!ok);
      msg.hidden = false;
    }

    function setStatus(status) {
      document.getElementById('cm-status').textContent =
        (status || '—').replace(/_/g, ' ');
    }

    /**
     * Read a fetch response without assuming the body is JSON.
     *
     * A 403/404 from Laravel's error handler is HTML, so r.json() used to
     * reject and the caller showed a generic message that hid the real
     * reason. Callers only see {ok, json} and decide what to display.
     */
    function readJson(r) {
      return r.json()
        .catch(() => ({}))
        .then(json => ({ ok: r.ok, status: r.status, json: json || {} }));
    }

    /**
     * Prefer the server's explanation.
     *
     * A bare "Failed." told an administrator nothing about whether they were
     * forbidden, missing the complaint, or hitting a validation rule. When the
     * body is not JSON at all — Laravel's error page — the status code is all
     * there is, so report it rather than hiding it.
     */
    function failureMessage(json, fallback, status) {
      if (json && json.message) return json.message;
      if (json && json.errors) {
        const first = Object.keys(json.errors)[0];
        if (first) return json.errors[first][0];
      }
      return status ? fallback + ' (HTTP ' + status + ').' : fallback;
    }

    function post(url, formData) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      }).then(readJson);
    }

    // Route (instant — fires on category change)
    window.catDDChanged = function (dd, value) {
      if (!dd || dd.id !== 'cm-category') return;
      const category = String(value || '').trim();
      if (!category || category === currentCategory) return;

      const fd = new FormData();
      fd.append('category', category);
      post(routeUrl.replace('_ID', currentId), fd).then(({ ok, status, json }) => {
        if (ok && json.data) {
          currentCategory = json.data.category || category;
          updateChip('Category', json.data.category || category);
          if (json.data.status) {
            setStatus(json.data.status);
            updateChip('Status', json.data.status.replace('_', ' '));
          }
          document.getElementById('cm-auto').textContent = json.data.team_leader
            ? 'Auto-assigned to: ' + json.data.team_leader + ' (' + String(json.data.department || '').replace('_', ' ') + ')'
            : '';
          window.onComplaintChanged();
        }
        showMsg(
          ok ? (json.message || 'Routed to available staff.')
             : failureMessage(json, 'Routing failed.', status),
          ok
        );
      });
    };

    function updateChip(key, value) {
      document.querySelectorAll('#cm-meta .cm-chip').forEach(chip => {
        const b = chip.querySelector('b');
        if (b && b.textContent === key) chip.innerHTML = '<b>' + key + '</b> ' + value;
      });
    }
  })();
</script>
@endpush