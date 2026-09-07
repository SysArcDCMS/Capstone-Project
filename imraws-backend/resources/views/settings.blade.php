@extends('layouts.app')
@section('title', 'Settings')
@section('content')
  <div class="page-header">
    <h1>Settings</h1>
    <p>Manage your account and system preferences</p>
  </div>

  <div class="settings-tabs">
    <button class="settings-tab active"><i data-lucide="user"></i> Profile</button>
    <button class="settings-tab"><i data-lucide="bell"></i> Notification</button>
    <button class="settings-tab"><i data-lucide="shield-check"></i> Security</button>
    <button class="settings-tab"><i data-lucide="mail"></i> Email</button>
    <button class="settings-tab"><i data-lucide="settings"></i> System</button>
  </div>

  @if(session('success'))
    <div style="background:#dcfce7;color:#166534;padding:0.6rem 0.9rem;border-radius:0.5rem;font-size:0.85rem;margin-bottom:1rem;">
      {{ session('success') }}
    </div>
  @endif

  <div style="background:white; border-radius:1rem; padding:1.5rem; border:1px solid #f1f5f9;">
    <h3 style="font-weight:600; color:#1e293b; margin-bottom:1rem;">Profile Information</h3>
    <div style="display:flex; align-items:center; gap:1rem;">
      <div style="width:4rem; height:4rem; background:#bfdbfe; border-radius:9999px; display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:#1e4d8c;">
        {{ strtoupper(substr($user->full_name,0,1)) }}
      </div>
      <div>
        <div style="font-size:0.85rem;color:#64748b;">Profile photo upload is reserved for the Flutter mobile app.</div>
      </div>
    </div>

    <form method="POST" action="{{ route('settings.profile') }}">
      @csrf @method('PATCH')
      <div class="grid grid-cols-2 gap-4 mt-4">
        <div>
          <label class="form-label">Full Name</label>
          <input class="form-input" name="full_name" value="{{ old('full_name', $user->full_name) }}" />
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
        <div style="grid-column: span 2;">
          <label class="form-label">Address</label>
          <textarea class="form-input" name="address" rows="2">{{ old('address', $user->address) }}</textarea>
        </div>
      </div>

      <div style="display:flex; justify-content:center; margin-top:1rem;">
        <button type="submit" class="btn-dark"><i data-lucide="save"></i> Save Profile</button>
      </div>
    </form>
  </div>
@endsection
