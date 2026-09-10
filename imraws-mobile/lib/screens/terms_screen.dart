import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../providers/auth_provider.dart';

class TermsScreen extends StatelessWidget {
  const TermsScreen({super.key});

  static const _clauses = [
    _Clause(
      title: 'Use of the Application',
      body:
          'This application is intended solely for registered Maynilad Water Services customers. By using this system, you agree that you will only submit complaints related to legitimate water service issues within your registered service area.',
    ),
    _Clause(
      title: 'Accurate Information',
      body:
          'You agree to provide truthful, accurate, and complete information when submitting a complaint. Submitting false or misleading information may result in suspension of your account and may be subject to applicable legal penalties.',
    ),
    _Clause(
      title: 'Acceptable Use',
      body:
          'You must not misuse this application by submitting spam, duplicate, or frivolous complaints. Maynilad reserves the right to remove complaints that violate these guidelines and to suspend accounts that engage in abuse of the reporting system.',
    ),
    _Clause(
      title: 'Privacy and Data Protection',
      body:
          'Your personal data collected through this application is handled in accordance with the Data Privacy Act of 2012 (Republic Act 10173). We collect only the data necessary to process your complaint and will not share your information with unauthorized third parties.',
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.pageBg,
      body: SafeArea(
        child: Column(
          children: [
            // ── Header ──────────────────────────────────────────
            Container(
              color: AppColors.navy,
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 18),
              child: Row(
                children: const [
                  MayniladLogo(size: 44),
                  SizedBox(width: 16),
                  Expanded(
                    child: Text(
                      'TERMS & CONDITIONS',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.2,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // ── Body ────────────────────────────────────────────
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: AppColors.cardBg,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.navy.withOpacity(0.08),
                          blurRadius: 12,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Top grey rule
                        Container(
                          height: 4,
                          decoration: BoxDecoration(
                            color: const Color(0xFFE5E7EB),
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                        const SizedBox(height: 16),

                        // Intro text
                        const Text(
                          'Please read the following terms and conditions carefully before using the Water Service Complaint System.',
                          style: TextStyle(
                            fontSize: 12.5,
                            color: Color(0xFF4B5563),
                            height: 1.65,
                          ),
                        ),
                        const SizedBox(height: 22),

                        // Numbered clauses
                        ..._clauses.asMap().entries.map((e) {
                          final index = e.key;
                          final clause = e.value;
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 18),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Circle number
                                Container(
                                  width: 34,
                                  height: 34,
                                  decoration: const BoxDecoration(
                                    color: AppColors.navy,
                                    shape: BoxShape.circle,
                                  ),
                                  child: Center(
                                    child: Text(
                                      '${index + 1}',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const SizedBox(height: 4),
                                      Text(
                                        clause.title,
                                        style: const TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.navy,
                                        ),
                                      ),
                                      const SizedBox(height: 5),
                                      Text(
                                        clause.body,
                                        style: const TextStyle(
                                          fontSize: 11.5,
                                          color: Color(0xFF4B5563),
                                          height: 1.65,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          );
                        }),

                        // Acknowledgement box
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: AppColors.pageBg,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text(
                            'By continuing to use this application, you acknowledge that you have read and agree to these Terms & Conditions.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 11,
                              color: Color(0xFF6B7280),
                              height: 1.55,
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),

                        // I Agree button
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: () => Navigator.pop(context),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.navy,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 15),
                              shape: const StadiumBorder(),
                              elevation: 0,
                            ),
                            child: const Text(
                              'I AGREE',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 1.5,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            MayniladBottomNav(
              active: NavTab.settings,
              onHome: () => context.read<AuthProvider>().navigateToHome(context),
              onReport: () => Navigator.pushNamed(context, '/complaint'),
              onSettings: () => Navigator.pushReplacementNamed(context, '/settings'),
              isCustomer: context.watch<AuthProvider>().isCustomer,
            ),
          ],
        ),
      ),
    );
  }
}

class _Clause {
  final String title;
  final String body;
  const _Clause({required this.title, required this.body});
}
