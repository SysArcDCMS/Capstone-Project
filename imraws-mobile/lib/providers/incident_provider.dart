import 'package:flutter/material.dart';

import '../models/attachment_model.dart';
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
  ///
  /// [photos] are the customer's own pictures of the problem. They travel in
  /// the same request, because the complaint has no id until this call
  /// succeeds and a second upload would leave the customer retrying.
  ///
  /// Returns the incident id under `incident_id` so the caller can jump
  /// straight to the detail view. The response also reports `photos_saved` and
  /// `photo_warnings`: the complaint is stored either way, so a photo that
  /// failed is surfaced as a warning rather than an outright failure.
  Future<Map<String, dynamic>> submitComplaint(
    String description,
    String? location, {
    double? latitude,
    double? longitude,
    List<ComplaintPhoto> photos = const [],
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final result = await _incidentService.submitComplaint(
        description: description,
        location: location,
        latitude: latitude,
        longitude: longitude,
        photos: photos,
      );
      _lastAnalysis = result['analysis'] is Map<String, dynamic>
          ? ComplaintAnalysis.fromJson(result['analysis'])
          : null;
      await fetchMyIncidents();
      _isLoading = false;
      notifyListeners();

      final warnings = result['photo_warnings'];

      return {
        'success': true,
        'message': _submissionMessage(warnings),
        'analysis': result['analysis'],
        'incident_id': _incidentIdFrom(result['data']),
        'photo_warnings': warnings is List ? warnings : const [],
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

  /// Pull the id out of the `{data: {...}}` envelope the server returns.
  static int? _incidentIdFrom(Object? data) {
    if (data is Map<String, dynamic>) {
      final id = data['id'];
      if (id is num) return id.toInt();
      final parsed = int.tryParse(id?.toString() ?? '');
      if (parsed != null) return parsed;
    }
    return null;
  }

  /// Report what actually happened to the photos.
  ///
  /// The complaint is stored even when a photo could not be, so saying
  /// "submitted successfully" without qualification would hide a lost picture.
  static String _submissionMessage(Object? warnings) {
    if (warnings is! List || warnings.isEmpty) {
      return 'Complaint submitted successfully.';
    }

    return 'Complaint submitted, but ${warnings.map((w) => w.toString()).join(' ')}';
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