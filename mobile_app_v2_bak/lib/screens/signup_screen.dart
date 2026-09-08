import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/pill_input.dart';
import '../providers/auth_provider.dart';

class SignUpScreen extends StatefulWidget {
  const SignUpScreen({super.key});

  @override
  State<SignUpScreen> createState() => _SignUpScreenState();
}

class _SignUpScreenState extends State<SignUpScreen> {
  final _nameController     = TextEditingController();
  final _emailController    = TextEditingController();
  final _contactController  = TextEditingController();
  final _addressController  = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController  = TextEditingController();

  bool _obscurePassword = true;
  bool _obscureConfirm  = true;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _contactController.dispose();
    _addressController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _handleSignUp() async {
    if (_passwordController.text != _confirmController.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Passwords do not match'), backgroundColor: AppColors.red),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    final result = await auth.register(
      fullName: _nameController.text.trim(),
      email: _emailController.text.trim(),
      password: _passwordController.text,
      contactNo: _contactController.text.trim(),
      address: _addressController.text.trim(),
    );

    if (!mounted) return;

    if (result['success']) {
      auth.navigateToHome(context);
    } else {
      final message = result['message'];
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(message.toString()),
          backgroundColor: AppColors.red,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.navy,
      body: SafeArea(
        child: Column(
          children: [
            // ── Header ──────────────────────────────────────────
            Padding(
              padding: const EdgeInsets.only(top: 36, bottom: 24),
              child: Column(
                children: [
                  const MayniladLogo(size: 58),
                  const SizedBox(height: 10),
                  const Text(
                    'MAYNILAD',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 2.5,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Water Services',
                    style: TextStyle(color: Colors.white.withOpacity(0.55), fontSize: 10.5),
                  ),
                ],
              ),
            ),

            // ── Card ────────────────────────────────────────────
            Expanded(
              child: Container(
                decoration: const BoxDecoration(
                  color: AppColors.cardBg,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'SIGN UP',
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: AppColors.navy,
                          letterSpacing: 1.2,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        'Please complete the setup of your login profile.',
                        style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                      ),
                      const SizedBox(height: 22),

                      PillInput(
                        label: 'Full Name:',
                        hint: 'Enter your full name',
                        controller: _nameController,
                        required: true,
                      ),
                      const SizedBox(height: 13),

                      PillInput(
                        label: 'Email Address:',
                        hint: 'Enter your email',
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        required: true,
                      ),
                      const SizedBox(height: 13),

                      PillInput(
                        label: 'Contact Number:',
                        hint: 'e.g. +639171234567',
                        controller: _contactController,
                        keyboardType: TextInputType.phone,
                      ),
                      const SizedBox(height: 13),

                      PillInput(
                        label: 'Address:',
                        hint: 'e.g. 123 Test St, Caloocan City',
                        controller: _addressController,
                      ),
                      const SizedBox(height: 13),

                      PillInput(
                        label: 'Password:',
                        hint: 'Create a password',
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        required: true,
                        suffixIcon: GestureDetector(
                          onTap: () => setState(() => _obscurePassword = !_obscurePassword),
                          child: Icon(
                            _obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                            size: 20,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ),
                      const SizedBox(height: 13),

                      PillInput(
                        label: 'Confirm Password:',
                        hint: 'Repeat your password',
                        controller: _confirmController,
                        obscureText: _obscureConfirm,
                        required: true,
                        suffixIcon: GestureDetector(
                          onTap: () => setState(() => _obscureConfirm = !_obscureConfirm),
                          child: Icon(
                            _obscureConfirm ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                            size: 20,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ),
                      const SizedBox(height: 26),

                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _handleSignUp,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.navy,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: const StadiumBorder(),
                            elevation: 0,
                          ),
                          child: context.watch<AuthProvider>().isLoading
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                )
                              : const Text(
                                  'SUBMIT',
                                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, letterSpacing: 1.5),
                                ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      GestureDetector(
                        onTap: () => Navigator.pop(context),
                        child: const Text(
                          '← Back',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: AppColors.blueLink,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
