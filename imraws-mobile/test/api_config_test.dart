import 'package:flutter_test/flutter_test.dart';
import 'package:maynilad_app/services/api_config.dart';

void main() {
  group('ApiConfig.absoluteUrl', () {
    test('joins a root-relative storage path to the server origin', () {
      // ApiConfig.baseUrl carries the /api segment, so the attachment URL must
      // not be joined to it — that would request /api/storage/... which the
      // web server never serves.
      expect(ApiConfig.baseUrl, endsWith('/api'));
      expect(
        ApiConfig.absoluteUrl('/storage/attachments/42/proof.jpg'),
        '${ApiConfig.origin}/storage/attachments/42/proof.jpg',
      );
      expect(ApiConfig.origin, isNot(contains('/api')));
    });

    test('a path without a leading slash is still joined correctly', () {
      expect(
        ApiConfig.absoluteUrl('storage/attachments/42/proof.jpg'),
        '${ApiConfig.origin}/storage/attachments/42/proof.jpg',
      );
    });

    test('an already absolute URL is passed through unchanged', () {
      expect(
        ApiConfig.absoluteUrl('https://cdn.example.com/a.jpg'),
        'https://cdn.example.com/a.jpg',
      );
      expect(
        ApiConfig.absoluteUrl('http://192.168.1.20:8000/storage/a.jpg'),
        'http://192.168.1.20:8000/storage/a.jpg',
      );
    });

    test('an empty path stays empty rather than becoming the origin', () {
      expect(ApiConfig.absoluteUrl(''), '');
    });
  });
}
