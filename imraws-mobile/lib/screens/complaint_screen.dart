import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:provider/provider.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../providers/auth_provider.dart';
import '../providers/incident_provider.dart';
import '../widgets/confirm_dialog.dart';
import 'map_picker_screen.dart';

class ComplaintScreen extends StatefulWidget {
  const ComplaintScreen({super.key});

  @override
  State<ComplaintScreen> createState() => _ComplaintScreenState();
}

class _ComplaintScreenState extends State<ComplaintScreen> {
  final _controller = TextEditingController();
  final _locationController = TextEditingController();
  LatLng? _pickedLocation;

  @override
  void dispose() {
    _controller.dispose();
    _locationController.dispose();
    super.dispose();
  }

  Future<void> _pickLocation() async {
    final result = await Navigator.push<LatLng>(
      context,
      MaterialPageRoute(
        builder: (_) => MapPickerScreen(initial: _pickedLocation),
      ),
    );
    if (result != null && mounted) {
      setState(() => _pickedLocation = result);
    }
  }

  Future<void> _handleSubmit() async {
    final description = _controller.text.trim();
    if (description.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a description'), backgroundColor: AppColors.red),
      );
      return;
    }

    final proceed = await confirmAction(
      context,
      title: 'Submit Complaint?',
      message: 'Your complaint will be submitted, analyzed, and routed to the appropriate department.',
      confirmLabel: 'Submit',
    );
    if (proceed != true) return;
    if (!mounted) return;

    final provider = context.read<IncidentProvider>();
    final result = await provider.submitComplaint(
      description,
      _locationController.text.trim().isEmpty
          ? null
          : _locationController.text.trim(),
      latitude: _pickedLocation?.latitude,
      longitude: _pickedLocation?.longitude,
    );

    if (!mounted) return;

    final message = StringBuffer(result['message'] is String ? result['message'] as String : '');
    if (result['success'] && result['analysis'] is Map<String, dynamic>) {
      final analysis = result['analysis'] as Map<String, dynamic>;
      message.write(
        '\nClassified as ${analysis['category'] ?? 'unknown'} · '
        'Severity ${analysis['severity'] ?? 'unknown'}',
      );
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message.toString()),
        backgroundColor: result['success'] == true ? AppColors.green : AppColors.red,
      ),
    );
    if (result['success'] == true) {
      Navigator.pop(context);
    }
  }

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
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 14),
                    child: Row(
                      children: [
                        const MayniladLogo(size: 44),
                        const Spacer(),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              'Good morning,',
                              style: TextStyle(color: Colors.white.withOpacity(0.55), fontSize: 11),
                            ),
                            Text(
                              'Hello, ${context.read<AuthProvider>().user?.fullName ?? 'Client'}!',
                              style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.only(bottom: 16),
                    child: Column(
                      children: [
                        Text(
                          'SUBMIT A COMPLAINT',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 2,
                          ),
                        ),
                        SizedBox(height: 8),
                        SizedBox(
                          width: 40,
                          child: Divider(color: Colors.white38, thickness: 2.5),
                        ),
                      ],
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
                      const Text(
                        'Put Your Complaint:',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textBody,
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Text field
                      TextField(
                        controller: _controller,
                        maxLines: 7,
                        style: const TextStyle(fontSize: 13, color: AppColors.textDark, height: 1.6),
                        decoration: InputDecoration(
                          hintText: 'Describe the issue in detail…',
                          hintStyle: const TextStyle(color: AppColors.textMuted, fontSize: 13),
                          filled: true,
                          fillColor: AppColors.fieldBg,
                          contentPadding: const EdgeInsets.all(16),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(16),
                            borderSide: BorderSide.none,
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(16),
                            borderSide: const BorderSide(color: AppColors.navy, width: 1.5),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Location field
                      TextField(
                        controller: _locationController,
                        style: const TextStyle(fontSize: 13, color: AppColors.textDark),
                        decoration: InputDecoration(
                          hintText: 'Location / Barangay / City (optional)…',
                          hintStyle: const TextStyle(color: AppColors.textMuted, fontSize: 13),
                          prefixIcon: const Icon(
                            Icons.location_on_outlined,
                            size: 18,
                            color: AppColors.textMuted,
                          ),
                          filled: true,
                          fillColor: AppColors.fieldBg,
                          contentPadding: const EdgeInsets.symmetric(vertical: 14),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(999),
                            borderSide: BorderSide.none,
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(999),
                            borderSide: const BorderSide(color: AppColors.navy, width: 1.5),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Pin on map (draggable Google Maps marker).
                      InkWell(
                        onTap: _pickLocation,
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          decoration: BoxDecoration(
                            color: _pickedLocation == null
                                ? AppColors.fieldBg
                                : const Color(0xFFE8F0FB),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            children: [
                              Icon(
                                _pickedLocation == null
                                    ? Icons.location_on_outlined
                                    : Icons.location_on,
                                size: 18,
                                color: _pickedLocation == null
                                    ? AppColors.textMuted
                                    : AppColors.navy,
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  _pickedLocation == null
                                      ? 'Set Location on Map (optional)'
                                      : 'Pin at ${_pickedLocation!.latitude.toStringAsFixed(5)}, ${_pickedLocation!.longitude.toStringAsFixed(5)}',
                                  style: TextStyle(
                                    fontSize: 13,
                                    color: _pickedLocation == null
                                        ? AppColors.textMuted
                                        : AppColors.navyDark,
                                    fontWeight: _pickedLocation == null
                                        ? FontWeight.w400
                                        : FontWeight.w600,
                                  ),
                                ),
                              ),
                              if (_pickedLocation != null)
                                GestureDetector(
                                  onTap: () => setState(() => _pickedLocation = null),
                                  child: const Icon(Icons.close, size: 16, color: AppColors.textMuted),
                                ),
                              if (_pickedLocation == null)
                                const Icon(Icons.chevron_right, size: 18, color: AppColors.textMuted),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _handleSubmit,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.navy,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: const StadiumBorder(),
                            elevation: 0,
                          ),
                          child: context.watch<IncidentProvider>().isLoading
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
                    ],
                  ),
                ),
              ),
            ),

            MayniladBottomNav(
              active: NavTab.home,
              onHome: () => context.read<AuthProvider>().navigateToHome(context),
              onReport: () {},
              onSettings: () => Navigator.pushNamed(context, '/settings'),
              isCustomer: context.watch<AuthProvider>().isCustomer,
            ),
          ],
        ),
      ),
    );
  }
}