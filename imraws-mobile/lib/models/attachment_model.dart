import 'json_parsing.dart';

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
      id: jsonAsInt(json['id']) ?? 0,
      incidentId: jsonAsInt(json['incident_id']) ?? 0,
      filePath: json['file_path']?.toString(),
      originalName: json['original_name']?.toString(),
      mimeType: json['mime_type']?.toString(),
      fileSize: jsonAsInt(json['file_size']),
      caption: json['caption']?.toString(),
      url: json['url']?.toString(),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())?.toLocal()
          : null,
    );
  }
}
