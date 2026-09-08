import 'incident_model.dart';

/// Assignment — matches `tbl_assignments` fields returned by the Laravel API.
class Assignment {
  final int id;
  final int incidentId;
  final int teamLeaderId;
  final int? engineerReviewId;
  final String? actionStatus;
  final String? resolutionNotes;
  final DateTime? assignedAt;
  final Incident? incident;

  const Assignment({
    required this.id,
    required this.incidentId,
    required this.teamLeaderId,
    this.engineerReviewId,
    this.actionStatus,
    this.resolutionNotes,
    this.assignedAt,
    this.incident,
  });

  bool get hasBeenActedOn =>
      actionStatus != null && actionStatus != 'assigned' && actionStatus != 'pending';

  factory Assignment.fromJson(Map<String, dynamic> json) {
    return Assignment(
      id: (json['id'] as num?)?.toInt() ?? 0,
      incidentId: (json['incident_id'] as num?)?.toInt() ?? 0,
      teamLeaderId: (json['team_leader_id'] as num?)?.toInt() ?? 0,
      engineerReviewId:
          (json['engineer_review_id'] as num?)?.toInt(),
      actionStatus: json['action_status']?.toString(),
      resolutionNotes: json['resolution_notes']?.toString(),
      assignedAt: json['assigned_at'] != null
          ? DateTime.tryParse(json['assigned_at'].toString())
          : null,
      incident: json['incident'] is Map<String, dynamic>
          ? Incident.fromJson(json['incident'])
          : null,
    );
  }
}