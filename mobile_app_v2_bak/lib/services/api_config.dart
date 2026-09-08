class ApiConfig {
  static const String baseUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'http://192.168.254.122:8000/api',
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