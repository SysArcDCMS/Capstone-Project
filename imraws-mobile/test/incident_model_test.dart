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
