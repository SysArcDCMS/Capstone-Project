<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login · IMRAWS-NLP Admin</title>
  <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .login-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 2rem;
      background: linear-gradient(135deg, #f0f7ff 0%, #f6f8fa 100%);
    }
    .login-card {
      background: white;
      border-radius: 1rem;
      padding: 2.5rem;
      width: 100%;
      max-width: 420px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
    }
    .login-card .brand {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      font-size: 1.2rem;
      color: #0f172a;
      margin-bottom: 0.5rem;
    }
    .login-card .brand .logo-icon { font-size: 1.8rem; }
    .login-card .title { font-size: 1.4rem; font-weight: 600; margin: 0.5rem 0; }
    .login-card .subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem; }
    .login-card label { display: block; font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem; margin-top: 1rem; }
    .login-card input { width: 100%; }
    .login-card .btn { width: 100%; margin-top: 1.5rem; padding: 0.65rem; justify-content: center; }
    .login-error { background: #fee2e2; color: #991b1b; padding: 0.6rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; margin-bottom: 1rem; }
    .login-hint { margin-top: 1rem; font-size: 0.75rem; color: #94a3b8; text-align: center; }
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <div class="brand"><span class="logo-icon">💧</span> IMRAWS-NLP Admin</div>
      <h1 class="title">Sign in</h1>
      <p class="subtitle">Engineer / Administrator web portal</p>

      @if ($errors->any())
        <div class="login-error">
          @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
          @endforeach
        </div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" class="form-input" required autofocus value="{{ old('email') }}" />

        <label for="password">Password</label>
        <input id="password" name="password" type="password" class="form-input" required />

        <button type="submit" class="btn btn-dark">
          <i data-lucide="log-in"></i> Sign in
        </button>
      </form>

      <p class="login-hint">Customer accounts register via the mobile app. Offsite staff are provisioned by an Administrator.</p>
    </div>
  </div>
  <script>lucide.createIcons();</script>
</body>
</html>
