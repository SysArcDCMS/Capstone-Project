import 'assignment_model.dart';
import 'attachment_model.dart';

/// Incident — matches `tbl_incidents` fields returned by the Laravel API.
///
/// [assignments] and [attachments] are only populated by the by-id endpoint
/// (`GET /api/incidents/{id}`), which eager-loads them; the list endpoint does
/// not, so they stay empty on a list-sourced instance.
class Incident {
  final int id;
  final int customerId;
  final String? description;
  final String? location;
  final double? latitude;
  final double? longitude;
  final String? category;
  final String? severity;
  final double? compositeScore;
  final String? status;
  final String? resolutionNotes;
  final DateTime? submittedAt;
  final DateTime? resolvedAt;
  final IncidentCustomer? customer;
  final List<Assignment> assignments;
  final List<Attachment> attachments;
  final Map<String, dynamic>? rawAnalysis;

  const Incident({
    required this.id,
    required this.customerId,
    this.description,
    this.location,
    this.latitude,
    this.longitude,
    this.category,
    this.severity,
    this.compositeScore,
    this.status,
    this.resolutionNotes,
    this.submittedAt,
    this.resolvedAt,
    this.customer,
    this.assignments = const [],
    this.attachments = const [],
    this.rawAnalysis,
  });

  bool get isResolved => status?.toLowerCase() == 'resolved';

  /// The assignment that currently owns the complaint: the most recent row that
  /// was not handed off to somebody else.
  Assignment? get currentAssignment {
    if (assignments.isEmpty) return null;
    final live = assignments.where((a) => !a.isReassigned).toList();
    if (live.isEmpty) return null;
    live.sort((a, b) {
      final at = a.assignedAt;
      final bt = b.assignedAt;
      if (at == null && bt == null) return 0;
      if (at == null) return 1;
      if (bt == null) return -1;
      return bt.compareTo(at);
    });
    return live.first;
  }

  /// Name of the offsite person handling this complaint, or null while the
  /// complaint is still unassigned.
  String? get assignedOffsiteName => currentAssignment?.teamLeaderName;

  /// Resolution note for the customer to read. Notes live on the assignment
  /// row, not on `tbl_incidents`, so the by-id payload is what carries them.
  String? get latestResolutionNotes {
    final current = currentAssignment?.resolutionNotes;
    if (current != null && current.trim().isNotEmpty) return current;
    for (final assignment in assignments) {
      final notes = assignment.resolutionNotes;
      if (notes != null && notes.trim().isNotEmpty) return notes;
    }
    if (resolutionNotes != null && resolutionNotes!.trim().isNotEmpty) {
      return resolutionNotes;
    }
    return null;
  }

  factory Incident.fromJson(Map<String, dynamic> json) {
    return Incident(
      id: (json['id'] as num?)?.toInt() ?? 0,
      customerId: (json['customer_id'] as num?)?.toInt() ?? 0,
      description: json['description']?.toString(),
      location: json['location']?.toString(),
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      category: json['category']?.toString(),
      severity: json['severity']?.toString(),
      compositeScore: (json['composite_score'] as num?)?.toDouble(),
      status: json['status']?.toString(),
      resolutionNotes: json['resolution_notes']?.toString(),
      submittedAt: json['submitted_at'] != null
          ? DateTime.tryParse(json['submitted_at'].toString())?.toLocal()
          : null,
      resolvedAt: json['resolved_at'] != null
          ? DateTime.tryParse(json['resolved_at'].toString())?.toLocal()
          : null,
      customer: json['customer'] is Map<String, dynamic>
          ? IncidentCustomer.fromJson(json['customer'])
          : null,
      assignments: _listOf(json['assignments'], Assignment.fromJson),
      attachments: _listOf(json['attachments'], Attachment.fromJson),
    );
  }
}

/// Parses a Laravel relation array, tolerating a missing or null key.
List<T> _listOf<T>(dynamic raw, T Function(Map<String, dynamic>) parse) {
  if (raw is! List) return const [];
  return raw
      .whereType<Map<String, dynamic>>()
      .map(parse)
      .toList(growable: false);
}

/// Nested `customer` object on incident responses.
class IncidentCustomer {
  final int id;
  final String fullName;
  final String? email;
  final String? contactNo;
  final String? address;

  const IncidentCustomer({
    required this.id,
    required this.fullName,
    this.email,
    this.contactNo,
    this.address,
  });

  factory IncidentCustomer.fromJson(Map<String, dynamic> json) {
    return IncidentCustomer(
      id: (json['id'] as num?)?.toInt() ?? 0,
      fullName: json['full_name']?.toString() ?? '',
      email: json['email']?.toString(),
      contactNo: json['contact_no']?.toString(),
      address: json['address']?.toString(),
    );
  }
}

/// Result of the NLP pipeline returned on `POST /api/incidents`.
class ComplaintAnalysis {
  final String? category;
  final double categoryConfidence;
  final String? sentiment;
  final double sentimentScore;
  final double compositeScore;
  final String? severity;

  const ComplaintAnalysis({
    this.category,
    this.categoryConfidence = 0,
    this.sentiment,
    this.sentimentScore = 0,
    this.compositeScore = 0,
    this.severity,
  });

  factory ComplaintAnalysis.fromJson(Map<String, dynamic> json) {
    return ComplaintAnalysis(
      category: json['category']?.toString(),
      categoryConfidence:
          (json['category_confidence'] as num?)?.toDouble() ?? 0,
      sentiment: json['sentiment']?.toString(),
      sentimentScore: (json['sentiment_score'] as num?)?.toDouble() ?? 0,
      compositeScore: (json['composite_score'] as num?)?.toDouble() ?? 0,
      severity: json['severity']?.toString(),
    );
  }
}
