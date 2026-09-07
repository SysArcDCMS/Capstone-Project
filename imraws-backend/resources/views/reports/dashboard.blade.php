@extends('layouts.app')
@section('title', 'Reports')
@section('content')
  <div class="page-header">
    <h1>Reports & Analytics</h1>
    <p>System-wide incident trends, classification accuracy, and team performance</p>
  </div>

  @php $d = $data; @endphp

  <div class="grid grid-cols-3 gap-4">
    <div class="stat-card"><div><div class="stat-label">Total Incidents ({{ $d['window_days'] }}d)</div><div class="stat-value">{{ $d['incidents']['total'] }}</div></div></div>
    <div class="stat-card"><div><div class="stat-label">HITL Corrections</div><div class="stat-value">{{ $d['feedback']['corrections'] }}</div></div></div>
    <div class="stat-card"><div><div class="stat-label">Correction Rate</div><div class="stat-value">{{ round($d['feedback']['correction_rate']*100,1) }}%</div></div></div>
  </div>

  <div class="grid grid-cols-2 gap-4 mt-4">
    <div style="background:white; border-radius:1rem; padding:1.25rem; border:1px solid #f1f5f9;">
      <h3 style="font-weight:600; color:#1e293b; margin-bottom:0.75rem;">By Status</h3>
      <table><tbody>
        @foreach($d['incidents']['by_status'] as $k => $v)
          <tr><td>{{ ucwords(str_replace('_',' ',$k)) }}</td><td style="text-align:right;font-weight:600;">{{ $v }}</td></tr>
        @endforeach
      </tbody></table>
    </div>
    <div style="background:white; border-radius:1rem; padding:1.25rem; border:1px solid #f1f5f9;">
      <h3 style="font-weight:600; color:#1e293b; margin-bottom:0.75rem;">Avg Resolution (hours) by Dept</h3>
      <table><tbody>
        @forelse($d['resolution']['avg_hours_by_department'] as $r)
          <tr><td>{{ ucwords(str_replace('_',' ',$r['department_team'] ?? '—')) }}</td><td style="text-align:right;font-weight:600;">{{ $r['avg_hours'] }}h ({{ $r['total_resolved'] }} resolved)</td></tr>
        @empty
          <tr><td colspan="2" style="color:#94a3b8;">No resolved incidents yet.</td></tr>
        @endforelse
      </tbody></table>
    </div>
  </div>

  <div style="background:white; border-radius:1rem; padding:1.25rem; border:1px solid #f1f5f9; margin-top:1rem;">
    <h3 style="font-weight:600; color:#1e293b; margin-bottom:0.75rem;">Top Misclassification Patterns</h3>
    <table><thead><tr><th>From</th><th>To</th><th style="text-align:right;">Count</th></tr></thead><tbody>
      @forelse($d['feedback']['top_correction_pairs'] as $p)
        <tr><td>{{ $p->original_category }}</td><td>{{ $p->corrected_category }}</td><td style="text-align:right;">{{ $p->count }}</td></tr>
      @empty
        <tr><td colspan="3" style="color:#94a3b8;text-align:center;padding:1rem;">No corrections logged yet. Test the HITL flow by submitting a complaint and correcting it as a Team Leader.</td></tr>
      @endforelse
    </tbody></table>
  </div>
@endsection
