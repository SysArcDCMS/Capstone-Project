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