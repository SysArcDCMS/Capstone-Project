@extends('layouts.app')
@section('title', 'Complaints')
@section('content')
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
    <div class="page-header" style="margin-bottom:0;">
      <h1>Complaints</h1>
      <p>Manage and track all user complaints</p>
    </div>
    <div style="display:flex; gap:0.5rem;">
      <a href="{{ route('complaints.export') }}" class="btn-outline"><i data-lucide="download"></i> Export</a>
    </div>
  </div>

  <!-- Filter Bar -->
  <form method="GET" action="{{ route('complaints.index') }}" class="filter-bar">
    <input type="text" name="q" placeholder="Search by ID, subject, or user..." value="{{ request('q') }}" />
    <select name="status">
      <option value="">All Statuses</option>
      @foreach(['open','assigned','in_progress','resolved','rejected'] as $s)
        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
      @endforeach
    </select>
    <select name="severity">
      <option value="">All Priorities</option>
      @foreach(['High','Medium','Low'] as $s)
        <option value="{{ $s }}" @selected(request('severity')===$s)>{{ $s }}</option>
      @endforeach
    </select>
    <select name="category">
      <option value="">All Categories</option>
      @foreach(['Billing','Water Quality','Metering','Operations'] as $c)
        <option value="{{ $c }}" @selected(request('category')===$c)>{{ $c }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn-primary" style="padding:0.4rem 1rem;">Filter</button>
    <span style="font-size:0.85rem; color:#64748b; margin-left:auto;">
      Showing {{ $complaints->count() }} of {{ $complaints->total() }} complaints
    </span>
  </form>

  <!-- Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th><th>User</th><th>Category</th><th>Subject</th>
          <th>Status</th><th>Priority</th><th>Assigned To</th><th>Date</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($complaints as $c)
          @php
            $badge = [
              'open' => 'badge-red', 'assigned' => 'badge-blue',
              'in_progress' => 'badge-orange',
              'resolved' => 'badge-green', 'rejected' => 'badge-gray',
            ][$c->status] ?? 'badge-gray';
            $sevBadge = ['High'=>'badge-red','Medium'=>'badge-yellow','Low'=>'badge-blue'][$c->severity] ?? 'badge-gray';
            $dot = ['Billing'=>'dot-purple','Water Quality'=>'dot-green','Metering'=>'dot-blue','Operations'=>'dot-coral'][$c->category] ?? 'dot-blue';
            $tl = $c->assignments->where('action_status','!=','reassign')->sortByDesc('assigned_at')->first();
          @endphp
          <tr>
            <td>C{{ str_pad($c->id, 3, '0', STR_PAD_LEFT) }}</td>
            <td>{{ $c->customer->full_name ?? '—' }}</td>
            <td><span class="category-dot {{ $dot }}"></span>{{ $c->category ?? '—' }}</td>
            <td>{{ \Illuminate\Support\Str::limit($c->description, 50) }}</td>
            <td><span class="badge-pill {{ $badge }}">{{ ucwords(str_replace('_',' ',$c->status)) }}</span></td>
            <td><span class="badge-pill {{ $sevBadge }}">{{ $c->severity ?? '—' }}</span></td>
            <td>{{ $tl && $tl->teamLeader ? 'Engr. '.$tl->teamLeader->full_name : '—' }}</td>
            <td>{{ $c->submitted_at?->diffForHumans() ?? '—' }}</td>
            <td>
              <a href="{{ route('complaints.show', $c->id) }}" class="action-icon" title="View"><i data-lucide="eye"></i></a>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" style="text-align:center; color:#94a3b8; padding:2rem;">No complaints yet. Submit one via the mobile app to test the pipeline.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="display:flex; justify-content:flex-end; margin-top:0.75rem;">{!! $complaints->links() !!}</div>
@endsection
