/// Notification — matches `tbl_notifications` fields returned by the API.
class AppNotification {
  final int id;
  final int? incidentId;
  final int userId;
  final String message;
  final bool isRead;
  final DateTime? createdAt;
  final IncidentSummary? incident;

  const AppNotification({
    required this.id,
    this.incidentId,
    required this.userId,
    required this.message,
    required this.isRead,
    this.createdAt,
    this.incident,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: (json['id'] as num?)?.toInt() ?? 0,
      incidentId: (json['incident_id'] as num?)?.toInt(),
      userId: (json['user_id'] as num?)?.toInt() ?? 0,
      message: json['message']?.toString() ?? '',
      isRead: json['is_read'] == true || json['is_read'] == 1,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      incident: json['incident'] is Map<String, dynamic>
          ? IncidentSummary.fromJson(json['incident'])
          : null,
    );
  }
}

/// Minimal incident info attached to a notification.
class IncidentSummary {
  final int id;
  final String? category;
  final String? severity;
  final String? status;

  const IncidentSummary({
    required this.id,
    this.category,
    this.severity,
    this.status,
  });

  factory IncidentSummary.fromJson(Map<String, dynamic> json) {
    return IncidentSummary(
      id: (json['id'] as num?)?.toInt() ?? 0,
      category: json['category']?.toString(),
      severity: json['severity']?.toString(),
      status: json['status']?.toString(),
    );
  }
}