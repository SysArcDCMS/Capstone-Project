@extends('layouts.app')
@section('title', 'Settings')
@section('content')
  <div class="page-header">
    <h1>Settings</h1>
    <p>Manage your account and system preferences</p>
  </div>

  @php $user = auth()->user(); @endphp

  <div class="settings-tabs">
    <button class="settings-tab {{ $tab==='profile'?'active':'' }}" data-target="profile"><i data-lucide="user"></i> Profile</button>
    <button class="settings-tab {{ $tab==='notification'?'active':'' }}" data-target="notification"><i data-lucide="bell"></i> Notification</button>
    <button class="settings-tab {{ $tab==='security'?'active':'' }}" data-target="security"><i data-lucide="shield-check"></i> Security</button>
    <button class="settings-tab {{ $tab==='email'?'active':'' }}" data-target="email"><i data-lucide="mail"></i> Email</button>
    @if($user->isAdministrator())
      <button class="settings-tab {{ $tab==='system'?'active':'' }}" data-target="system"><i data-lucide="settings"></i> System</button>
    @endif
  </div>

  @if(session('success'))
    <div style="background:#dcfce7;color:#166534;padding:0.6rem 0.9rem;border-radius:0.5rem;font-size:0.85rem;margin-bottom:1rem;">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div style="background:#fee2e2;color:#991b1b;padding:0.6rem 0.9rem;border-radius:0.5rem;font-size:0.85rem;margin-bottom:1rem;">{{ session('error') }}</div>
  @endif

  {{-- ──────── PROFILE ──────── --}}
  <div id="panel-profile" class="settings-panel {{ $tab==='profile'?'active':'' }}">
    <div style="background:white;border-radius:1rem;padding:1.5rem;border:1px solid #f1f5f9;">
      <h3 style="font-weight:600;color:#1e293b;margin-bottom:1rem;">Profile Information</h3>
      <div style="display:flex;align-items:center;gap:1rem;">
        <div style="width:4rem;height:4rem;background:#bfdbfe;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#1e4d8c;">{{ strtoupper(substr($user->full_name,0,1)) }}</div>
        <div style="font-size:0.85rem;color:#64748b;">Profile photo upload is reserved for the Flutter mobile app.</div>
      </div>
      <form method="POST" action="{{ route('settings.profile', ['tab' => 'profile']) }}">
        @csrf @method('PATCH')
        <div class="grid grid-cols-2 gap-4 mt-4">
          <div>
            <label class="form-label">Full Name</label>
            <input class="form-input" name="full_name" value="{{ old('full_name', $user->full_name) }}" />
            @error('full_name') <span style="color:#ef4444;font-size:0.75rem;display:block;">{{ $message }}</span> @enderror
          </div>
          <div>
            <label class="form-label">Email Address</label>
            <input class="form-input" value="{{ $user->email }}" disabled />
          </div>
          <div>
            <label class="form-label">Role</label>
            <input class="form-input" value="{{ ucwords(str_replace('_',' ',$user->role)) }}" disabled />
          </div>
          <div>
            <label class="form-label">Phone Number</label>
            <input class="form-input" name="contact_no" value="{{ old('contact_no', $user->contact_no) }}" />
          </div>
          <div style="grid-column:span 2;">
            <label class="form-label">Address</label>
            <textarea class="form-input" name="address" rows="2">{{ old('address', $user->address) }}</textarea>
          </div>
        </div>
        <div style="display:flex;justify-content:center;margin-top:1rem;"><button type="submit" class="btn-dark"><i data-lucide="save"></i> Save Profile</button></div>
      </form>
    </div>
  </div>

  {{-- ──────── NOTIFICATIONS ──────── --}}
  <div id="panel-notification" class="settings-panel {{ $tab==='notification'?'active':'' }}">
    <div style="background:white;border-radius:1rem;padding:1.5rem;border:1px solid #f1f5f9;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h3 style="font-weight:600;color:#1e293b;">Your Notifications</h3>
        @if($unreadCount > 0)
          <form method="POST" action="{{ route('settings.notifications.markRead', ['tab' => 'notification']) }}">
            @csrf
            <button type="submit" class="btn-outline" style="font-size:0.8rem;"><i data-lucide="check-check"></i> Mark all read ({{ $unreadCount }} unread)</button>
          </form>
        @else
          <span class="badge-pill badge-green">All caught up</span>
        @endif
      </div>

      @forelse($notifications as $n)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.65rem 0;border-bottom:1px solid #f1f5f9;{{ !$n->is_read ? 'background:#f8fafc;border-radius:0.5rem;padding-left:0.5rem;padding-right:0.5rem;' : '' }}">
          <div>
            <span style="font-size:0.85rem;color:#1e293b;{{ !$n->is_read ? 'font-weight:600;' : '' }}">{{ $n->message }}</span>
            <div style="font-size:0.75rem;color:#64748b;margin-top:0.1rem;">{{ $n->created_at->diffForHumans() }} @if($n->incident_id) · Incident #{{ $n->incident_id }} @endif</div>
          </div>
          <form method="POST" action="{{ route('settings.notifications.update', $n->id) }}" style="margin-left:1rem;">
            @csrf @method('PATCH')
            <button type="submit" class="action-icon" style="cursor:pointer;background:none;border:none;padding:0;" title="{{ $n->is_read ? 'Mark unread' : 'Mark read' }}">
              <i data-lucide="{{ $n->is_read ? 'mail' : 'mail-open' }}"></i>
            </button>
          </form>
        </div>
      @empty
        <div style="text-align:center;padding:2rem;color:#94a3b8;">No notifications yet.</div>
      @endforelse

      <div style="margin-top:0.75rem;">{{ $notifications->links() }}</div>
    </div>
  </div>

  {{-- ──────── SECURITY ──────── --}}
  <div id="panel-security" class="settings-panel {{ $tab==='security'?'active':'' }}">
    <div style="background:white;border-radius:1rem;padding:1.5rem;border:1px solid #f1f5f9;">
      <h3 style="font-weight:600;color:#1e293b;margin-bottom:1rem;">Change Password</h3>
      <form method="POST" action="{{ route('settings.security', ['tab' => 'security']) }}">
        @csrf @method('PATCH')
        <div style="max-width:28rem;">
          <div class="mb-3">
            <label class="form-label">Current Password <span style="color:#ef4444">*</span></label>
            <input type="password" class="form-input" name="current_password" required autocomplete="current-password" />
            @error('current_password') <span style="color:#ef4444;font-size:0.75rem;display:block;">{{ $message }}</span> @enderror
          </div>
          <div class="mb-3">
            <label class="form-label">New Password <span style="color:#ef4444">*</span></label>
            <input type="password" class="form-input" name="password" required autocomplete="new-password" minlength="8" />
            @error('password') <span style="color:#ef4444;font-size:0.75rem;display:block;">{{ $message }}</span> @enderror
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password <span style="color:#ef4444">*</span></label>
            <input type="password" class="form-input" name="password_confirmation" required autocomplete="new-password" />
          </div>
        </div>
        <div style="display:flex;justify-content:center;margin-top:1rem;"><button type="submit" class="btn-dark"><i data-lucide="save"></i> Update Password</button></div>
      </form>
    </div>
  </div>

  {{-- ──────── EMAIL ──────── --}}
  <div id="panel-email" class="settings-panel {{ $tab==='email'?'active':'' }}">
    <div style="background:white;border-radius:1rem;padding:1.5rem;border:1px solid #f1f5f9;">
      <h3 style="font-weight:600;color:#1e293b;margin-bottom:1rem;">Change Email Address</h3>
      <form method="POST" action="{{ route('settings.email', ['tab' => 'email']) }}">
        @csrf @method('PATCH')
        <div style="max-width:28rem;">
          <div class="mb-3">
            <label class="form-label">Current Email</label>
            <input class="form-input" value="{{ $user->email }}" disabled />
          </div>
          <div class="mb-3">
            <label class="form-label">New Email <span style="color:#ef4444">*</span></label>
            <input type="email" class="form-input" name="email" value="{{ old('email', $user->email) }}" required />
            @error('email') <span style="color:#ef4444;font-size:0.75rem;display:block;">{{ $message }}</span> @enderror
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm Current Password <span style="color:#ef4444">*</span></label>
            <input type="password" class="form-input" name="current_password" required autocomplete="current-password" />
            @error('current_password') <span style="color:#ef4444;font-size:0.75rem;display:block;">{{ $message }}</span> @enderror
          </div>
        </div>
        <div style="display:flex;justify-content:center;margin-top:1rem;"><button type="submit" class="btn-dark"><i data-lucide="save"></i> Update Email</button></div>
      </form>
    </div>
  </div>

  {{-- ──────── SYSTEM (admin-only) ──────── --}}
  @if($user->isAdministrator())
    <div id="panel-system" class="settings-panel {{ $tab==='system'?'active':'' }}">
      <div class="grid grid-cols-2 gap-4">
        <div style="background:white;border-radius:1rem;padding:1.25rem;border:1px solid #f1f5f9;">
          <h3 style="font-weight:600;color:#1e293b;margin-bottom:0.75rem;">NLP Service Status</h3>
          @if($nlpHealth && ($nlpHealth['status'] ?? null) === 'online')
            <span class="badge-pill badge-green" style="margin-bottom:0.5rem;">Online</span>
            <div style="font-size:0.85rem;color:#475569;margin-top:0.5rem;line-height:1.6;">
              Service: {{ $nlpHealth['service'] ?? '—' }}<br>
              Queue size: {{ $nlpHealth['queue_size'] ?? 0 }} · Results cached: {{ $nlpHealth['results_count'] ?? 0 }}<br>
              Checked: {{ $nlpHealth['timestamp'] ?? now()->toDateTimeString() }}
            </div>
          @else
            <span class="badge-pill badge-red" style="margin-bottom:0.5rem;">Offline</span>
            <div style="font-size:0.85rem;color:#94a3b8;margin-top:0.5rem;">NLP service at <code>{{ $nlpUrl }}</code> is not reachable.</div>
          @endif
        </div>

        <div style="background:white;border-radius:1rem;padding:1.25rem;border:1px solid #f1f5f9;">
          <h3 style="font-weight:600;color:#1e293b;margin-bottom:0.75rem;">Runtime Versions</h3>
          <div style="font-size:0.85rem;color:#475569;line-height:1.8;">
            PHP: <strong>{{ $phpVersion }}</strong><br>
            Laravel: <strong>{{ $laravelVersion }}</strong><br>
            PostgreSQL: <strong>{{ $pgVersion }}</strong><br>
            Environment: <span class="badge-pill badge-gray">{{ ucfirst($appEnv) }}</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4 mt-4">
        <div style="background:white;border-radius:1rem;padding:1.25rem;border:1px solid #f1f5f9;">
          <h3 style="font-weight:600;color:#1e293b;margin-bottom:0.75rem;">Retrain AI Model</h3>
          <p style="font-size:0.85rem;color:#64748b;margin-bottom:0.75rem;">Trigger a background retraining of the SVM classifier using the accumulated HITL correction data in tbl_feedback.</p>
          <form method="POST" action="{{ route('settings.system.retrain') }}" onsubmit="return confirm('Start AI retraining? This runs in the background and may take a few minutes.')">
            @csrf
            <button type="submit" class="btn-dark"><i data-lucide="brain"></i> Retrain Now</button>
          </form>
        </div>

        <div style="background:white;border-radius:1rem;padding:1.25rem;border:1px solid #f1f5f9;">
          <h3 style="font-weight:600;color:#1e293b;margin-bottom:0.75rem;">Severity Configuration</h3>
          @if($nlpSeverityConfig)
            <div style="font-size:0.85rem;color:#475569;line-height:1.8;">
              @foreach($nlpSeverityConfig as $key => $value)
                @if($key !== 'status')
                  <strong>{{ ucwords(str_replace('_',' ',$key)) }}:</strong>
                  @if(is_array($value))
                    {{ collect($value)->map(fn($v,$k)=>"$k: $v")->implode(', ') }}
                  @else
                    {{ $value }}
                  @endif
                  <br>
                @endif
              @endforeach
            </div>
          @else
            <span class="badge-pill badge-gray">NLP service offline — config unavailable</span>
          @endif
        </div>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('.settings-tab').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.settings-tab').forEach(function(b) { b.classList.remove('active'); });
    document.querySelectorAll('.settings-panel').forEach(function(p) { p.classList.remove('active'); });
    btn.classList.add('active');
    var panel = document.getElementById('panel-' + btn.dataset.target);
    if (panel) panel.classList.add('active');
  });
});
</script>
@endpush