import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';

class OtpScreen extends StatefulWidget {
  const OtpScreen({super.key});

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  final List<TextEditingController> _controllers =
      List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _focusNodes =
      List.generate(4, (_) => FocusNode());

  int _secondsLeft = 103; // 1:43
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  void _startTimer() {
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (_secondsLeft > 0) {
        setState(() => _secondsLeft--);
      } else {
        t.cancel();
      }
    });
  }

  String get _timerDisplay {
    final m = _secondsLeft ~/ 60;
    final s = (_secondsLeft % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  void dispose() {
    for (final c in _controllers) c.dispose();
    for (final f in _focusNodes) f.dispose();
    _timer?.cancel();
    super.dispose();
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
                      // Icon + title row
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 50,
                            height: 50,
                            decoration: BoxDecoration(
                              color: const Color(0xFFE8F0FE),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Icon(
                              Icons.email_outlined,
                              color: AppColors.navy,
                              size: 26,
                            ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Enter verification code from email',
                                  style: TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.navy,
                                    height: 1.35,
                                  ),
                                ),
                                const SizedBox(height: 7),
                                RichText(
                                  text: const TextSpan(
                                    text: 'Please enter the code we emailed you ',
                                    style: TextStyle(fontSize: 11.5, color: Color(0xFF6B7280), height: 1.5),
                                    children: [
                                      TextSpan(
                                        text: 'xxx.xxxxxx@gmail.com',
                                        style: TextStyle(
                                          color: AppColors.navy,
                                          fontWeight: FontWeight.w600,
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
                      const SizedBox(height: 30),

                      // OTP boxes
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: List.generate(4, (i) {
                          return Container(
                            width: 60,
                            height: 66,
                            margin: EdgeInsets.only(right: i < 3 ? 12 : 0),
                            decoration: BoxDecoration(
                              color: AppColors.fieldBg,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: i == 3 ? AppColors.navy : const Color(0xFFD1D5DB),
                                width: i == 3 ? 2 : 1.5,
                              ),
                            ),
                            child: Center(
                              child: TextField(
                                controller: _controllers[i],
                                focusNode: _focusNodes[i],
                                textAlign: TextAlign.center,
                                keyboardType: TextInputType.number,
                                maxLength: 1,
                                style: const TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.navy,
                                ),
                                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                                decoration: const InputDecoration(
                                  counterText: '',
                                  border: InputBorder.none,
                                  contentPadding: EdgeInsets.zero,
                                ),
                                onChanged: (val) {
                                  if (val.isNotEmpty && i < 3) {
                                    _focusNodes[i + 1].requestFocus();
                                  } else if (val.isEmpty && i > 0) {
                                    _focusNodes[i - 1].requestFocus();
                                  }
                                  setState(() {});
                                },
                              ),
                            ),
                          );
                        }),
                      ),
                      const SizedBox(height: 28),

                      // Continue button
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: () => Navigator.pushReplacementNamed(context, '/home'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.fieldBg,
                            foregroundColor: AppColors.navy,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: const StadiumBorder(),
                            elevation: 0,
                          ),
                          child: const Text(
                            'Continue →',
                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Timer
                      Center(
                        child: RichText(
                          text: TextSpan(
                            text: 'Resend code in ',
                            style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                            children: [
                              TextSpan(
                                text: _timerDisplay,
                                style: const TextStyle(
                                  color: AppColors.navy,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
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
