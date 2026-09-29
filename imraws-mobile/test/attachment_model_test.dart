import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:maynilad_app/models/attachment_model.dart';

void main() {
  group('Attachment.fromJson kind', () {
    test('reads the kind the server reports', () {
      final evidence = Attachment.fromJson({
        'id': 7,
        'incident_id': 42,
        'kind': 'evidence',
        'url': '/api/attachments/7/photo',
      });
      final proof = Attachment.fromJson({
        'id': 8,
        'incident_id': 42,
        'kind': 'proof',
        'url': '/api/attachments/8/photo',
      });

      expect(evidence.isEvidence, isTrue);
      expect(evidence.kindLabel, 'Photos from you');
      expect(proof.isEvidence, isFalse);
      expect(proof.kindLabel, 'Photo proof');
    });

    test('one label per kind, shared with the detail view', () {
      // The gallery headings come from this so the two kinds cannot drift into
      // the same or mismatched wording.
      expect(Attachment.labelForKind(Attachment.kindEvidence), 'Photos from you');
      expect(Attachment.labelForKind(Attachment.kindProof), 'Photo proof');
      expect(
        Attachment.fromJson({'id': 1, 'kind': Attachment.kindEvidence}).kindLabel,
        Attachment.labelForKind(Attachment.kindEvidence),
      );
    });

    test('defaults to proof when the server omits the kind', () {
      // An older server serialised no kind and every photo was staff proof, so
      // an absent value must not be mistaken for customer evidence.
      final attachment = Attachment.fromJson({
        'id': 5,
        'incident_id': 42,
        'url': '/api/attachments/5/photo',
      });

      expect(attachment.kind, Attachment.kindProof);
      expect(attachment.isEvidence, isFalse);
    });
  });

  group('ComplaintPhoto', () {
    test('labels a JPEG part with its real content type', () {
      // The server sniffs each upload and rejects anything that is not an
      // image, which sniffing gets wrong on application/octet-stream.
      final photo = ComplaintPhoto.jpg(bytes: Uint8List.fromList([1, 2, 3]));

      expect(photo.contentType, 'image/jpeg');
      expect(photo.filename, 'photo.jpg');
    });

    test('caps the count and size to what the server accepts', () {
      // Mirrors IncidentAttachment::MAX_EVIDENCE_PHOTOS and ::MAX_BYTES.
      expect(ComplaintPhoto.maxPhotos, 3);
      expect(ComplaintPhoto.maxBytes, 5 * 1024 * 1024);
    });
  });
}
