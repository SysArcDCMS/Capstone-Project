import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'theme/app_colors.dart';
import 'screens/login_screen.dart';
import 'screens/signup_screen.dart';
import 'screens/otp_screen.dart';
import 'screens/home_screen.dart';
import 'screens/complaint_screen.dart';
import 'screens/settings_screen.dart';
import 'screens/help_screen.dart';
import 'screens/account_screen.dart';
import 'screens/terms_screen.dart';
import 'screens/offsite_screen.dart';
import 'screens/view_details_screen.dart';
import 'screens/about_screen.dart';

import 'package:provider/provider.dart';
import 'app_globals.dart';
import 'providers/auth_provider.dart';
import 'providers/incident_provider.dart';
import 'providers/assignment_provider.dart';
import 'providers/notification_provider.dart';
import 'screens/notifications_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final authProvider = AuthProvider();
  await authProvider.init(); // restore session from stored JWT, wire 401 routing

  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: AppColors.navy,
      statusBarIconBrightness: Brightness.light,
      systemNavigationBarColor: AppColors.navy,
    ),
  );
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: authProvider),
        ChangeNotifierProvider(create: (_) => IncidentProvider()),
        ChangeNotifierProvider(create: (_) => AssignmentProvider()),
        ChangeNotifierProvider(create: (_) => NotificationProvider()),
      ],
      child: const MayniladApp(),
    ),
  );
}

class MayniladApp extends StatelessWidget {
  const MayniladApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Maynilad Water Services',
      debugShowCheckedModeBanner: false,
      navigatorKey: appNavigatorKey,
      theme: ThemeData(
        colorSchemeSeed: AppColors.navy,
        fontFamily: 'Inter',
        useMaterial3: true,
        scaffoldBackgroundColor: AppColors.pageBg,
        appBarTheme: const AppBarTheme(
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          elevation: 0,
        ),
      ),
      initialRoute: '/',
      routes: {
        '/':            (context) => const LoginScreen(),
        '/signup':      (context) => const SignUpScreen(),
        '/otp':         (context) => const OtpScreen(),
        '/home':        (context) => const HomeScreen(),
        '/complaint':   (context) => const ComplaintScreen(),
        '/settings':    (context) => const SettingsScreen(),
        '/help':        (context) => const HelpScreen(),
        '/account':     (context) => const AccountScreen(),
        '/terms':       (context) => const TermsScreen(),
        '/offsite':     (context) => const OffsiteScreen(),
        '/viewdetails': (context) => const ViewDetailsScreen(),
        '/about':       (context) => const AboutScreen(),
        '/notifications': (context) => const NotificationsScreen(),
      },
    );
  }
}
