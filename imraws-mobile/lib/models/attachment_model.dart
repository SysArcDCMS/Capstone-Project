import 'dart:typed_data';

import 'json_parsing.dart';

/// A photo a customer picked to attach while filing a complaint.
///
/// These are the customer's own pictures of the problem — "evidence" on the
/// server — as opposed to the photo proof offsite staff upload after a repair.
/// The two are stored in separate directories and labelled differently, so the
/// detail view can tell them apart.
class ComplaintPhoto {
  /// The encoded image bytes, sent as `photos[]`.
  final Uint8List bytes;

  /// Name presented to the server. The server derives the stored extension from
  /// the sniffed MIME type, so this is for the caller's benefit only.
  final String filename;

  /// Content type sent with the part.
  ///
  /// Set explicitly rather than left as application/octet-stream: the server
  /// sniffs the upload and rejects anything that is not JPEG, PNG, or WebP, and
  /// sniffing is unreliable on a generic part.
  final String contentType;

  const ComplaintPhoto({
    required this.bytes,
    required this.filename,
    required this.contentType,
  });

  factory ComplaintPhoto.jpg({
    required Uint8List bytes,
    String filename = 'photo.jpg',
  }) =>
      ComplaintPhoto(bytes: bytes, filename: filename, contentType: 'image/jpeg');

  /// Max photos one complaint may carry. Mirrors
  /// IncidentAttachment::MAX_EVIDENCE_PHOTOS.
  static const int maxPhotos = 3;

  /// Max size of one photo: 5 MB. Mirrors IncidentAttachment::MAX_BYTES.
  static const int maxBytes = 5 * 1024 * 1024;
}

/// A stored incident attachment — matches `tbl_incident_attachments` API fields.
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

  /// `evidence` for a customer's photo, `proof` for a staff one.
  ///
  /// Derived server-side from the storage path and sent explicitly, because the
  /// gallery labels each photo and cannot tell them apart from the URL.
  final String kind;

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
    this.kind = kindProof,
  });

  /// A photo the customer attached when filing.
  static const String kindEvidence = 'evidence';

  /// A photo offsite staff attached to confirm a repair.
  static const String kindProof = 'proof';

  bool get isEvidence => kind == kindEvidence;

  /// Heading for this kind's band in the detail view.
  ///
  /// "Photos from you" leads with the customer's own account; "Photo proof" is
  /// the staff confirmation of the repair. The two are never pooled into one
  /// undifferentiated strip, because they carry different weight.
  static String labelForKind(String kind) =>
      kind == kindEvidence ? 'Photos from you' : 'Photo proof';

  String get kindLabel => labelForKind(kind);

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
      // Absent on an older server, where every photo was staff proof.
      kind: json['kind']?.toString() ?? kindProof,
    );
  }
}
