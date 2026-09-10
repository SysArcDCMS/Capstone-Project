@extends('layouts.app')
@section('title', $user ? 'Edit User' : 'Add User')
@section('content')
  <a href="{{ route('users.index') }}" class="back-link"><i data-lucide="arrow-left"></i> Back to Users</a>
  <div class="page-header mt-3" style="margin-bottom:0.5rem;">
    <h1>{{ $user ? 'Edit User' : 'Add User' }}</h1>
    <p>{{ $user ? 'Update user details.' : 'Create a new account.' }}</p>
  </div>

  <div style="background:white; border-radius:1rem; padding:1.5rem; border:1px solid #f1f5f9;">
    @if($errors->any())
      <div style="background:#fee2e2;color:#991b1b;padding:0.6rem 0.9rem;border-radius:0.5rem;font-size:0.85rem;margin-bottom:1rem;">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
      </div>
    @endif

    <form method="POST" action="{{ $user ? route('users.update', $user->id) : route('users.store') }}">
      @csrf
      @if($user) @method('PUT') @endif
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Full Name</label>
          <input class="form-input" name="full_name" value="{{ old('full_name', $user->full_name ?? '') }}" required />
        </div>
        @if(!$user)
          <div>
            <label class="form-label">Email Address</label>
            <input class="form-input" type="email" name="email" value="{{ old('email') }}" required />
          </div>
          <div>
            <label class="form-label">Password</label>
            <input class="form-input" type="password" name="password" required />
          </div>
        @endif
        <div>
          <label class="form-label">Role</label>
          <select class="form-input" name="role" required>
            @foreach(['customer','administrator','engineer','offsite_staff'] as $r)
              <option value="{{ $r }}" @selected(old('role', $user->role ?? '')===$r)>{{ ucwords(str_replace('_',' ',$r)) }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="form-label">Contact Number</label>
          <input class="form-input" name="contact_no" value="{{ old('contact_no', $user->contact_no ?? '') }}" />
        </div>
        <div>
          <label class="form-label">Department</label>
          <select class="form-input" name="department_team">
            <option value="">No Department</option>
            @foreach(['metering','billing','water_quality','operations'] as $dept)
              <option value="{{ $dept }}" @selected(old('department_team', $user->department_team ?? '')===$dept)>{{ ucwords(str_replace('_',' ',$dept)) }}</option>
            @endforeach
          </select>
        </div>
        @if($user)
          <div>
            <label class="form-label">Status</label>
            <select class="form-input" name="is_active">
              <option value="1" @selected($user->is_active)>Active</option>
              <option value="0" @selected(!$user->is_active)>Inactive</option>
            </select>
          </div>
        @endif
        <div>
          <label class="form-label">Team Leader</label>
          <select class="form-input" name="is_team_leader">
            <option value="0" @selected(old('is_team_leader', $user->is_team_leader ?? false)==false)>No</option>
            <option value="1" @selected(old('is_team_leader', $user->is_team_leader ?? false)==true)>Yes</option>
          </select>
        </div>
      </div>

      <div style="display:flex; justify-content:center; margin-top:1rem; gap:0.5rem;">
        <a href="{{ route('users.index') }}" class="btn-outline">Cancel</a>
        <button type="submit" class="btn-dark"><i data-lucide="save"></i> {{ $user ? 'Update' : 'Create' }}</button>
      </div>
    </form>
  </div>
@endsection
