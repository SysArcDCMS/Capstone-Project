import 'incident_model.dart';
import 'user_ref.dart';

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
  final UserRef? teamLeader;
  final UserRef? engineer;

  const Assignment({
    required this.id,
    required this.incidentId,
    required this.teamLeaderId,
    this.engineerReviewId,
    this.actionStatus,
    this.resolutionNotes,
    this.assignedAt,
    this.incident,
    this.teamLeader,
    this.engineer,
  });

  bool get hasBeenActedOn =>
      actionStatus != null &&
      actionStatus != 'assigned' &&
      actionStatus != 'pending';

  /// A reassigned row is a dead assignment — the complaint moved on to another
  /// team leader, so its notes and person must not be presented as current.
  bool get isReassigned => actionStatus == 'reassign';

  /// Name of the offsite person handling this complaint, when the API
  /// eager-loaded it.
  String? get teamLeaderName {
    final name = teamLeader?.fullName;
    if (name != null && name.trim().isNotEmpty) return name;
    return null;
  }

  factory Assignment.fromJson(Map<String, dynamic> json) {
    return Assignment(
      id: (json['id'] as num?)?.toInt() ?? 0,
      incidentId: (json['incident_id'] as num?)?.toInt() ?? 0,
      teamLeaderId: (json['team_leader_id'] as num?)?.toInt() ?? 0,
      engineerReviewId: (json['engineer_review_id'] as num?)?.toInt(),
      actionStatus: json['action_status']?.toString(),
      resolutionNotes: json['resolution_notes']?.toString(),
      assignedAt: json['assigned_at'] != null
          ? DateTime.tryParse(json['assigned_at'].toString())?.toLocal()
          : null,
      incident: json['incident'] is Map<String, dynamic>
          ? Incident.fromJson(json['incident'])
          : null,
      // Laravel serialises eager-loaded relations in snake_case, so the
      // payload key is `team_leader`, not the relation name.
      teamLeader: _userRef(json, 'team_leader'),
      engineer: _userRef(json, 'engineer'),
    );
  }
}

UserRef? _userRef(Map<String, dynamic> json, String key) {
  final raw = json[key];
  return raw is Map<String, dynamic> ? UserRef.fromJson(raw) : null;
}
