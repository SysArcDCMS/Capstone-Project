import 'dart:typed_data';

import '../models/attachment_model.dart';
import '../models/incident_model.dart';
import 'api_config.dart';
import 'api_service.dart';

/// Pagination envelope returned by Laravel's LengthAwarePaginator.
class Paginated<T> {
  final List<T> items;
  final int total;
  final int currentPage;
  final int lastPage;

  const Paginated({
    required this.items,
    required this.total,
    required this.currentPage,
    required this.lastPage,
  });

  factory Paginated.fromJson(Map<String, dynamic> json, T Function(Map<String, dynamic>) parse) {
    final rawList = json['data'];
    final items = rawList is List
        ? rawList.whereType<Map<String, dynamic>>().map(parse).toList()
        : <T>[];
    final meta = json['meta'];
    return Paginated(
      items: items,
      total: (meta is Map && meta['total'] is num) ? (meta['total'] as num).toInt() : items.length,
      currentPage: (meta is Map && meta['current_page'] is num) ? (meta['current_page'] as num).toInt() : 1,
      lastPage: (meta is Map && meta['last_page'] is num) ? (meta['last_page'] as num).toInt() : 1,
    );
  }
}

class IncidentService {
  final ApiService _api = ApiService.instance;

  /// GET /api/incidents — server already scopes by role (customers see only
  /// their own; offsite staff see only their assigned incidents).
  Future<Paginated<Incident>> getIncidents({
    String? status,
    String? category,
    String? severity,
    int perPage = 20,
  }) async {
    final data = await _api.get(ApiConfig.incidents, query: {
      if (status != null) 'status': status,
      if (category != null) 'category': category,
      if (severity != null) 'severity': severity,
      'per_page': perPage,
    });
    if (data is! Map<String, dynamic>) {
      throw ApiException(0, 'Could not load incidents.');
    }
    return Paginated.fromJson(data, Incident.fromJson);
  }

  /// POST /api/incidents — submission triggers the NLP pipeline server-side.
  /// Returns the full `{data, analysis, assignment}` payload.
  Future<Map<String, dynamic>> submitComplaint({
    required String description,
    String? location,
  }) async {
    final data = await _api.post(ApiConfig.incidents, data: {
      'description': description.trim(),
      if (location != null && location.trim().isNotEmpty)
        'location': location.trim(),
    });
    if (data is! Map<String, dynamic>) {
      throw ApiException(0, 'Could not submit complaint.');
    }
    return data;
  }

  /// GET /api/incidents/{id}
  Future<Incident?> getIncident(int id) async {
    final data = await _api.get(ApiConfig.incident(id));
    final incidentMap = data is Map<String, dynamic> ? data['data'] : null;
    if (incidentMap is! Map<String, dynamic>) return null;
    return Incident.fromJson(incidentMap);
  }

  /// PATCH /api/incidents/{id}/status
  /// Valid statuses: open, assigned, in_progress, resolved, rejected.
  Future<Incident?> updateStatus(
    int id, {
    required String status,
    String? resolutionNotes,
  }) async {
    final data = await _api.patch(ApiConfig.incidentStatus(id), data: {
      'status': status,
      if (resolutionNotes != null && resolutionNotes.trim().isNotEmpty)
        'resolution_notes': resolutionNotes.trim(),
    });
    final incidentMap = data is Map<String, dynamic> ? data['data'] : null;
    if (incidentMap is Map<String, dynamic>) {
      return Incident.fromJson(incidentMap);
    }
    return getIncident(id);
  }

  /// POST /api/incidents/{id}/attachments — photo proof (staff only).
  Future<Attachment> uploadAttachment(
    int incidentId, {
    required Uint8List bytes,
    required String filename,
    String? caption,
  }) async {
    final data = await _api.upload(
      ApiConfig.incidentAttachments(incidentId),
      bytes: bytes,
      filename: filename,
      caption: caption,
    );
    final attachmentMap = data is Map<String, dynamic> ? data['data'] : null;
    if (attachmentMap is! Map<String, dynamic>) {
      throw ApiException(0, 'Could not upload attachment.');
    }
    return Attachment.fromJson(attachmentMap);
  }

  /// GET /api/incidents/{id}/attachments
  Future<List<Attachment>> getAttachments(int incidentId) async {
    final data = await _api.get(ApiConfig.incidentAttachments(incidentId));
    final rawList = data is Map<String, dynamic> ? data['data'] : null;
    if (rawList is! List) return const [];
    return rawList
        .whereType<Map<String, dynamic>>()
        .map(Attachment.fromJson)
        .toList();
  }
}