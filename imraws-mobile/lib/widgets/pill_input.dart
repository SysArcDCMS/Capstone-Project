import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

class PillInput extends StatelessWidget {
  final String label;
  final String? hint;
  final TextEditingController? controller;
  final bool obscureText;
  final bool required;
  final bool italic;
  final bool readOnly;
  final TextInputType keyboardType;
  final Widget? suffixIcon;

  const PillInput({
    super.key,
    required this.label,
    this.hint,
    this.controller,
    this.obscureText = false,
    this.required = false,
    this.italic = false,
    this.readOnly = false,
    this.keyboardType = TextInputType.text,
    this.suffixIcon,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        RichText(
          text: TextSpan(
            text: label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.textBody,
              fontStyle: italic ? FontStyle.italic : FontStyle.normal,
            ),
            children: required
                ? [
                    const TextSpan(
                      text: ' *',
                      style: TextStyle(color: AppColors.red),
                    )
                  ]
                : [],
          ),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: controller,
          obscureText: obscureText,
          keyboardType: keyboardType,
          readOnly: readOnly,
          style: TextStyle(
            fontSize: 13,
            color: AppColors.textDark,
            fontStyle: italic ? FontStyle.italic : FontStyle.normal,
          ),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: Color(0xFFADB5C0), fontSize: 13),
            filled: true,
            fillColor: AppColors.fieldBg,
            suffixIcon: suffixIcon,
            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 13),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(999),
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(999),
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(999),
              borderSide: const BorderSide(color: AppColors.navy, width: 1.5),
            ),
          ),
        ),
      ],
    );
  }
}
