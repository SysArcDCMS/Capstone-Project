import 'package:flutter/material.dart';

import '../models/assignment_model.dart';
import '../services/api_service.dart';
import '../services/assignment_service.dart';
import '../services/availability_service.dart';

class AssignmentProvider with ChangeNotifier {
  final AssignmentService _assignmentService = AssignmentService();
  final AvailabilityService _availabilityService = AvailabilityService();

  List<Assignment> _assignments = [];
  String _availability = AvailabilityStatus.unavailable;
  bool _isLoading = false;
  String? _error;

  List<Assignment> get assignments => _assignments;
  String get availability => _availability;
  bool get isLoading => _isLoading;
  String? get error => _error;

  /// Loads assignments and the staff member's availability together.
  Future<void> fetchData() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final results = await Future.wait([
        _assignmentService.getAssignments(),
        _availabilityService.getMyAvailability(),
      ]);
      _assignments = results[0] as List<Assignment>;
      _availability = results[1] as String;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Could not load your assignments.';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// POST /api/assignments/{id}/team-leader-action.
  /// Returns null on success, otherwise an error string.
  Future<String?> teamLeaderAction(
    int assignmentId, {
    required String action,
    String? correctedCategory,
    String? correctedSeverity,
    String? rejectionReason,
  }) async {
    try {
      await _assignmentService.teamLeaderAction(
        assignmentId,
        action: action,
        correctedCategory: correctedCategory,
        correctedSeverity: correctedSeverity,
        rejectionReason: rejectionReason,
      );
      await fetchData();
      return null;
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Could not complete the action.';
    }
  }

  /// POST /api/availability
  Future<String?> updateAvailability(String status) async {
    try {
      await _availabilityService.updateAvailability(status);
      _availability = status;
      notifyListeners();
      return null;
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Could not update availability.';
    }
  }
}