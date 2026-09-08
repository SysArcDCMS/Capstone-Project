@extends('layouts.app')
@section('title', 'Complaint #'.str_pad($incident->id,3,'0',STR_PAD_LEFT))
@section('content')
  <a href="{{ route('complaints.index') }}" class="back-link"><i data-lucide="arrow-left"></i> Back to Complaints</a>
  <div class="page-header mt-3" style="margin-bottom:0.5rem;">
    <h1>Complaint #{{ str_pad($incident->id,3,'0',STR_PAD_LEFT) }}</h1>
    <p>{{ $incident->customer->full_name ?? 'Unknown' }} · {{ $incident->submitted_at?->format('M d, Y') }}</p>
  </div>

  <div class="grid grid-cols-2 gap-4 mt-3">
    <div class="stat-card"><div><div class="stat-label">Category</div><div class="stat-value" style="font-size:1.2rem;">{{ $incident->category ?? '—' }}</div></div></div>
    <div class="stat-card"><div><div class="stat-label">Severity</div><div class="stat-value" style="font-size:1.2rem;">{{ $incident->severity ?? '—' }} <small style="font-size:0.7rem;color:#64748b;">(composite {{ $incident->composite_score ?? '—' }})</small></div></div></div>
    <div class="stat-card"><div><div class="stat-label">Status</div><div class="stat-value" style="font-size:1.2rem;"><span class="badge-pill {{ ['open'=>'badge-red','assigned'=>'badge-blue','in_progress'=>'badge-orange','resolved'=>'badge-green','rejected'=>'badge-gray'][$incident->status] ?? 'badge-gray' }}">{{ ucwords(str_replace('_',' ',$incident->status)) }}</span></div></div></div>
    <div class="stat-card"><div><div class="stat-label">Location</div><div class="stat-value" style="font-size:1.1rem;">{{ $incident->location ?? '—' }}</div></div></div>
  </div>

  <div style="background:white; border-radius:1rem; padding:1.25rem; border:1px solid #f1f5f9; margin-top:1rem;">
    <h3 style="font-weight:600; color:#1e293b; margin-bottom:0.5rem;">Description</h3>
    <p style="color:#475569;">{{ $incident->description }}</p>
  </div>

  <div style="background:white; border-radius:1rem; padding:1.25rem; border:1px solid #f1f5f9; margin-top:1rem;">
    <h3 style="font-weight:600; color:#1e293b; margin-bottom:0.5rem;">Assignments & Feedback</h3>
    @forelse($incident->assignments as $a)
      <div style="border-top:1px solid #f1f5f9; padding:0.5rem 0;">
        <strong>{{ $a->teamLeader->full_name ?? '—' }}</strong>
        <span class="badge-pill badge-gray" style="margin-left:0.5rem;">{{ $a->action_status }}</span>
        <small style="color:#94a3b8;">· {{ $a->assigned_at?->format('M d Y H:i') }}</small>
      </div>
    @empty
      <p style="color:#94a3b8;">No assignments yet.</p>
    @endforelse
  </div>

  <div style="background:white; border-radius:1rem; padding:1.25rem; border:1px solid #f1f5f9; margin-top:1rem;">
    <h3 style="font-weight:600; color:#1e293b; margin-bottom:0.5rem;">Photo Proof</h3>
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
      @forelse($incident->attachments as $att)
        <a href="{{ $att->url }}" target="_blank" style="display:block;border:1px solid #f1f5f9;padding:0.25rem;border-radius:0.5rem;text-decoration:none;color:#475569;font-size:0.85rem;">
          📎 {{ $att->original_name }}
        </a>
      @empty
        <p style="color:#94a3b8;">No attachments uploaded.</p>
      @endforelse
    </div>
  </div>
@endsection
