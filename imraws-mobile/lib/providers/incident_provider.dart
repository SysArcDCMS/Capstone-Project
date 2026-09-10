import 'package:flutter/material.dart';

import '../models/incident_model.dart';
import '../services/api_service.dart';
import '../services/incident_service.dart';

class IncidentProvider with ChangeNotifier {
  final IncidentService _incidentService = IncidentService();

  List<Incident> _myIncidents = [];
  bool _isLoading = false;
  String? _error;
  ComplaintAnalysis? _lastAnalysis;

  List<Incident> get myIncidents => _myIncidents;
  bool get isLoading => _isLoading;
  String? get error => _error;
  ComplaintAnalysis? get lastAnalysis => _lastAnalysis;

  /// GET /api/incidents — scoped to own records by the server.
  Future<void> fetchMyIncidents() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final result = await _incidentService.getIncidents();
      _myIncidents = result.items;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Could not load your complaints.';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// POST /api/incidents — server runs the NLP pipeline and returns the
  /// classification in `analysis`.
  Future<Map<String, dynamic>> submitComplaint(
    String description,
    String? location,
  ) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final result =
          await _incidentService.submitComplaint(description: description, location: location);
      _lastAnalysis = result['analysis'] is Map<String, dynamic>
          ? ComplaintAnalysis.fromJson(result['analysis'])
          : null;
      await fetchMyIncidents();
      _isLoading = false;
      notifyListeners();
      return {
        'success': true,
        'message': 'Complaint submitted successfully.',
        'analysis': result['analysis'],
      };
    } on ApiException catch (e) {
      _isLoading = false;
      _error = e.message;
      notifyListeners();
      return {'success': false, 'message': e.message};
    } catch (_) {
      _isLoading = false;
      _error = 'Something went wrong. Please try again.';
      notifyListeners();
      return {'success': false, 'message': _error!};
    }
  }

  /// PATCH /api/incidents/{id}/status — offsite staff / engineer only.
  Future<String?> updateStatus(
    int incidentId, {
    required String status,
    String? resolutionNotes,
  }) async {
    try {
      await _incidentService.updateStatus(
        incidentId,
        status: status,
        resolutionNotes: resolutionNotes,
      );
      await fetchMyIncidents();
      return null; // success
    } on ApiException catch (e) {
      return e.message;
    } catch (_) {
      return 'Could not update status.';
    }
  }
}