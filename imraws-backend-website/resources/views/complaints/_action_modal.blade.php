{{--
  Complaint action modal — shared by complaints/index, complaints/show,
  assignments/index. Opens via openComplaintActions(id); all traffic is
  fetch + JSON against the complaints.modal / updateStatus / route /
  resolve endpoints.
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

      {{-- 1. Status --}}
      <form class="cm-form" id="cm-status-form">
        @csrf
        <input type="hidden" name="status_target" value="" />
        <div class="cm-section">
          <span class="cm-label" for="cm-status">Update status</span>
          <div class="cm-row">
            <select name="status" id="cm-status" class="cm-select">
              <option value="open">Open</option>
              <option value="assigned">Assigned</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="rejected">Rejected</option>
            </select>
            <button type="submit" class="btn-primary" style="padding:0.4rem 1rem;">Save</button>
          </div>
        </div>
      </form>

      {{-- 2. Route / Assign --}}
      <form class="cm-form" id="cm-route-form">
        @csrf
        <div class="cm-section">
          <span class="cm-label" for="cm-leaders">Route / assign to</span>
          <div class="cm-row">
            <select name="team_leader_id" id="cm-leaders" class="cm-select">
              <option value="">— Select team leader —</option>
            </select>
            <button type="submit" class="btn-outline" style="padding:0.4rem 1rem;">Route</button>
          </div>
        </div>
      </form>

      {{-- 3. Resolve --}}
      <form class="cm-form" id="cm-resolve-form">
        @csrf
        <div class="cm-section">
          <span class="cm-label" for="cm-notes">Resolve</span>
          <textarea name="resolution_notes" id="cm-notes" rows="2" placeholder="Resolution notes (optional)"></textarea>
          <div style="margin-top:0.5rem;">
            <button type="submit" class="btn-success" style="padding:0.4rem 1rem;"><i data-lucide="check-circle" style="width:1rem;height:1rem;"></i> Mark Resolved</button>
          </div>
        </div>
      </form>

      <div id="cm-status-msg" class="cm-status-msg" hidden></div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  (function () {
    const overlay = document.getElementById('complaint-modal');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getContent();
    const modalUrl   = "{{ route('complaints.modal', '_ID_') }}".replace(/_ID_/g, '_ID');
    const statusUrl  = "{{ route('complaints.updateStatus', '_ID_') }}".replace(/_ID_/g, '_ID');
    const routeUrl   = "{{ route('complaints.route', '_ID_') }}".replace(/_ID_/g, '_ID');
    const resolveUrl = "{{ route('complaints.resolve', '_ID_') }}".replace(/_ID_/g, '_ID');
    let currentId = null;

    window.openComplaintActions = function (id) {
      currentId = id;
      overlay.hidden = false;
      document.body.style.overflow = 'hidden';
      const msg = document.getElementById('cm-status-msg');
      msg.hidden = true;
      msg.textContent = '';

      fetch(modalUrl.replace('_ID', id), { headers: { 'X-CSRF-TOKEN': csrfToken } })
        .then(r => r.json())
        .then(json => fill(json.data))
        .catch(() => showMsg('Could not load complaint details.'));
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
      document.getElementById('cm-status').value = data.status;

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

      const leaders = document.getElementById('cm-leaders');
      leaders.innerHTML = '<option value="">— Select team leader —</option>' +
        data.team_leaders.map(t =>
          `<option value="${t.id}">${t.full_name} · ${(t.department_team || 'all').replace('_', ' ')} · ${t.active_assignments} active</option>`
        ).join('');
      if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function showMsg(text, ok) {
      const msg = document.getElementById('cm-status-msg');
      msg.textContent = text;
      msg.classList.toggle('ok', !!ok);
      msg.hidden = false;
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
      }).then(async r => ({ ok: r.ok, json: await r.json().catch(() => ({})) }));
    }

    // Status
    document.getElementById('cm-status-form').addEventListener('submit', function (e) {
      e.preventDefault();
      const fd = new FormData();
      fd.append('_method', 'PATCH');
      fd.append('status', document.getElementById('cm-status').value);
      post(statusUrl.replace('_ID', currentId), fd).then(({ ok, json }) => {
        showMsg(json.message || (ok ? 'Saved.' : 'Failed.'), ok);
        document.getElementById('cm-status').value = json.data ? json.data.status : document.getElementById('cm-status').value;
        if (ok && typeof window.onComplaintChanged === 'function') window.onComplaintChanged();
      });
    });

    // Route
    document.getElementById('cm-route-form').addEventListener('submit', function (e) {
      e.preventDefault();
      const leaderId = document.getElementById('cm-leaders').value;
      if (!leaderId) { showMsg('Pick a team leader first.', false); return; }
      const fd = new FormData();
      fd.append('team_leader_id', leaderId);
      post(routeUrl.replace('_ID', currentId), fd).then(({ ok, json }) => {
        showMsg(json.message || (ok ? 'Routed.' : 'Failed.'), ok);
        if (ok && typeof window.onComplaintChanged === 'function') window.onComplaintChanged();
      });
    });

    // Resolve
    document.getElementById('cm-resolve-form').addEventListener('submit', function (e) {
      e.preventDefault();
      const fd = new FormData();
      fd.append('resolution_notes', document.getElementById('cm-notes').value);
      post(resolveUrl.replace('_ID', currentId), fd).then(({ ok, json }) => {
        showMsg(json.message || (ok ? 'Resolved.' : 'Failed.'), ok);
        if (ok && typeof window.onComplaintChanged === 'function') window.onComplaintChanged();
      });
    });
  })();
</script>
@endpush