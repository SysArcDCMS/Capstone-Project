@extends('layouts.app')
@section('title', 'Categories')
@section('content')
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
    <div class="page-header" style="margin-bottom:0;">
      <h1>Categories</h1>
      <p>Manage complaint categories and their properties</p>
    </div>
    <button class="btn-primary" style="border-radius:9999px;" disabled title="Coming soon"><i data-lucide="plus"></i> Add Category</button>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div class="stat-card"><div><div class="stat-label">Total Categories</div><div class="stat-value">4</div></div></div>
    <div class="stat-card"><div><div class="stat-label">Total Complaints</div><div class="stat-value">{{ $incidentCount ?? 0 }}</div></div></div>
  </div>

  <div class="grid grid-cols-2 gap-4 mt-4">
    @php
      $cards = [
        ['Metering Issue',    '#3b82f6', '#bfdbfe', 'Problems with meter reading or damage.', $counts['Metering'] ?? 0],
        ['Billing Issue',     '#8b5cf6', '#c4b5fd', 'Errors or concerns about bills and payments.', $counts['Billing'] ?? 0],
        ['Water Quality Concern','#10b981','#a7f3d0','Dirty, smelly, or unsafe water.', $counts['Water Quality'] ?? 0],
        ['Operations Issue',  '#f87171', '#fca5a5', 'Supply issues like no water, leaks, or low pressure.', $counts['Operations'] ?? 0],
      ];
      $dots = ['dot-blue','dot-purple','dot-green','dot-coral'];
    @endphp
    @foreach($cards as $i => $c)
      <div class="category-card">
        <div class="card-header">
          <div class="title-wrap"><span style="color:{{ $c[1] }};"><i data-lucide="folder"></i></span> {{ $c[0] }}</div>
          <div>
            <span class="action-icon"><i data-lucide="pencil"></i></span>
            <span class="action-icon"><i data-lucide="trash-2"></i></span>
          </div>
        </div>
        <div class="card-desc">{{ $c[3] }}</div>
        <div class="card-footer"><span>{{ $c[4] }} Complaints</span> <span class="category-dot {{ $dots[$i] }}"></span></div>
      </div>
    @endforeach
  </div>
@endsection
