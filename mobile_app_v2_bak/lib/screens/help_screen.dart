import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../providers/auth_provider.dart';

class HelpScreen extends StatefulWidget {
  const HelpScreen({super.key});

  @override
  State<HelpScreen> createState() => _HelpScreenState();
}

class _HelpScreenState extends State<HelpScreen> {
  int? _openIndex = 0;

  static const _faqs = [
    _Faq(
      question: 'How do I log in to the Maynilad app?',
      answer:
          "Open the app and enter your registered email address and password on the Login screen. Tap 'LOG IN' to access your dashboard. If you're a new user, tap 'Sign Up' to create an account first.",
    ),
    _Faq(
      question: 'What should I do if I forgot my password?',
      answer:
          "On the login screen, tap 'Forgot Password?' and enter your registered email address. You will receive a reset link within a few minutes. Check your spam folder if it does not arrive promptly.",
    ),
    _Faq(
      question: 'How do I report low water pressure?',
      answer:
          "Tap the '+' button in the center of the bottom navigation bar to open the Submit a Complaint screen. Select 'Low Pressure' as the complaint type, describe the issue, and tap SUBMIT. Our team will respond within 24–48 hours.",
    ),
    _Faq(
      question: 'How can I track my complaint status?',
      answer:
          'Your active complaint status is visible on the Home dashboard under the Status section. Historical complaints are listed below under the History section with their current resolution status.',
    ),
    _Faq(
      question: 'Who can I contact for urgent concerns?',
      answer:
          'For urgent water service concerns, you may call the Maynilad 24-hour hotline at 1626 or send an email to customercare@maynilad.com.ph. Emergency field crews are dispatched around the clock.',
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
                  Text(
                    'HELP CENTER',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.5,
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
                    decoration: BoxDecoration(
                      color: AppColors.cardBg,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF0A3B71).withOpacity(0.08),
                          blurRadius: 12,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Section header
                        Container(
                          padding: const EdgeInsets.fromLTRB(20, 14, 20, 12),
                          decoration: const BoxDecoration(
                            border: Border(bottom: BorderSide(color: AppColors.divider)),
                          ),
                          child: const Text(
                            'FREQUENTLY ASKED QUESTIONS',
                            style: TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w700,
                              color: AppColors.textMuted,
                              letterSpacing: 1.2,
                            ),
                          ),
                        ),

                        // Accordions
                        ..._faqs.asMap().entries.map((e) {
                          final i = e.key;
                          final faq = e.value;
                          final isOpen = _openIndex == i;

                          return Column(
                            children: [
                              InkWell(
                                onTap: () => setState(() => _openIndex = isOpen ? null : i),
                                child: Padding(
                                  padding: const EdgeInsets.fromLTRB(20, 15, 20, 15),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.center,
                                    children: [
                                      Expanded(
                                        child: Text(
                                          faq.question,
                                          style: const TextStyle(
                                            fontSize: 12.5,
                                            fontWeight: FontWeight.w700,
                                            color: AppColors.navy,
                                            height: 1.4,
                                          ),
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                      AnimatedRotation(
                                        turns: isOpen ? 0.5 : 0,
                                        duration: const Duration(milliseconds: 200),
                                        child: const Icon(
                                          Icons.keyboard_arrow_down,
                                          color: AppColors.navy,
                                          size: 20,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                              AnimatedCrossFade(
                                firstChild: const SizedBox.shrink(),
                                secondChild: Padding(
                                  padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
                                  child: Text(
                                    faq.answer,
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: Color(0xFF6B7280),
                                      height: 1.65,
                                    ),
                                  ),
                                ),
                                crossFadeState: isOpen
                                    ? CrossFadeState.showSecond
                                    : CrossFadeState.showFirst,
                                duration: const Duration(milliseconds: 200),
                              ),
                              if (i < _faqs.length - 1)
                                const Divider(height: 1, color: AppColors.divider),
                            ],
                          );
                        }),
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

class _Faq {
  final String question;
  final String answer;

  const _Faq({required this.question, required this.answer});
}
