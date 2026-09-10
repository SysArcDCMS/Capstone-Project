<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>@yield('title', 'Dashboard') · IMRAWS-NLP Admin</title>
  <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
  <script src="https://unpkg.com/lucide@latest"></script>
  @stack('styles')
</head>
<body>
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span class="logo-icon">💧</span>
      <span>IMRAWS-NLP Admin</span>
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
      <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
        <i data-lucide="users"></i> Users
      </a>
      <a href="{{ route('categories.index') }}" class="nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
        <i data-lucide="folder"></i> Categories
      </a>
      <a href="{{ route('reports.dashboard') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
        <i data-lucide="bar-chart-2"></i> Reports
      </a>
      <a href="{{ route('settings') }}" class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}">
        <i data-lucide="settings"></i> Settings
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="avatar-sm">{{ strtoupper(substr(auth()->user()->full_name ?? 'U', 0, 1)) }}{{ strtoupper(substr(auth()->user()->full_name ?? '', 1, 1)) }}</div>
      <div class="user-meta">
        <div class="name">{{ auth()->user()->full_name }}</div>
        <div class="role">{{ ucwords(str_replace('_', ' ', auth()->user()->role)) }}</div>
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
  </script>
  @stack('scripts')
</body>
</html>
