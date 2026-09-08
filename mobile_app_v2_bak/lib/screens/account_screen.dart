import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/pill_input.dart';
import '../widgets/bottom_nav_bar.dart';
import '../providers/auth_provider.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  late TextEditingController _nameController;
  late TextEditingController _emailController;
  late TextEditingController _contactController;
  late TextEditingController _addressController;
  final _passwordController = TextEditingController(text: '••••••••••');

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _nameController = TextEditingController(text: user?.fullName);
    _emailController = TextEditingController(text: user?.email);
    _contactController = TextEditingController(text: user?.contactNo);
    _addressController = TextEditingController(text: user?.address);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _contactController.dispose();
    _addressController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleSave() async {
    final auth = context.read<AuthProvider>();
    final result = await auth.updateProfile(
      fullName: _nameController.text.trim(),
      contactNo: _contactController.text.trim(),
      address: _addressController.text.trim(),
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          result['success'] == true ? 'Changes saved successfully.' : result['message'] as String,
        ),
        backgroundColor:
            result['success'] == true ? AppColors.navy : AppColors.red,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    
    return Scaffold(
      backgroundColor: AppColors.pageBg,
      body: SafeArea(
        child: Column(
          children: [
            // ── Header ──────────────────────────────────────────
            Container(
              color: AppColors.navy,
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 18),
              child: const Row(
                children: [
                  MayniladLogo(size: 44),
                  SizedBox(width: 16),
                  Text(
                    'ACCOUNT SETTINGS',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.2,
                    ),
                  ),
                ],
              ),
            ),

            // ── Body ────────────────────────────────────────────
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                child: Container(
                  padding: const EdgeInsets.all(20),
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
                      // Avatar row
                      Container(
                        padding: const EdgeInsets.only(bottom: 18),
                        decoration: const BoxDecoration(
                          border: Border(bottom: BorderSide(color: AppColors.divider)),
                        ),
                        child: Row(
                          children: [
                            CircleAvatar(
                              radius: 28,
                              backgroundColor: AppColors.navy,
                              child: Text(
                                user?.fullName.isNotEmpty == true ? user!.fullName[0].toUpperCase() : '?',
                                style: const TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.white,
                                ),
                              ),
                            ),
                            const SizedBox(width: 14),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  user?.fullName ?? '',
                                  style: const TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.navy,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  '${user?.role.toUpperCase()} Account',
                                  style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 18),

                      // Fields
                      PillInput(
                        label: 'Complete Name:',
                        controller: _nameController,
                        italic: true,
                      ),
                      const SizedBox(height: 13),

                      PillInput(
                        label: 'Email Address:',
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        italic: true,
                        readOnly: true,
                      ),
                      const SizedBox(height: 13),

                      PillInput(
                        label: 'Contact Number:',
                        controller: _contactController,
                        keyboardType: TextInputType.phone,
                        italic: true,
                      ),
                      const SizedBox(height: 13),

                      PillInput(
                        label: 'Address:',
                        controller: _addressController,
                        italic: true,
                      ),
                      const SizedBox(height: 20),

                      // Save button
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _handleSave,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.navy,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: const StadiumBorder(),
                            elevation: 0,
                          ),
                          child: const Text(
                            'SAVE CHANGES',
                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, letterSpacing: 1.5),
                          ),
                        ),
                      ),
                      
                      const SizedBox(height: 16),
                      
                      // Logout button
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton(
                          onPressed: () async {
                            final auth = context.read<AuthProvider>();
                            await auth.logout();
                            if (!context.mounted) return;
                            Navigator.pushNamedAndRemoveUntil(context, '/', (route) => false);
                          },
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: const StadiumBorder(),
                            side: const BorderSide(color: AppColors.red),
                          ),
                          child: const Text(
                            'LOG OUT',
                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.red),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
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
