@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
  <div class="page-header">
    <h1>Dashboard Overview</h1>
    <p>Monitor and manage complaint statistics and trends</p>
  </div>

  @php
    /** @var array $data */
    $inc = $data['incidents'];
    $sev = $inc['by_severity'] ?? [];
    $cat = $inc['by_category'] ?? [];
  @endphp

  <!-- Stats Row 1 -->
  <div class="grid grid-cols-4 gap-4">
    <div class="stat-card">
      <div><div class="stat-label">Total Complaints</div><div class="stat-value">{{ $inc['total'] }}</div></div>
      <span class="stat-icon" style="color:#3b82f6"><i data-lucide="info"></i></span>
    </div>
    <div class="stat-card">
      <div><div class="stat-label">Open</div><div class="stat-value">{{ $sev['open'] ?? ($inc['by_status']['open'] ?? 0) }}</div></div>
      <span class="stat-icon" style="color:#ef4444"><i data-lucide="clock"></i></span>
    </div>
    <div class="stat-card">
      <div><div class="stat-label">In Progress</div><div class="stat-value">{{ $inc['by_status']['in_progress'] ?? 0 }}</div></div>
      <span class="stat-icon" style="color:#f59e0b"><i data-lucide="trending-up"></i></span>
    </div>
    <div class="stat-card">
      <div><div class="stat-label">Resolved</div><div class="stat-value">{{ $inc['by_status']['resolved'] ?? 0 }}</div></div>
      <span class="stat-icon" style="color:#10b981"><i data-lucide="check-circle"></i></span>
    </div>
  </div>

  <!-- Stats Row 2 -->
  <div class="grid grid-cols-3 gap-4 mt-4">
    <div class="stat-card">
      <div><div class="stat-label">High Priority</div><div class="stat-value">{{ $inc['by_severity']['High'] ?? 0 }}</div></div>
      <span class="stat-icon" style="color:#ef4444"><i data-lucide="alert-triangle"></i></span>
    </div>
    <div class="stat-card">
      <div><div class="stat-label">Avg Resolution</div>
        <div class="stat-value">
          @if(!empty($data['resolution']['avg_hours_by_department']))
            {{ number_format(collect($data['resolution']['avg_hours_by_department'])->avg('avg_hours'), 1) }}h
          @else
            N/A
          @endif
        </div>
      </div>
      <span class="stat-icon" style="color:#3b82f6"><i data-lucide="clock"></i></span>
    </div>
    <div class="stat-card">
      <div><div class="stat-label">HITL Correction Rate</div>
        <div class="stat-value">{{ round(($data['feedback']['correction_rate'] ?? 0) * 100, 1) }}%</div>
      </div>
      <span class="stat-icon" style="color:#8b5cf6"><i data-lucide="git-pull-request"></i></span>
    </div>
  </div>

  <!-- Top categories bar chart -->
  <div class="mt-6" style="background:white; border-radius:1rem; padding:1.25rem; border:1px solid #f1f5f9;">
    <h3 style="font-weight:600; color:#1e293b; margin-bottom:0.75rem;">Top Complaint Categories</h3>
    <div style="display:flex; align-items:flex-end; height:130px; gap:1.25rem;">
      @php $maxCat = max($cat ?: ['x' => 1]); @endphp
      @foreach(['Metering Issue' => 'dot-blue', 'Billing Issue' => 'dot-purple', 'Water Quality Concern' => 'dot-green', 'Operations Issue' => 'dot-coral'] as $label => $color)
        <div style="flex:1; display:flex; flex-direction:column; align-items:center;">
          <div class="chart-bar" style="height:{{ max(20, (($cat[$label] ?? 0) / $maxCat) * 100) }}px; background:{{ $color === 'dot-blue' ? '#bfdbfe' : ($color === 'dot-purple' ? '#c4b5fd' : ($color === 'dot-green' ? '#a7f3d0' : '#fca5a5')) }};"></div>
          <span style="font-size:0.7rem; color:#64748b; margin-top:0.25rem;">{{ $label }} ({{ $cat[$label] ?? 0 }})</span>
        </div>
      @endforeach
    </div>
  </div>
@endsection
