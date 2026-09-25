<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'Dashboard') · IMRAWS-NLP</title>
  <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
  <script src="https://unpkg.com/lucide@latest"></script>
  @stack('styles')
</head>
<body>
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span class="logo-icon">💧</span>
      <span>IMRAWS-NLP {{ auth()->user()->roleLabel() }}</span>
    </div>
    <nav class="sidebar-nav">
      <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <i data-lucide="layout-dashboard"></i> Dashboard
      </a>
      <a href="{{ route('complaints.index') }}" class="nav-item {{ request()->routeIs('complaints.*') ? 'active' : '' }}">
        <i data-lucide="file-text"></i> Complaints
      </a>
      @if(auth()->user()->isAdministrator() || auth()->user()->isEngineer())
        <a href="{{ route('assignments.index') }}" class="nav-item {{ request()->routeIs('assignments.*') ? 'active' : '' }}">
          <i data-lucide="clipboard-list"></i> Assignments
        </a>
      @endif
      @if(auth()->user()->isAdministrator() || auth()->user()->isEngineer())
        <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
          <i data-lucide="users"></i> Users
        </a>
      @endif
      @if(auth()->user()->isAdministrator())
        <a href="{{ route('categories.index') }}" class="nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
          <i data-lucide="folder"></i> Categories
        </a>
      @endif
      @if(auth()->user()->isAdministrator() || auth()->user()->isEngineer())
        <a href="{{ route('reports.dashboard') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
          <i data-lucide="bar-chart-2"></i> Reports
        </a>
      @endif
      <a href="{{ route('settings') }}" class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}">
        <i data-lucide="settings"></i> Settings
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="avatar-sm">{{ strtoupper(substr(auth()->user()->full_name ?? 'U', 0, 1)) }}{{ strtoupper(substr(auth()->user()->full_name ?? '', 1, 1)) }}</div>
      <div class="user-meta">
        <div class="name">{{ auth()->user()->full_name }}</div>
        <div class="role">{{ auth()->user()->roleLabel() }}</div>
      </div>
      <form method="POST" action="{{ route('logout') }}" style="display:inline;">
        @csrf
        <button type="submit" class="logout-link" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:0;" title="Sign out">
          <i data-lucide="log-out"></i>
        </button>
      </form>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main-wrap">
    @yield('content')
  </main>

  <script>
    lucide.createIcons();
    // Toggle switches
    document.querySelectorAll('.toggle-track').forEach(el => {
      el.addEventListener('click', function () { this.classList.toggle('on'); });
    });

    // Category color dropdowns (shared across portal)
    (function () {
      const NEUTRAL = '#cbd5e1';

      function bind(root) {
        root.querySelectorAll('.cat-dd').forEach(function (dd) {
          if (dd.dataset.catInit) return;
          dd.dataset.catInit = '1';
          const toggle = dd.querySelector('[data-cat-dd-toggle]');
          const hidden = dd.querySelector('[data-cat-dd-value]');
          const dot = dd.querySelector('[data-cat-dd-toggle] .cat-dd-dot');
          const text = dd.querySelector('[data-cat-dd-text]');

          function select(value, color, label) {
            if (hidden) hidden.value = value;
            if (dot) dot.style.background = color;
            if (text) text.textContent = label;
            dd.querySelectorAll('.cat-dd-row').forEach(function (r) {
              r.classList.toggle('is-selected', String(r.dataset.value || '') === String(value));
            });
            dd.classList.remove('open');
            if (typeof window.catDDChanged === 'function') {
              window.catDDChanged(dd, value, color, label);
            }
          }

          toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.cat-dd.open').forEach(function (o) { if (o !== dd) o.classList.remove('open'); });
            dd.classList.toggle('open');
          });

          dd.addEventListener('click', function (e) {
            const row = e.target.closest('.cat-dd-row');
            if (!row) return;
            select(row.dataset.value || '', row.dataset.color || NEUTRAL, row.textContent.trim());
          });
        });
      }

      document.addEventListener('click', function (e) {
        if (!e.target.closest('.cat-dd')) {
          document.querySelectorAll('.cat-dd.open').forEach(function (o) { o.classList.remove('open'); });
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          document.querySelectorAll('.cat-dd.open').forEach(function (o) { o.classList.remove('open'); });
        }
      });
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bind(document); });
      } else {
        bind(document);
      }

      window.catDDSetOptions = function (ddOrId, options, selected, placeholder) {
        const dd = typeof ddOrId === 'string' ? document.getElementById(ddOrId) : ddOrId;
        if (!dd) return;
        const panel = dd.querySelector('.cat-dd-panel');
        if (!panel) return;
        panel.innerHTML = (options || []).map(function (o) {
          const sel = String(o.value) === String(selected || '') ? ' is-selected' : '';
          return '<button type="button" class="cat-dd-row' + sel + '" data-value="' + o.value + '" data-color="' + o.color + '">'
            + '<span class="cat-dd-dot" style="background:' + o.color + ';"></span>' + o.label + '</button>';
        }).join('');
        const hidden = dd.querySelector('[data-cat-dd-value]');
        const dot = dd.querySelector('[data-cat-dd-toggle] .cat-dd-dot');
        const text = dd.querySelector('[data-cat-dd-text]');
        const chosen = (options || []).find(function (o) { return String(o.value) === String(selected || ''); });
        if (hidden) hidden.value = selected || '';
        if (dot) dot.style.background = chosen ? chosen.color : NEUTRAL;
        if (text) text.textContent = chosen ? chosen.label : (placeholder || '');
        bind(dd);
      };
    })();
  </script>
  @stack('scripts')
</body>
</html>
