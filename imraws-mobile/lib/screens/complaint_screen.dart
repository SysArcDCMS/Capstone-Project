import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import '../models/attachment_model.dart';
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

  /// Photos the customer picked to show the problem, in the order added.
  final List<ComplaintPhoto> _photos = [];
  bool _picking = false;

  /// Street text resolved by the picker for the current pin. Kept apart from
  /// [_locationController] so the summary row can echo what the pin resolved to
  /// without echoing coordinates.
  String? _pickedAddress;

  @override
  void dispose() {
    _controller.dispose();
    _locationController.dispose();
    super.dispose();
  }

  /// Add photos of the problem, up to the server's limit.
  ///
  /// Re-encoded as JPEG rather than uploaded as picked: a modern phone hands
  /// back HEIC, which the server rejects because it only accepts JPEG, PNG, and
  /// WebP. Re-encoding here means a customer on a default camera setting is not
  /// turned away for choosing the wrong format, and it strips location metadata
  /// from the picture on the way.
  Future<void> _addPhotos() async {
    if (_picking) return;

    final remaining = ComplaintPhoto.maxPhotos - _photos.length;
    if (remaining <= 0) {
      _toast('You can attach up to ${ComplaintPhoto.maxPhotos} photos.');
      return;
    }

    setState(() => _picking = true);

    try {
      final picked = await ImagePicker().pickMultiImage(
        limit: remaining,
        maxWidth: 1920,
        maxHeight: 1920,
        imageQuality: 85,
      );

      if (picked.isEmpty || !mounted) return;

      final added = <ComplaintPhoto>[];

      for (final file in picked.take(remaining)) {
        final bytes = await file.readAsBytes();
        if (bytes.isEmpty) continue;
        added.add(ComplaintPhoto.jpg(bytes: bytes));
      }

      if (added.isEmpty) return;

      setState(() => _photos.addAll(added));
    } catch (_) {
      // Permission denied, or the picker was dismissed by the system. The
      // complaint is still submittable without photos, so this is a message
      // rather than a blocking error.
      if (mounted) _toast('Could not open your photos.');
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  void _removePhotoAt(int index) {
    setState(() => _photos.removeAt(index));
  }

  void _toast(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _pickLocation() async {
    final result = await Navigator.push<PickedLocation>(
      context,
      MaterialPageRoute(
        builder: (_) => MapPickerScreen(initial: _pickedLocation),
      ),
    );
    if (result != null && mounted) {
      setState(() {
        _pickedLocation = result.point;
        // Fill the address field from the pin, but never overwrite one the
        // user typed themselves: a typed barangay or landmark is more specific
        // than whatever the device geocoder infers from the coordinates.
        final resolved = result.address;
        _pickedAddress =
            (resolved == null || resolved.isEmpty) ? null : resolved;
        if (resolved != null &&
            resolved.isNotEmpty &&
            _locationController.text.trim().isEmpty) {
          _locationController.text = resolved;
        }
      });
    }
  }

  Future<void> _handleSubmit() async {
    final description = _controller.text.trim();
    if (description.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Please enter a description'),
            backgroundColor: AppColors.red),
      );
      return;
    }

    final proceed = await confirmAction(
      context,
      title: 'Submit Complaint?',
      message: _photos.isEmpty
          ? 'Your complaint will be submitted, analyzed, and routed to the appropriate department.'
          : 'Your complaint and ${_photos.length} photo'
              '${_photos.length == 1 ? '' : 's'} will be submitted, analyzed, and routed to the appropriate department.',
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
      photos: List.unmodifiable(_photos),
    );

    if (!mounted) return;

    final message = StringBuffer(
        result['message'] is String ? result['message'] as String : '');
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
        backgroundColor:
            result['success'] == true ? AppColors.green : AppColors.red,
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
                              style: TextStyle(
                                  color: Colors.white.withOpacity(0.55),
                                  fontSize: 11),
                            ),
                            Text(
                              'Hello, ${context.read<AuthProvider>().user?.fullName ?? 'Client'}!',
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700),
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
                        style: const TextStyle(
                            fontSize: 13,
                            color: AppColors.textDark,
                            height: 1.6),
                        decoration: InputDecoration(
                          hintText: 'Describe the issue in detail…',
                          hintStyle: const TextStyle(
                              color: AppColors.textMuted, fontSize: 13),
                          filled: true,
                          fillColor: AppColors.fieldBg,
                          contentPadding: const EdgeInsets.all(16),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(16),
                            borderSide: BorderSide.none,
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(16),
                            borderSide: const BorderSide(
                                color: AppColors.navy, width: 1.5),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Photos of the problem. Optional, and sent with the
                      // complaint so there is no second request to retry.
                      _PhotoPicker(
                        photos: _photos,
                        busy: _picking,
                        onAdd: _addPhotos,
                        onRemove: _removePhotoAt,
                      ),
                      const SizedBox(height: 12),

                      // Location field
                      TextField(
                        controller: _locationController,
                        style: const TextStyle(
                            fontSize: 13, color: AppColors.textDark),
                        decoration: InputDecoration(
                          hintText: 'Location / Barangay / City (optional)…',
                          hintStyle: const TextStyle(
                              color: AppColors.textMuted, fontSize: 13),
                          prefixIcon: const Icon(
                            Icons.location_on_outlined,
                            size: 18,
                            color: AppColors.textMuted,
                          ),
                          filled: true,
                          fillColor: AppColors.fieldBg,
                          contentPadding:
                              const EdgeInsets.symmetric(vertical: 14),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(999),
                            borderSide: BorderSide.none,
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(999),
                            borderSide: const BorderSide(
                                color: AppColors.navy, width: 1.5),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Pin on map (opens the OpenFreeMap picker).
                      InkWell(
                        onTap: _pickLocation,
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 10),
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
                                      : (_pickedAddress ??
                                          'Location set on map'),
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
                                  onTap: () => setState(() {
                                    _pickedLocation = null;
                                    _pickedAddress = null;
                                  }),
                                  child: const Icon(Icons.close,
                                      size: 16, color: AppColors.textMuted),
                                ),
                              if (_pickedLocation == null)
                                const Icon(Icons.chevron_right,
                                    size: 18, color: AppColors.textMuted),
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
                                  child: CircularProgressIndicator(
                                      color: Colors.white, strokeWidth: 2),
                                )
                              : const Text(
                                  'SUBMIT',
                                  style: TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                      letterSpacing: 1.5),
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
              onHome: () =>
                  context.read<AuthProvider>().navigateToHome(context),
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

/// Optional photo attachments for a complaint.
///
/// Shows the picked photos as removable thumbnails and an add tile. The count
/// in the label tracks the server limit, so a customer learns the cap before
/// picking rather than from a failed upload.
class _PhotoPicker extends StatelessWidget {
  final List<ComplaintPhoto> photos;
  final bool busy;
  final VoidCallback onAdd;
  final ValueChanged<int> onRemove;

  const _PhotoPicker({
    required this.photos,
    required this.busy,
    required this.onAdd,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    final atLimit = photos.length >= ComplaintPhoto.maxPhotos;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(Icons.add_a_photo_outlined,
                size: 18, color: AppColors.textMuted),
            const SizedBox(width: 8),
            const Text(
              'Photos of the problem (optional)',
              style: TextStyle(
                color: AppColors.textDark,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
            const Spacer(),
            Text(
              '${photos.length}/${ComplaintPhoto.maxPhotos}',
              style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
            ),
          ],
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 84,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            // Keep the add tile reachable by scrolling, rather than overflowing
            // once three thumbnails are in place.
            physics: const BouncingScrollPhysics(),
            itemCount: atLimit ? photos.length : photos.length + 1,
            separatorBuilder: (_, __) => const SizedBox(width: 10),
            itemBuilder: (context, index) {
              if (index == photos.length) {
                return _AddTile(
                  onTap: busy || atLimit ? null : onAdd,
                  busy: busy,
                  atLimit: atLimit,
                );
              }
              return _PhotoTile(
                photo: photos[index],
                onRemove: () => onRemove(index),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _AddTile extends StatelessWidget {
  final VoidCallback? onTap;
  final bool busy;
  final bool atLimit;

  const _AddTile({required this.onTap, required this.busy, required this.atLimit});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 84,
        height: 84,
        decoration: BoxDecoration(
          color: AppColors.fieldBg,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Center(
          child: busy
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Icon(
                  atLimit ? Icons.block : Icons.add_photo_alternate_outlined,
                  size: 22,
                  color: atLimit ? AppColors.textMuted : AppColors.navy,
                ),
        ),
      ),
    );
  }
}

class _PhotoTile extends StatelessWidget {
  final ComplaintPhoto photo;
  final VoidCallback onRemove;

  const _PhotoTile({required this.photo, required this.onRemove});

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(14),
          child: Image.memory(
            photo.bytes,
            width: 84,
            height: 84,
            fit: BoxFit.cover,
            // A photo that will not decode is not worth blocking the complaint
            // on; show the broken tile and let the customer remove it.
            errorBuilder: (_, __, ___) => Container(
              width: 84,
              height: 84,
              color: AppColors.fieldBg,
              child: const Icon(Icons.broken_image_outlined,
                  size: 20, color: AppColors.textMuted),
            ),
          ),
        ),
        Positioned(
          top: 0,
          right: 0,
          child: GestureDetector(
            onTap: onRemove,
            child: Container(
              padding: const EdgeInsets.all(3),
              decoration: const BoxDecoration(
                color: AppColors.red,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.close, size: 13, color: Colors.white),
            ),
          ),
        ),
      ],
    );
  }
}