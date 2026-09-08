/// Central API configuration.
///
/// Base URL comes from a compile-time define so it can be changed without
/// touching source:
///
///   flutter run --dart-define=API_URL=http://192.168.1.10:8000/api
///
/// Defaults to the Android emulator's host loopback (maps to the PC where
/// the Laravel backend runs). Physical devices must pass their PC's LAN IP.
class ApiConfig {
  static const String baseUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );

  // ── Auth ────────────────────────────────────────────────────────────
  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String me = '/auth/me';
  static const String profile = '/auth/profile';
  static const String logout = '/auth/logout';
  static const String refresh = '/auth/refresh';

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