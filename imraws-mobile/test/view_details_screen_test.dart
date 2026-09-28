import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:maynilad_app/models/assignment_model.dart';
import 'package:maynilad_app/models/incident_model.dart';
import 'package:maynilad_app/models/user_model.dart';
import 'package:maynilad_app/providers/assignment_provider.dart';
import 'package:maynilad_app/providers/auth_provider.dart';
import 'package:maynilad_app/providers/incident_provider.dart';
import 'package:maynilad_app/screens/view_details_screen.dart';
import 'package:maynilad_app/services/incident_service.dart';
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

/// Keeps the by-id refetch off the network so the test only exercises the
/// screen's route handling and rendering.
class _FakeIncidentService extends IncidentService {
  _FakeIncidentService(this._result);

  final Incident? _result;

  @override
  Future<Incident?> getIncident(int id) async => _result;
}

void main() {
  final seed = Incident.fromJson({
    'id': 42,
    'customer_id': 7,
    'description': 'Water meter is leaking.',
    'location': 'Zone 4, Kamuning',
    'status': 'in_progress',
    'submitted_at': '2026-09-20T08:30:00+00:00',
    'customer': {
      'id': 7,
      'full_name': 'Robert Dela Cruz',
      'contact_no': '09171234567',
    },
    'assignments': [
      {
        'id': 9,
        'incident_id': 42,
        'team_leader_id': 3,
        'action_status': 'in_progress',
        'team_leader': {'id': 3, 'full_name': 'Team Leader Santos'},
      },
    ],
  });

  final assignment = Assignment.fromJson({
    'id': 9,
    'incident_id': 42,
    'team_leader_id': 3,
    'action_status': 'in_progress',
    'incident': {
      'id': 42,
      'customer_id': 7,
      'description': 'Water meter is leaking.',
      'location': 'Zone 4, Kamuning',
      'status': 'in_progress',
    },
    'team_leader': {'id': 3, 'full_name': 'Team Leader Santos'},
  });

  const customer = User(
    id: 7,
    fullName: 'Robert Dela Cruz',
    email: 'robert.j@example.com',
    role: 'customer',
  );

  const staff = User(
    id: 3,
    fullName: 'Team Leader Santos',
    email: 'santos@example.com',
    role: 'offsite_staff',
  );

  Widget pumpDetails(WidgetTester tester, Object? argument, User user) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthProvider>(
          create: (_) => _FakeAuthProvider(user),
        ),
        ChangeNotifierProvider<IncidentProvider>(
          create: (_) => IncidentProvider(),
        ),
        ChangeNotifierProvider<AssignmentProvider>(
          create: (_) => AssignmentProvider(),
        ),
      ],
      child: MaterialApp(
        onGenerateRoute: (settings) => MaterialPageRoute<void>(
          settings: settings,
          builder: (_) =>
              ViewDetailsScreen(service: _FakeIncidentService(seed)),
        ),
        initialRoute: '/viewdetails',
        onGenerateInitialRoutes: (initial) => [
          MaterialPageRoute<void>(
            settings: RouteSettings(name: initial, arguments: argument),
            builder: (_) =>
                ViewDetailsScreen(service: _FakeIncidentService(seed)),
          ),
        ],
      ),
    );
  }

  group('ViewDetailsScreen route handling', () {
    testWidgets(
        'pumps with an Incident argument without an inherited-widget error',
        (tester) async {
      // Regression: reading ModalRoute.of(context) from initState threw
      // "dependOnInheritedWidgetOfExactType<_ModalScopeStatus>() ... was
      // called before ... initState() completed".
      await tester.pumpWidget(pumpDetails(tester, seed, customer));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.text('Complaint Description'), findsOneWidget);
      expect(find.text('Water meter is leaking.'), findsOneWidget);
    });

    testWidgets('pumps with an Assignment argument', (tester) async {
      await tester.pumpWidget(pumpDetails(tester, assignment, staff));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.text('Water meter is leaking.'), findsOneWidget);
    });

    testWidgets('pumps when no argument is supplied', (tester) async {
      await tester.pumpWidget(pumpDetails(tester, null, customer));
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
    });

    testWidgets('shows the assigned offsite person and street location',
        (tester) async {
      await tester.pumpWidget(pumpDetails(tester, seed, customer));
      await tester.pumpAndSettle();

      // _FieldRow renders its label uppercased.
      expect(find.text('ASSIGNED OFFSITE PERSON'), findsOneWidget);
      expect(find.text('Team Leader Santos'), findsOneWidget);
      expect(find.text('LOCATION'), findsOneWidget);
      expect(find.text('Zone 4, Kamuning'), findsOneWidget);
    });

    testWidgets('shows complainant details to offsite staff', (tester) async {
      await tester.pumpWidget(pumpDetails(tester, seed, staff));
      await tester.pumpAndSettle();

      expect(find.text('COMPLAINANT NAME'), findsOneWidget);
      expect(find.text('Robert Dela Cruz'), findsOneWidget);
      expect(find.text('CP NUMBER'), findsOneWidget);
      expect(find.text('09171234567'), findsOneWidget);
    });

    testWidgets('hides complainant details from a customer', (tester) async {
      await tester.pumpWidget(pumpDetails(tester, seed, customer));
      await tester.pumpAndSettle();

      expect(find.text('CP NUMBER'), findsNothing);
    });

    testWidgets('never renders latitude or longitude', (tester) async {
      await tester.pumpWidget(pumpDetails(tester, seed, customer));
      await tester.pumpAndSettle();

      expect(find.textContaining('14.57'), findsNothing);
      expect(find.textContaining('121.03'), findsNothing);
    });
  });
}
