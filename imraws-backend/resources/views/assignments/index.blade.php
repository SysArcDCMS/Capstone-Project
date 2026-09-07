@extends('layouts.app')
@section('title', 'Assignments')
@section('content')
  <div class="page-header">
    <h1>Assignments Queue</h1>
    <p>Engineer adjudication queue — review flagged incidents</p>
  </div>

  <div class="table-wrap mt-3">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Incident</th>
          <th>Category</th>
          <th>Severity</th>
          <th>Team Leader</th>
          <th>Status</th>
          <th>Assigned At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($assignments as $a)
          @php
            $badge = [
              'pending' => 'badge-gray', 'assigned' => 'badge-blue',
              'accept' => 'badge-green', 'reject' => 'badge-red',
              'correct' => 'badge-yellow', 'override' => 'badge-orange',
              'reassign' => 'badge-gray', 'in_progress' => 'badge-orange',
              'resolved' => 'badge-green',
            ][$a->action_status] ?? 'badge-gray';
          @endphp
          <tr>
            <td>#{{ $a->id }}</td>
            <td>#{{ $a->incident_id }} — {{ \Illuminate\Support\Str::limit($a->incident->description ?? '', 40) }}</td>
            <td>{{ $a->incident->category ?? '—' }}</td>
            <td>{{ $a->incident->severity ?? '—' }}</td>
            <td>{{ $a->teamLeader->full_name ?? '—' }}</td>
            <td><span class="badge-pill {{ $badge }}">{{ ucwords(str_replace('_',' ',$a->action_status)) }}</span></td>
            <td>{{ $a->assigned_at?->format('M d H:i') ?? '—' }}</td>
            <td>
              <a href="{{ route('complaints.show', $a->incident_id) }}" class="action-icon" title="View incident"><i data-lucide="eye"></i></a>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">No assignments yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:flex-end;margin-top:0.75rem;">{!! $assignments->links() !!}</div>
@endsection
