import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../providers/auth_provider.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final items = <_SettingsItem>[
      _SettingsItem(
        icon: Icons.notifications_outlined,
        label: 'Notifications',
        onTap: () => Navigator.pushNamed(context, '/notifications'),
      ),
      _SettingsItem(
        icon: Icons.person_outline,
        label: 'Account Settings',
        onTap: () => Navigator.pushNamed(context, '/account'),
      ),
      _SettingsItem(
        icon: Icons.help_outline,
        label: 'Help',
        onTap: () => Navigator.pushNamed(context, '/help'),
      ),
      _SettingsItem(
        icon: Icons.info_outline,
        label: 'About Us',
        onTap: () => Navigator.pushNamed(context, '/about'),
      ),
      _SettingsItem(
        icon: Icons.description_outlined,
        label: 'Terms & Conditions',
        onTap: () => Navigator.pushNamed(context, '/terms'),
      ),
    ];

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
                    'SETTINGS',
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
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                child: Column(
                  children: [
                    // Menu card
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
                        children: items.asMap().entries.map((e) {
                          final i = e.key;
                          final item = e.value;
                          return Column(
                            children: [
                              InkWell(
                                onTap: item.onTap,
                                borderRadius: BorderRadius.circular(20),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                                  child: Row(
                                    children: [
                                      Container(
                                        width: 44,
                                        height: 44,
                                        decoration: BoxDecoration(
                                          shape: BoxShape.circle,
                                          border: Border.all(color: const Color(0xFFD1D5DB), width: 1.5),
                                        ),
                                        child: Icon(item.icon, color: AppColors.navy, size: 22),
                                      ),
                                      const SizedBox(width: 16),
                                      Expanded(
                                        child: Text(
                                          item.label,
                                          style: const TextStyle(
                                            fontSize: 14,
                                            fontWeight: FontWeight.w600,
                                            color: AppColors.textDark,
                                          ),
                                        ),
                                      ),
                                      const Icon(Icons.chevron_right, color: Color(0xFFC0C9D4), size: 20),
                                    ],
                                  ),
                                ),
                              ),
                              if (i < items.length - 1)
                                const Divider(
                                  height: 1,
                                  color: AppColors.divider,
                                  indent: 80,
                                ),
                            ],
                          );
                        }).toList(),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Logout button
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () async {
                          final auth = context.read<AuthProvider>();
                          await auth.logout();
                          if (!context.mounted) return;
                          Navigator.pushNamedAndRemoveUntil(context, '/', (r) => false);
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.red,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 15),
                          shape: const StadiumBorder(),
                          elevation: 4,
                          shadowColor: AppColors.red.withOpacity(0.4),
                        ),
                        child: const Text(
                          'LOGOUT',
                          style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, letterSpacing: 1.5),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            MayniladBottomNav(
              active: NavTab.settings,
              onHome: () => context.read<AuthProvider>().navigateToHome(context),
              onReport: () => Navigator.pushNamed(context, '/complaint'),
              onSettings: () {},
              isCustomer: context.watch<AuthProvider>().isCustomer,
            ),
          ],
        ),
      ),
    );
  }
}

class _SettingsItem {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _SettingsItem({required this.icon, required this.label, required this.onTap});
}
