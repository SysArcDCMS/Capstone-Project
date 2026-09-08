import '../models/assignment_model.dart';
import 'api_config.dart';
import 'api_service.dart';

class AssignmentService {
  final ApiService _api = ApiService.instance;

  /// GET /api/assignments — offsite staff see only their own assignments.
  Future<List<Assignment>> getAssignments({String? status}) async {
    final data = await _api.get(ApiConfig.assignments, query: {
      if (status != null) 'status': status,
      'per_page': 100,
    });
    final rawList = data is Map<String, dynamic> ? data['data'] : null;
    if (rawList is! List) return const [];
    return rawList
        .whereType<Map<String, dynamic>>()
        .map(Assignment.fromJson)
        .toList();
  }

  /// POST /api/assignments/{id}/team-leader-action
  ///
  /// `action` is one of: accept | reject | correct.
  /// - reject needs rejectionReason
  /// - correct needs correctedCategory and/or correctedSeverity
  Future<void> teamLeaderAction(
    int assignmentId, {
    required String action,
    String? correctedCategory,
    String? correctedSeverity,
    String? rejectionReason,
  }) async {
    await _api.post(
      ApiConfig.assignmentTeamLeaderAction(assignmentId),
      data: {
        'action': action,
        if (correctedCategory != null) 'corrected_category': correctedCategory,
        if (correctedSeverity != null) 'corrected_severity': correctedSeverity,
        if (rejectionReason != null && rejectionReason.trim().isNotEmpty)
          'rejection_reason': rejectionReason.trim(),
      },
    );
  }
}