import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
  final int? maxLength;
  final double letterSpacing;
  final bool autofocus;
  final TextInputAction textInputAction;
  final ValueChanged<String>? onSubmitted;
  final List<TextInputFormatter>? inputFormatters;

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
    this.maxLength,
    this.letterSpacing = 0,
    this.autofocus = false,
    this.textInputAction = TextInputAction.next,
    this.onSubmitted,
    this.inputFormatters,
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
          autofocus: autofocus,
          maxLength: maxLength,
          textInputAction: textInputAction,
          onSubmitted: onSubmitted,
          inputFormatters: inputFormatters,
          style: TextStyle(
            fontSize: 13,
            color: AppColors.textDark,
            letterSpacing: letterSpacing,
            fontStyle: italic ? FontStyle.italic : FontStyle.normal,
          ),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: Color(0xFFADB5C0), fontSize: 13),
            filled: true,
            fillColor: AppColors.fieldBg,
            suffixIcon: suffixIcon,
            counterText: '',
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
