import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../providers/auth_provider.dart';

class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key});

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
                children: [
                  const MayniladLogo(size: 44),
                  const Expanded(
                    child: Center(
                      child: Text(
                        'ABOUT US',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.5,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 44), // balance logo
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
                        // Logo banner
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: AppColors.pageBg,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 52,
                                height: 52,
                                decoration: const BoxDecoration(
                                  color: AppColors.navy,
                                  shape: BoxShape.circle,
                                ),
                                child: const Center(child: MayniladLogo(size: 40)),
                              ),
                              const SizedBox(width: 14),
                              const Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Maynilad Water Services',
                                      style: TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w800,
                                        color: AppColors.navy,
                                      ),
                                    ),
                                    SizedBox(height: 3),
                                    Text(
                                      'Water Service Complaint System',
                                      style: TextStyle(fontSize: 11, color: AppColors.textMuted),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 18),

                        // Overview paragraphs
                        const Text(
                          'The Water Service Complaint System is a mobile application developed by Maynilad Water Services, Inc. to provide customers with a fast, convenient, and transparent way to report water service issues and track the resolution of their complaints in real time.',
                          style: TextStyle(fontSize: 12.5, color: Color(0xFF4B5563), height: 1.7),
                        ),
                        const SizedBox(height: 12),
                        const Text(
                          'Maynilad Water Services is the largest private water concessionaire in the Philippines, serving over 9 million customers across Metro Manila\'s west zone. Our commitment to service excellence drives every feature of this application.',
                          style: TextStyle(fontSize: 12.5, color: Color(0xFF4B5563), height: 1.7),
                        ),
                        const SizedBox(height: 22),

                        // Mission
                        const _SectionHeading(
                          title: 'Mission',
                          accentColor: AppColors.navy,
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'To provide safe, affordable, and reliable water and wastewater services to all customers in our service area, delivered with integrity, responsiveness, and respect for the environment and the communities we serve.',
                          style: TextStyle(fontSize: 12.5, color: Color(0xFF4B5563), height: 1.7),
                        ),
                        const SizedBox(height: 20),

                        // Vision
                        const _SectionHeading(
                          title: 'Vision',
                          accentColor: AppColors.orange,
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'To be the most trusted and customer-centric water utility in Southeast Asia — one that delivers 24/7 access to clean water, resolves service disruptions within hours, and continuously innovates for a more sustainable water future.',
                          style: TextStyle(fontSize: 12.5, color: Color(0xFF4B5563), height: 1.7),
                        ),
                        const SizedBox(height: 22),

                        // App version footer
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          decoration: BoxDecoration(
                            color: AppColors.pageBg,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            children: const [
                              Icon(Icons.info_outline, size: 16, color: AppColors.textMuted),
                              SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'v2.4.1 · © 2024 Maynilad Water Services, Inc.',
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.navy,
                                  ),
                                ),
                              ),
                            ],
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

class _SectionHeading extends StatelessWidget {
  final String title;
  final Color accentColor;

  const _SectionHeading({required this.title, required this.accentColor});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 4,
          height: 20,
          decoration: BoxDecoration(
            color: accentColor,
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        const SizedBox(width: 10),
        Text(
          title,
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: AppColors.navy,
          ),
        ),
      ],
    );
  }
}
