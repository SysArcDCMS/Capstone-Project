class ApiConfig {
  /// Backend base URL, injected at build time as `--dart-define=API_URL=...`.
  ///
  /// `tool/run.dart` overrides this with the machine's LAN address, which is
  /// what a physical phone on the same Wi-Fi needs. The fallback below points
  /// at the loopback address on purpose: it only resolves on the device when
  /// `adb reverse tcp:8000 tcp:8000` is active, so an unconfigured run fails
  /// loudly on a refused connection instead of silently aiming at a stale IP
  /// that DHCP has since handed to a different machine.
  static const String baseUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'http://127.0.0.1:8000/api',
  );

  /// Server origin without the `/api` suffix, e.g. `http://192.168.1.20:8000`.
  ///
  /// Attachment `url` values come back root-relative (`/storage/...`) because
  /// they are produced by `Storage::url()`, so they must be joined to the
  /// origin rather than to [baseUrl] — that would produce `/api/storage/...`,
  /// which the web server never serves.
  static String get origin {
    final uri = Uri.parse(baseUrl);
    return '${uri.scheme}://${uri.authority}';
  }

  /// Absolute URL for a root-relative path returned by the API.
  static String absoluteUrl(String path) {
    if (path.isEmpty) return path;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return '$origin${path.startsWith('/') ? '' : '/'}$path';
  }

  // ── Auth ────────────────────────────────────────────────────────────
  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String me = '/auth/me';
  static const String profile = '/auth/profile';
  static const String changePassword = '/auth/change-password';
  static const String logout = '/auth/logout';
  static const String refresh = '/auth/refresh';
  static const String forgotPassword = '/auth/forgot-password';
  static const String resetPassword = '/auth/reset-password';

  // ── Incidents ───────────────────────────────────────────────────────
  static const String incidents = '/incidents';
  static String incident(int id) => '/incidents/$id';
  static String incidentStatus(int id) => '/incidents/$id/status';
  static String incidentAttachments(int id) => '/incidents/$id/attachments';
  static String attachment(int id) => '/attachments/$id';

  // ── Assignments ────────────────────────────────────────────────────
  static const String assignments = '/assignments';
  static String assignment(int id) => '/assignments/$id';
  static String assignmentTeamLeaderAction(int id) =>
      '/assignments/$id/team-leader-action';

  // ── Availability ────────────────────────────────────────────────────
  static const String availability = '/availability';
  static const String availabilityMe = '/availability/me';

  // ── Notifications ───────────────────────────────────────────────────
  static const String notifications = '/notifications';
  static String notificationRead(int id) => '/notifications/$id/read';
  static const String notificationMarkAllRead = '/notifications/mark-all-read';
}
