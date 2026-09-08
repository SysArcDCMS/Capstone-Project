/// Incident attachment — matches `tbl_incident_attachments` API fields.
class Attachment {
  final int id;
  final int incidentId;
  final String? filePath;
  final String? originalName;
  final String? mimeType;
  final int? fileSize;
  final String? caption;
  final String? url;
  final DateTime? createdAt;

  const Attachment({
    required this.id,
    required this.incidentId,
    this.filePath,
    this.originalName,
    this.mimeType,
    this.fileSize,
    this.caption,
    this.url,
    this.createdAt,
  });

  factory Attachment.fromJson(Map<String, dynamic> json) {
    return Attachment(
      id: (json['id'] as num?)?.toInt() ?? 0,
      incidentId: (json['incident_id'] as num?)?.toInt() ?? 0,
      filePath: json['file_path']?.toString(),
      originalName: json['original_name']?.toString(),
      mimeType: json['mime_type']?.toString(),
      fileSize: (json['file_size'] as num?)?.toInt(),
      caption: json['caption']?.toString(),
      url: json['url']?.toString(),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
    );
  }
}