@extends('layouts.app')
@section('title', 'Users')
@section('content')
  <div class="page-header">
    <h1>Users</h1>
    <p>Manage user accounts and their complaint history</p>
  </div>

@php
  $isAdmin = auth()->user()->isAdministrator();
  $isEngineer = auth()->user()->isEngineer();
  $dept = auth()->user()->department_team;
  $deptLabels = ['metering'=>'Metering','billing'=>'Billing','water_quality'=>'Water Quality','operations'=>'Operations'];
  // Engineers never see customer accounts, so the filter must not offer the
  // role — otherwise the list silently returns nothing.
  $roleOptions = $isEngineer
    ? ['administrator','engineer','offsite_staff']
    : ['customer','administrator','engineer','offsite_staff'];
@endphp

  <div class="grid {{ $isEngineer ? 'grid-cols-2' : 'grid-cols-3' }} gap-4">
    <div class="stat-card"><div><div class="stat-label">Total Users</div><div class="stat-value">{{ $stats['total'] }}</div></div></div>
    <div class="stat-card"><div><div class="stat-label">Active Users</div><div class="stat-value">{{ $stats['active'] }}</div></div></div>
    @unless($isEngineer)
      <div class="stat-card"><div><div class="stat-label">Customers</div><div class="stat-value">{{ $stats['by_role']['customer'] ?? 0 }}</div></div></div>
    @endunless
  </div>

  <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.75rem; margin-top:1rem;">
    <form method="GET" action="{{ route('users.index') }}" style="display:flex; gap:0.5rem; flex:1;">
      <input type="text" name="q" placeholder="Search users by name or email..." value="{{ request('q') }}"
             style="border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.4rem 0.75rem; font-size:0.85rem; background:#f8fafc; outline:none; max-width:280px;" />
      <select name="role" style="border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.4rem 0.75rem; font-size:0.85rem; background:#f8fafc;">
        <option value="">All Roles</option>
        @foreach($roleOptions as $r)
          <option value="{{ $r }}" @selected(request('role')===$r)>{{ ucwords(str_replace('_',' ',$r)) }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn-primary" style="padding:0.4rem 1rem;">Filter</button>
    </form>
    @if($isAdmin)
      <a href="{{ route('users.create') }}" class="btn-primary"><i data-lucide="user-plus"></i> Add User</a>
    @elseif($isEngineer)
      <span style="font-size:0.8rem; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.4rem 0.75rem;">
        <i data-lucide="info" style="width:0.9rem;height:0.9rem;"></i>
        @if($dept)
          Read-only · showing {{ $deptLabels[$dept] ?? $dept }} staff
        @else
          Read-only · no department assigned, so no users are listed
        @endif
      </span>
    @endif
  </div>

  <div class="table-wrap mt-3">
    <table>
      <thead>
        <tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Department</th><th>Actions</th></tr>
      </thead>
      <tbody>
        @forelse($users as $u)
          <tr>
            <td>{{ $u->full_name }} @if($u->is_team_leader)<small style="color:#2563eb;">(Team Leader)</small>@endif</td>
            <td>{{ $u->email }}</td>
            <td>{{ ucwords(str_replace('_',' ',$u->role)) }}</td>
            <td>
              @if($u->is_active)
                <span class="badge-pill badge-green">Active</span>
              @else
                <span class="badge-pill badge-gray">Inactive</span>
              @endif
            </td>
            <td>{{ $u->department_team ? ($deptLabels[$u->department_team] ?? $u->department_team) : '—' }}</td>
            <td>
              @if($isAdmin)
                <a href="{{ route('users.edit', $u->id) }}" class="action-icon" title="Edit"><i data-lucide="pencil"></i></a>
                <form method="POST" action="{{ route('users.deactivate', $u->id) }}" style="display:inline;">
                  @csrf @method('PATCH')
                  <button type="submit" class="action-icon" style="background:none;border:none;cursor:pointer;color:#94a3b8;" title="Toggle active">
                    <i data-lucide="{{ $u->is_active ? 'user-x' : 'user-check' }}"></i>
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" style="text-align:center; color:#94a3b8; padding:2rem;">
              @if($isEngineer && !$dept)
                No department is assigned to your account, so no users are listed. Ask an administrator to set one.
              @elseif($isEngineer)
                No {{ $deptLabels[$dept] ?? $dept }} staff found.
              @else
                No users yet.
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="display:flex; justify-content:flex-end; margin-top:0.75rem;">{!! $users->links() !!}</div>
@endsection
