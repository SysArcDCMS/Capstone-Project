/// Incident — matches `tbl_incidents` fields returned by the Laravel API.
class Incident {
  final int id;
  final int customerId;
  final String? description;
  final String? location;
  final String? category;
  final String? severity;
  final double? compositeScore;
  final String? status;
  final String? resolutionNotes;
  final DateTime? submittedAt;
  final DateTime? resolvedAt;
  final IncidentCustomer? customer;
  final Map<String, dynamic>? rawAnalysis;

  const Incident({
    required this.id,
    required this.customerId,
    this.description,
    this.location,
    this.category,
    this.severity,
    this.compositeScore,
    this.status,
    this.resolutionNotes,
    this.submittedAt,
    this.resolvedAt,
    this.customer,
    this.rawAnalysis,
  });

  bool get isResolved => status?.toLowerCase() == 'resolved';

  factory Incident.fromJson(Map<String, dynamic> json) {
    return Incident(
      id: (json['id'] as num?)?.toInt() ?? 0,
      customerId: (json['customer_id'] as num?)?.toInt() ?? 0,
      description: json['description']?.toString(),
      location: json['location']?.toString(),
      category: json['category']?.toString(),
      severity: json['severity']?.toString(),
      compositeScore:
          (json['composite_score'] as num?)?.toDouble(),
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
    );
  }
}

/// Nested `customer` object on incident responses.
class IncidentCustomer {
  final int id;
  final String fullName;
  final String? email;

  const IncidentCustomer({
    required this.id,
    required this.fullName,
    this.email,
  });

  factory IncidentCustomer.fromJson(Map<String, dynamic> json) {
    return IncidentCustomer(
      id: (json['id'] as num?)?.toInt() ?? 0,
      fullName: json['full_name']?.toString() ?? '',
      email: json['email']?.toString(),
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