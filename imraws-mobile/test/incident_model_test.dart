import 'package:flutter_test/flutter_test.dart';
import 'package:maynilad_app/models/incident_model.dart';

void main() {
  group('Incident.fromJson', () {
    test('parses the fields the complaint detail screen renders', () {
      final incident = Incident.fromJson({
        'id': 42,
        'customer_id': 7,
        'description': 'Water meter is leaking.',
        'location': 'Zone 4, Kamuning',
        'latitude': 14.5712,
        'longitude': 121.0342,
        'category': 'Metering',
        'severity': 'High',
        'status': 'in_progress',
        'submitted_at': '2026-09-20T08:30:00+00:00',
        'customer': {
          'id': 7,
          'full_name': 'Robert Dela Cruz',
          'email': 'robert.j@example.com',
          'contact_no': '09171234567',
        },
        'assignments': [
          {
            'id': 9,
            'incident_id': 42,
            'team_leader_id': 3,
            'action_status': 'in_progress',
            'resolution_notes': 'Waiting on the replacement part.',
            'assigned_at': '2026-09-20T09:00:00+00:00',
            'team_leader': {'id': 3, 'full_name': 'Team Leader Santos'},
          },
        ],
        'attachments': [
          {
            'id': 5,
            'incident_id': 42,
            'file_path': 'attachments/42/proof.jpg',
            'url': '/storage/attachments/42/proof.jpg',
            'mime_type': 'image/jpeg',
          },
        ],
      });

      expect(incident.id, 42);
      expect(incident.location, 'Zone 4, Kamuning');
      expect(incident.latitude, 14.5712);
      expect(incident.longitude, 121.0342);
      expect(incident.submittedAt, isNotNull);

      expect(incident.customer?.fullName, 'Robert Dela Cruz');
      expect(incident.customer?.contactNo, '09171234567');

      expect(incident.assignedOffsiteName, 'Team Leader Santos');
      expect(
          incident.latestResolutionNotes, 'Waiting on the replacement part.');

      expect(incident.attachments, hasLength(1));
      expect(
          incident.attachments.first.url, '/storage/attachments/42/proof.jpg');
      expect(incident.attachments.first.filePath, 'attachments/42/proof.jpg');
    });

    test('a list payload without relations leaves the detail-only fields empty',
        () {
      final incident = Incident.fromJson({
        'id': 42,
        'customer_id': 7,
        'status': 'open',
        'customer': {'id': 7, 'full_name': 'Robert Dela Cruz'},
      });

      expect(incident.assignments, isEmpty);
      expect(incident.attachments, isEmpty);
      expect(incident.assignedOffsiteName, isNull);
      expect(incident.latestResolutionNotes, isNull);
    });

    test('an unassigned complaint reports no offsite person', () {
      final incident = Incident.fromJson({
        'id': 42,
        'customer_id': 7,
        'status': 'open',
        'assignments': <Map<String, dynamic>>[],
      });

      expect(incident.currentAssignment, isNull);
      expect(incident.assignedOffsiteName, isNull);
    });
  });

  // Regression: tbl_incidents.latitude/longitude are decimal(10,7), and PDO
  // serialises Postgres `numeric` as a JSON *string*. The original
  // `(json['latitude'] as num?)` cast threw a TypeError on such a row, which
  // propagated out of the whole list parse and emptied the customer's
  // complaint list.
  group('decimal columns arriving as strings', () {
    test('coordinates sent as strings are parsed as numbers', () {
      final incident = Incident.fromJson({
        'id': 42,
        'customer_id': 7,
        'status': 'open',
        'latitude': '14.5712000',
        'longitude': '121.0342000',
      });

      expect(incident.latitude, 14.5712);
      expect(incident.longitude, 121.0342);
    });

    test('a decimal string never aborts the parse of a list payload', () {
      // The shape a customer actually receives from GET /api/incidents.
      final payload = [
        {'id': 1, 'customer_id': 7, 'status': 'open'},
        {
          'id': 2,
          'customer_id': 7,
          'status': 'open',
          'latitude': '14.5712000',
          'longitude': '121.0342000',
          'composite_score': '72.5',
        },
      ];

      final incidents = payload
          .map((json) => Incident.fromJson(json))
          .toList(growable: false);

      expect(incidents, hasLength(2));
      expect(incidents.first.latitude, isNull);
      expect(incidents.last.compositeScore, 72.5);
    });

    test('a non-numeric value degrades to null instead of throwing', () {
      final incident = Incident.fromJson({
        'id': 42,
        'latitude': 'not-a-number',
        'composite_score': '',
      });

      expect(incident.latitude, isNull);
      expect(incident.compositeScore, isNull);
    });

    test('numeric strings are read for ids, notes and file sizes too', () {
      final incident = Incident.fromJson({
        'id': '42',
        'customer_id': '7',
        'status': 'open',
        'composite_score': '88.25',
        'customer': {'id': '7', 'full_name': 'Robert Dela Cruz'},
        'assignments': [
          {
            'id': '9',
            'incident_id': '42',
            'team_leader_id': '3',
            'action_status': 'assigned',
          },
        ],
        'attachments': [
          {
            'id': '5',
            'incident_id': '42',
            'file_size': '20480',
            'url': '/storage/attachments/42/proof.jpg',
          },
        ],
      });

      expect(incident.id, 42);
      expect(incident.customerId, 7);
      expect(incident.compositeScore, 88.25);
      expect(incident.customer?.id, 7);
      expect(incident.assignments.first.id, 9);
      expect(incident.assignments.first.teamLeaderId, 3);
      expect(incident.attachments.first.fileSize, 20480);
    });

    test('a real number still parses unchanged', () {
      final incident = Incident.fromJson({
        'id': 42,
        'latitude': 14.5712,
        'longitude': 121.0342,
      });

      expect(incident.latitude, 14.5712);
      expect(incident.longitude, 121.0342);
    });
  });

  group('resolution note handover', () {
    Incident incidentWith(List<Map<String, dynamic>> assignments) {
      return Incident.fromJson({
        'id': 42,
        'customer_id': 7,
        'status': 'resolved',
        'assignments': assignments,
      });
    }

    test('the latest live assignment wins over an older one', () {
      final incident = incidentWith([
        {
          'id': 1,
          'incident_id': 42,
          'team_leader_id': 3,
          'action_status': 'reassign',
          'resolution_notes': 'First attempt, handed over.',
          'assigned_at': '2026-09-20T09:00:00+00:00',
          'team_leader': {'id': 3, 'full_name': 'First Leader'},
        },
        {
          'id': 2,
          'incident_id': 42,
          'team_leader_id': 4,
          'action_status': 'resolved',
          'resolution_notes': 'Meter replaced.',
          'assigned_at': '2026-09-21T09:00:00+00:00',
          'team_leader': {'id': 4, 'full_name': 'Second Leader'},
        },
      ]);

      expect(incident.assignedOffsiteName, 'Second Leader');
      expect(incident.latestResolutionNotes, 'Meter replaced.');
    });

    test('a reassigned-only payload does not present the old note as current',
        () {
      final incident = incidentWith([
        {
          'id': 1,
          'incident_id': 42,
          'team_leader_id': 3,
          'action_status': 'reassign',
          'resolution_notes': 'First attempt, handed over.',
          'assigned_at': '2026-09-20T09:00:00+00:00',
          'team_leader': {'id': 3, 'full_name': 'First Leader'},
        },
      ]);

      expect(incident.assignedOffsiteName, isNull);
      // The note is still recoverable for the record, but no leader is current.
      expect(incident.latestResolutionNotes, 'First attempt, handed over.');
    });

    test('a blank note is treated as no note', () {
      final incident = incidentWith([
        {
          'id': 1,
          'incident_id': 42,
          'team_leader_id': 3,
          'action_status': 'assigned',
          'resolution_notes': '   ',
          'assigned_at': '2026-09-20T09:00:00+00:00',
          'team_leader': {'id': 3, 'full_name': 'Team Leader Santos'},
        },
      ]);

      expect(incident.latestResolutionNotes, isNull);
    });
  });
}
