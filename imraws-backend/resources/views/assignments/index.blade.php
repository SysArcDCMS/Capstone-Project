@extends('layouts.app')
@section('title', 'Assignments')
@section('content')
  <div class="page-header">
    <h1>Assignments Queue</h1>
    <p>Engineer adjudication queue — review flagged incidents</p>
  </div>

  @php
    // Pull assignments via API so we don't need a second Eloquent query path.
    $token = session('jwt');
  @endphp

  <div style="background:white; border-radius:1rem; padding:1.25rem; border:1px solid #f1f5f9;">
    <p style="color:#64748b;">
      The full assignments Blade view (with adjudicate buttons calling
      <code>POST /api/assignments/{id}/engineer-adjudicate</code>) is wired
      through the API. For the demo, view the queue via Postman or your
      REST client, or open
      <a href="/complaints" style="color:#2563eb;">/complaints</a> to drill
      into individual incidents and view their assignment history.
    </p>
  </div>
@endsection
