import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/pill_input.dart';

/// Two-step password recovery driven by an emailed 6-digit code.
///
/// Step 1 asks the server to mail a code, step 2 spends it. The server answers
/// step 1 identically for unknown addresses, so the wording here never claims
/// an account exists — it only confirms the request was accepted.
class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _emailController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();

  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  bool _codeSent = false;
  int _resendSeconds = 0;

  Timer? _resendTimer;

  /// UI-side cooldown between code requests. The endpoint allows 3 per minute;
  /// this keeps the user from discovering that by being rejected.
  static const int _resendCooldownSeconds = 45;

  @override
  void dispose() {
    _resendTimer?.cancel();
    _emailController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  void _startResendCountdown() {
    _resendTimer?.cancel();
    setState(() => _resendSeconds = _resendCooldownSeconds);

    _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      setState(() => _resendSeconds--);
      if (_resendSeconds <= 0) {
        timer.cancel();
      }
    });
  }

  Future<void> _sendCode() async {
    final email = _emailController.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      _toast('Enter the email address on your account.', AppColors.orange);
      return;
    }

    final auth = context.read<AuthProvider>();
    final result = await auth.requestPasswordReset(email);

    if (!mounted) return;

    if (result['success'] == true) {
      setState(() => _codeSent = true);
      _startResendCountdown();
      _toast('If that email is registered, a code is on its way.', AppColors.green);
    } else {
      _toast(result['message'] as String? ?? 'Could not send the code.', AppColors.red);
    }
  }

  Future<void> _submit() async {
    final code = _codeController.text.trim();
    final password = _passwordController.text;

    if (code.length != 6) {
      _toast('Enter the 6-digit code from the email.', AppColors.orange);
      return;
    }
    if (password.length < 8) {
      _toast('Password must be at least 8 characters.', AppColors.orange);
      return;
    }
    if (password != _confirmController.text) {
      _toast('The two passwords do not match.', AppColors.orange);
      return;
    }

    final auth = context.read<AuthProvider>();
    final result = await auth.resetPassword(
      email: _emailController.text.trim(),
      code: code,
      newPassword: password,
    );

    if (!mounted) return;

    if (result['success'] == true) {
      _toast(result['message'] as String? ?? 'Password updated.', AppColors.green);
      // Back to the login form, which is now the only sensible next step.
      Navigator.of(context).pop();
    } else {
      _toast(result['message'] as String? ?? 'Could not reset the password.', AppColors.red);
    }
  }

  void _toast(String message, Color color) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: color),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isLoading = context.watch<AuthProvider>().isLoading;

    return Scaffold(
      backgroundColor: AppColors.navy,
      appBar: AppBar(
        backgroundColor: AppColors.navy,
        title: const Text(
          'Reset Password',
          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
        ),
      ),
      body: SafeArea(
        child: Container(
          decoration: const BoxDecoration(
            color: AppColors.cardBg,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Column(
                    children: [
                      const MayniladLogo(size: 52),
                      const SizedBox(height: 14),
                      Text(
                        _codeSent ? 'Enter your code' : 'Forgot your password?',
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: AppColors.navy,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _codeSent
                            ? 'We emailed a 6-digit code to\n${_emailController.text.trim()}'
                            : 'Enter your email and we will send you a code\nto choose a new password.',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 13, color: Colors.grey[600], height: 1.5),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 26),

                PillInput(
                  label: 'Email Address:',
                  hint: 'Enter your email',
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  readOnly: _codeSent,
                ),
                const SizedBox(height: 16),

                if (_codeSent) ...[
                  PillInput(
                    label: '6-Digit Code:',
                    hint: '000000',
                    controller: _codeController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    maxLength: 6,
                    letterSpacing: 8,
                    autofocus: true,
                    required: true,
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 16),

                  PillInput(
                    label: 'New Password:',
                    hint: 'At least 8 characters',
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
                  const SizedBox(height: 16),

                  PillInput(
                    label: 'Confirm New Password:',
                    hint: 'Re-enter your new password',
                    controller: _confirmController,
                    obscureText: _obscureConfirm,
                    required: true,
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => isLoading ? null : _submit(),
                    suffixIcon: GestureDetector(
                      onTap: () => setState(() => _obscureConfirm = !_obscureConfirm),
                      child: Icon(
                        _obscureConfirm ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                        size: 20,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),

                  // Resend, held back briefly so the endpoint's own rate
                  // limit is not the thing the user runs into.
                  Center(
                    child: _resendSeconds > 0
                        ? Text(
                            'You can request another code in ${_resendSeconds}s',
                            style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                          )
                        : TextButton(
                            onPressed: isLoading ? null : _sendCode,
                            child: const Text(
                              'Resend code',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: AppColors.blueLink,
                              ),
                            ),
                          ),
                  ),
                ],

                const SizedBox(height: 8),

                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: isLoading ? null : (_codeSent ? _submit : _sendCode),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.navy,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 15),
                      shape: const StadiumBorder(),
                      elevation: 0,
                    ),
                    child: isLoading
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                          )
                        : Text(
                            _codeSent ? 'SET NEW PASSWORD' : 'SEND CODE',
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 1.2,
                            ),
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
