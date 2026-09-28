import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:maynilad_app/models/assignment_model.dart';
import 'package:maynilad_app/models/user_model.dart';
import 'package:maynilad_app/providers/assignment_provider.dart';
import 'package:maynilad_app/providers/auth_provider.dart';
import 'package:maynilad_app/providers/incident_provider.dart';
import 'package:maynilad_app/providers/notification_provider.dart';
import 'package:maynilad_app/screens/offsite_screen.dart';
import 'package:provider/provider.dart';

/// AuthProvider keeps `_user` private with no setter, so the public getters are
/// overridden instead of reaching around the class.
class _FakeAuthProvider extends AuthProvider {
  _FakeAuthProvider(this._fakeUser);

  final User? _fakeUser;

  @override
  User? get user => _fakeUser;

  @override
  bool get isCustomer => _fakeUser?.isCustomer ?? true;
}

/// AssignmentProvider builds its own service instances, so a fake has to
/// override the getters it reads and stub the fetches it kicks off.
class _FakeAssignmentProvider extends AssignmentProvider {
  _FakeAssignmentProvider(this._fakeAssignments);

  final List<Assignment> _fakeAssignments;

  @override
  List<Assignment> get assignments => _fakeAssignments;

  @override
  bool get isLoading => false;

  @override
  String? get error => null;

  @override
  Future<void> fetchData() async {}

  @override
  Future<String?> teamLeaderAction(
    int assignmentId, {
    required String action,
    String? correctedCategory,
    String? correctedSeverity,
    String? rejectionReason,
  }) async =>
      null;
}

/// NotificationBell refetches on mount; stub it so the test never opens a
/// socket.
class _FakeNotificationProvider extends NotificationProvider {
  @override
  int get unreadCount => 0;

  @override
  Future<void> fetchNotifications() async {}
}

const _staff = User(
  id: 3,
  fullName: 'Team Leader Santos',
  email: 'santos@example.com',
  role: 'offsite_staff',
);

/// An incident filed 2026-09-20 08:30, deliberately far from the assignment
/// timestamp so a test can tell which one the card printed.
final _filedAt = DateTime.utc(2026, 9, 20, 8, 30);
final _assignedAt = DateTime.utc(2026, 9, 27, 14, 5);

Assignment assignmentWith({
  required String actionStatus,
  String incidentStatus = 'assigned',
  String severity = 'Low',
  bool omitSubmittedAt = false,
}) {
  return Assignment.fromJson({
    'id': 9,
    'incident_id': 42,
    'team_leader_id': 3,
    'action_status': actionStatus,
    'assigned_at': _assignedAt.toIso8601String(),
    'incident': {
      'id': 42,
      'description': 'Water meter is leaking.',
      'location': 'Zone 4, Kamuning',
      'category': 'Metering',
      'severity': severity,
      'status': incidentStatus,
      'submitted_at': omitSubmittedAt ? null : _filedAt.toIso8601String(),
    },
    'team_leader': {'id': 3, 'full_name': 'Team Leader Santos'},
  });
}

Widget pumpOffsite(WidgetTester tester, List<Assignment> assignments) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider<AuthProvider>(create: (_) => _FakeAuthProvider(_staff)),
      ChangeNotifierProvider<AssignmentProvider>(
        create: (_) => _FakeAssignmentProvider(assignments),
      ),
      ChangeNotifierProvider<IncidentProvider>(
        create: (_) => IncidentProvider(),
      ),
      ChangeNotifierProvider<NotificationProvider>(
        create: (_) => _FakeNotificationProvider(),
      ),
    ],
    child: const MaterialApp(home: OffsiteScreen()),
  );
}

void main() {
  group('OffsiteScreen complaint card actions', () {
    testWidgets('offers Accept, Reject and Correct before acceptance',
        (tester) async {
      for (final status in ['pending', 'assigned', 'correct', 'override']) {
        await tester.pumpWidget(
            pumpOffsite(tester, [assignmentWith(actionStatus: status)]));
        await tester.pumpAndSettle();

        expect(find.text('Accept'), findsOneWidget, reason: status);
        expect(find.text('Reject'), findsOneWidget, reason: status);
        expect(find.text('Correct'), findsOneWidget, reason: status);
        expect(find.text('CHANGE STATUS'), findsNothing, reason: status);
      }
    });

    testWidgets('offers only Change Status once accepted', (tester) async {
      for (final status in ['accept', 'in_progress']) {
        await tester.pumpWidget(
            pumpOffsite(tester, [assignmentWith(actionStatus: status)]));
        await tester.pumpAndSettle();

        expect(find.text('CHANGE STATUS'), findsOneWidget, reason: status);
        expect(find.text('Accept'), findsNothing, reason: status);
        expect(find.text('Reject'), findsNothing, reason: status);
        expect(find.text('Correct'), findsNothing, reason: status);
      }
    });

    testWidgets('keeps the status badge and the triage buttons on one line',
        (tester) async {
      await tester.pumpWidget(
          pumpOffsite(tester, [assignmentWith(actionStatus: 'assigned')]));
      await tester.pumpAndSettle();

      // "ASSIGNED" is the badge copy for the assigned incident status.
      final badge = tester.getTopLeft(find.text('ASSIGNED'));
      final accept = tester.getTopLeft(find.text('Accept'));
      final reject = tester.getTopLeft(find.text('Reject'));
      final correct = tester.getTopRight(find.text('Correct'));

      // The three buttons are laid out left to right...
      expect(accept.dx, lessThan(reject.dx));
      expect(reject.dx, lessThan(correct.dx));
      // ...on the badge's row, to its right, all within one line's height.
      expect(accept.dy, closeTo(badge.dy, 4));
      expect(reject.dy, closeTo(badge.dy, 4));
      expect(correct.dy, closeTo(badge.dy, 4));
      expect(correct.dx, greaterThan(badge.dx));
    });

    testWidgets('does not overflow on a narrow phone', (tester) async {
      // 360dp is the narrowest width a mainstream Android phone reports, and
      // the case the three-button row has to survive.
      tester.view.physicalSize = const Size(360, 780);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
          pumpOffsite(tester, [assignmentWith(actionStatus: 'assigned')]));
      await tester.pumpAndSettle();

      // Scoped to the card's own row: the screen header (logo + greeting +
      // bell) overflows below ~380dp independently of this change, so a
      // whole-screen takeException() check would fail for unrelated reasons.
      // The full-width View Details button marks the card's inner right edge.
      final cardRight = tester.getBottomRight(find.byType(ElevatedButton)).dx;
      final correctRight = tester.getTopRight(find.text('Correct')).dx;
      expect(correctRight, lessThanOrEqualTo(cardRight + 1),
          reason: 'triage buttons must stay within the card');

      // The three buttons still share the badge's line rather than wrapping.
      final badgeTop = tester.getTopLeft(find.text('ASSIGNED')).dy;
      final acceptTop = tester.getTopLeft(find.text('Accept')).dy;
      expect(acceptTop, closeTo(badgeTop, 4));
    });

    testWidgets('drops the High Priority pill', (tester) async {
      await tester.pumpWidget(pumpOffsite(tester, [
        assignmentWith(actionStatus: 'assigned', severity: 'High'),
      ]));
      await tester.pumpAndSettle();

      expect(find.textContaining('High Priority'), findsNothing);
      // Severity itself stays visible on the category line.
      expect(find.textContaining('Severity High'), findsOneWidget);
    });
  });

  group('OffsiteScreen complaint card date', () {
    testWidgets('shows the filed date, not the assignment date',
        (tester) async {
      await tester.pumpWidget(
          pumpOffsite(tester, [assignmentWith(actionStatus: 'assigned')]));
      await tester.pumpAndSettle();

      expect(find.textContaining('Sep 20, 2026'), findsOneWidget);
      expect(find.textContaining('Sep 27, 2026'), findsNothing);
    });

    testWidgets('falls back to the assignment date when submitted_at is null',
        (tester) async {
      await tester.pumpWidget(pumpOffsite(tester, [
        assignmentWith(actionStatus: 'assigned', omitSubmittedAt: true),
      ]));
      await tester.pumpAndSettle();

      expect(find.textContaining('Sep 27, 2026'), findsOneWidget);
    });
  });

  group('OffsiteScreen list filtering', () {
    testWidgets('moves resolved and rejected rows out of Active',
        (tester) async {
      await tester.pumpWidget(pumpOffsite(tester, [
        assignmentWith(actionStatus: 'assigned', incidentStatus: 'assigned'),
        assignmentWith(actionStatus: 'reject', incidentStatus: 'assigned'),
        assignmentWith(actionStatus: 'assigned', incidentStatus: 'resolved'),
      ]));
      await tester.pumpAndSettle();

      // The active card shows three actions; the two history rows do not, so
      // only one card's worth of triage buttons is on screen.
      expect(find.text('Accept'), findsOneWidget);
      expect(find.text('Reject'), findsOneWidget);
      expect(find.text('Correct'), findsOneWidget);
    });
  });
}
