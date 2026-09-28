import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'package:image_picker/image_picker.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../models/incident_model.dart';
import '../models/assignment_model.dart';
import '../models/attachment_model.dart';
import '../providers/auth_provider.dart';
import '../providers/incident_provider.dart';
import '../providers/assignment_provider.dart';
import '../services/incident_service.dart';
import '../services/api_config.dart';
import '../services/api_service.dart';

class ViewDetailsScreen extends StatefulWidget {
  const ViewDetailsScreen({super.key});

  @override
  State<ViewDetailsScreen> createState() => _ViewDetailsScreenState();
}

class _ViewDetailsScreenState extends State<ViewDetailsScreen> {
  final _notesController = TextEditingController();
  final _incidentService = IncidentService();

  /// Seeded from the route argument so the screen paints immediately, then
  /// replaced by the by-id payload, which is the only response that carries
  /// attachments, the assigned person and the resolution note.
  Incident? _incident;
  Assignment? _assignment;
  bool _loading = true;
  bool _addingNote = false;
  bool _uploading = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final args = ModalRoute.of(context)?.settings.arguments;
    if (args is Incident) {
      _incident = args;
    } else if (args is Assignment) {
      _assignment = args;
      _incident = args.incident;
    }
    _load();
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final id = _incident?.id ?? _assignment?.incidentId;
    if (id == null || id == 0) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    try {
      final fresh = await _incidentService.getIncident(id);
      if (!mounted || fresh == null) return;
      setState(() {
        _incident = fresh;
        final assignmentId = _assignment?.id;
        _assignment = assignmentId == null
            ? null
            : fresh.assignments.cast<Assignment?>().firstWhere(
                  (a) => a?.id == assignmentId,
                  orElse: () => null,
                );
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.red),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _loading = false);
    }
  }

  Future<void> _handleResolve(Incident incident) async {
    if (!_addingNote) {
      setState(() => _addingNote = true);
      return;
    }

    final notes = _notesController.text.trim();
    setState(() {
      _addingNote = false;
      _saving = true;
    });

    final provider = context.read<IncidentProvider>();
    final error = await provider.updateStatus(
      incident.id,
      status: 'resolved',
      resolutionNotes: notes,
    );

    if (!mounted) return;
    setState(() => _saving = false);
    _notesController.clear();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(error ?? 'Complaint marked as Resolved'),
        backgroundColor: error == null ? AppColors.green : AppColors.red,
      ),
    );
    if (error == null) {
      await context.read<AssignmentProvider>().fetchData();
      await _load();
    }
  }

  Future<void> _attachPhoto(Incident incident) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined,
                  color: AppColors.navy),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined,
                  color: AppColors.navy),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    final picked =
        await ImagePicker().pickImage(source: source, maxWidth: 1920);
    if (picked == null || !mounted) return;

    setState(() => _uploading = true);
    try {
      final bytes = await picked.readAsBytes();
      await _incidentService.uploadAttachment(
        incident.id,
        bytes: bytes,
        filename: picked.name,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Photo proof uploaded'),
          backgroundColor: AppColors.green,
        ),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.red),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Could not upload the photo.'),
          backgroundColor: AppColors.red,
        ),
      );
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  /// Resolution note from whichever source has it: the assignment passed in by
  /// offsite staff, else the latest note carried by the by-id payload.
  String? _resolutionNote(Incident incident) {
    final fromAssignment = _assignment?.resolutionNotes;
    if (fromAssignment != null && fromAssignment.trim().isNotEmpty) {
      return fromAssignment;
    }
    return incident.latestResolutionNotes;
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final isCustomer = auth.isCustomer;
    final incident = _incident;

    return Scaffold(
      backgroundColor: const Color(0xFFD8E5F2), // greyed background
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                child: Container(
                  decoration: BoxDecoration(
                    color: AppColors.cardBg,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.navy.withOpacity(0.2),
                        blurRadius: 32,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  clipBehavior: Clip.hardEdge,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // ── Navy bar header ──────────────────────
                      Container(
                        color: AppColors.navy,
                        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
                        child: Row(
                          children: [
                            const MayniladLogo(size: 36),
                            const SizedBox(width: 10),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  user?.role.toUpperCase() ?? 'USER',
                                  style: TextStyle(
                                      color: Colors.white.withOpacity(0.6),
                                      fontSize: 9.5),
                                ),
                                Text(
                                  'Hello, ${user?.fullName ?? 'User'}',
                                  style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700),
                                ),
                              ],
                            ),
                            const Spacer(),
                            GestureDetector(
                              onTap: () => Navigator.pop(context),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 12, vertical: 6),
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Text(
                                  '✕ Close',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w600),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                      // ── Detail body ──────────────────────────
                      if (incident == null)
                        const Padding(
                            padding: EdgeInsets.all(20),
                            child: Text('No details found.'))
                      else
                        Padding(
                          padding: const EdgeInsets.all(18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  _StatusBadge(
                                      status: incident.status ?? 'OPEN'),
                                  const Spacer(),
                                  if (_loading)
                                    const SizedBox(
                                      height: 12,
                                      width: 12,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 1.8,
                                        color: AppColors.navy,
                                      ),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 16),

                              // Complaint ID, filed date, and the fields
                              // each role needs to act on the complaint.
                              _FieldRow(
                                icon: Icons.tag,
                                label: 'Complaint ID',
                                value: '#MNL-${incident.id}',
                              ),
                              const Divider(
                                  color: AppColors.divider, height: 1),
                              _FieldRow(
                                icon: Icons.schedule,
                                label: 'Filed Date',
                                value: incident.submittedAt != null
                                    ? DateFormat('MMM dd, yyyy · hh:mm a')
                                        .format(incident.submittedAt!)
                                    : null,
                              ),
                              const Divider(
                                  color: AppColors.divider, height: 1),
                              _FieldRow(
                                icon: Icons.support_agent,
                                label: 'Assigned Offsite Person',
                                value: incident.assignedOffsiteName ??
                                    'Not yet assigned',
                              ),
                              const Divider(
                                  color: AppColors.divider, height: 1),
                              _FieldRow(
                                icon: Icons.location_on_outlined,
                                label: 'Location',
                                value: incident.location,
                              ),

                              // Offsite staff also need to know who filed it
                              // and how to reach them.
                              if (!isCustomer) ...[
                                const Divider(
                                    color: AppColors.divider, height: 1),
                                _FieldRow(
                                  icon: Icons.person_outline,
                                  label: 'Complainant Name',
                                  value: incident.customer?.fullName,
                                ),
                                const Divider(
                                    color: AppColors.divider, height: 1),
                                _FieldRow(
                                  icon: Icons.call_outlined,
                                  label: 'CP Number',
                                  value: incident.customer?.contactNo,
                                ),
                                const Divider(
                                    color: AppColors.divider, height: 1),
                                _FieldRow(
                                  icon: Icons.label_outline,
                                  label: 'Category',
                                  value: incident.category,
                                ),
                                const Divider(
                                    color: AppColors.divider, height: 1),
                                _FieldRow(
                                  icon: Icons.priority_high,
                                  label: 'Severity',
                                  value: incident.severity,
                                ),
                              ],
                              const SizedBox(height: 18),

                              // Complaint description
                              const Text(
                                'Complaint Description',
                                style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.textBody),
                              ),
                              const SizedBox(height: 8),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: AppColors.fieldBg,
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                child: Text(
                                  incident.description ??
                                      'No description provided.',
                                  style: const TextStyle(
                                      fontSize: 12,
                                      color: Color(0xFF4B5563),
                                      height: 1.65),
                                ),
                              ),
                              const SizedBox(height: 18),

                              // Image attachment(s)
                              const Text(
                                'Image Attachment',
                                style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.textBody),
                              ),
                              const SizedBox(height: 8),
                              _AttachmentGallery(
                                  attachments: incident.attachments),
                              const SizedBox(height: 18),

                              if (_resolutionNote(incident) != null) ...[
                                const Text(
                                  'Resolution Note',
                                  style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.textBody),
                                ),
                                const SizedBox(height: 8),
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFE6F5EE),
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                  child: Text(
                                    _resolutionNote(incident)!,
                                    style: const TextStyle(
                                        fontSize: 12,
                                        color: AppColors.green,
                                        height: 1.65),
                                  ),
                                ),
                                const SizedBox(height: 18),
                              ],

                              // Add note inline field (shown when _addingNote)
                              if (_addingNote && !isCustomer) ...[
                                TextField(
                                  controller: _notesController,
                                  maxLines: 3,
                                  autofocus: true,
                                  style: const TextStyle(
                                      fontSize: 12, color: AppColors.textDark),
                                  decoration: InputDecoration(
                                    hintText: 'Type resolution notes here…',
                                    hintStyle: const TextStyle(
                                        color: AppColors.textMuted,
                                        fontSize: 12),
                                    filled: true,
                                    fillColor: AppColors.fieldBg,
                                    contentPadding: const EdgeInsets.all(14),
                                    border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(14),
                                      borderSide: BorderSide.none,
                                    ),
                                    focusedBorder: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(14),
                                      borderSide: const BorderSide(
                                          color: AppColors.navy, width: 1.5),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 10),
                              ],

                              // Add Notes button (only for staff)
                              if (!isCustomer && !incident.isResolved)
                                ElevatedButton.icon(
                                  onPressed: _saving
                                      ? null
                                      : () => _handleResolve(incident),
                                  icon: _saving
                                      ? const SizedBox(
                                          height: 14,
                                          width: 14,
                                          child: CircularProgressIndicator(
                                              color: Colors.white,
                                              strokeWidth: 2),
                                        )
                                      : Icon(
                                          _addingNote ? Icons.check : Icons.add,
                                          size: 16),
                                  label: Text(_addingNote
                                      ? 'Confirm Resolved'
                                      : _resolutionNote(incident) == null
                                          ? 'Add Resolution Note'
                                          : 'Mark as Resolved'),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.navy,
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 20, vertical: 10),
                                    shape: const StadiumBorder(),
                                    elevation: 0,
                                    textStyle: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700),
                                  ),
                                ),
                              const SizedBox(height: 10),

                              // Photo proof (staff only)
                              if (!isCustomer)
                                SizedBox(
                                  width: double.infinity,
                                  child: OutlinedButton.icon(
                                    onPressed: _uploading
                                        ? null
                                        : () => _attachPhoto(incident),
                                    icon: _uploading
                                        ? const SizedBox(
                                            height: 14,
                                            width: 14,
                                            child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: AppColors.navy),
                                          )
                                        : const Icon(Icons.camera_alt_outlined,
                                            size: 16),
                                    label: const Text('Attach Photo Proof'),
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: AppColors.navy,
                                      padding: const EdgeInsets.symmetric(
                                          vertical: 12),
                                      shape: const StadiumBorder(),
                                      side: const BorderSide(
                                          color: AppColors.navy),
                                    ),
                                  ),
                                ),
                              const SizedBox(height: 12),

                              // Close Details button
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton(
                                  onPressed: () => Navigator.pop(context),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.navy,
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(
                                        vertical: 15),
                                    shape: const StadiumBorder(),
                                    elevation: 0,
                                  ),
                                  child: const Text(
                                    'Close Details',
                                    style: TextStyle(
                                        fontSize: 15,
                                        fontWeight: FontWeight.w700,
                                        letterSpacing: 1),
                                  ),
                                ),
                              ),
                            ],
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
              onReport: () => Navigator.pushNamed(context, '/complaint'),
              onSettings: () => Navigator.pushNamed(context, '/settings'),
              isCustomer: isCustomer,
            ),
          ],
        ),
      ),
    );
  }
}

/// One label/value pair in the detail sheet. An absent value is shown as
/// "Not provided" rather than collapsing the row, so the field list is the
/// same for every complaint.
class _FieldRow extends StatelessWidget {
  const _FieldRow({required this.label, required this.value, this.icon});

  final String label;
  final String? value;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final text = value?.trim() ?? '';
    final present = text.isNotEmpty;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon != null) ...[
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child:
                  Icon(icon, size: 14, color: AppColors.navy.withValues(alpha: 0.55)),
            ),
            const SizedBox(width: 9),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label.toUpperCase(),
                  style: const TextStyle(
                    fontSize: 9,
                    letterSpacing: 0.7,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textMuted,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  present ? text : 'Not provided',
                  style: TextStyle(
                    fontSize: 12.5,
                    height: 1.4,
                    fontWeight: FontWeight.w600,
                    color: present ? AppColors.navy : AppColors.textMuted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Horizontal strip of photo proofs, tappable for a full-screen view.
class _AttachmentGallery extends StatelessWidget {
  const _AttachmentGallery({required this.attachments});

  final List<Attachment> attachments;

  @override
  Widget build(BuildContext context) {
    if (attachments.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 14),
        decoration: BoxDecoration(
          color: AppColors.fieldBg,
          borderRadius: BorderRadius.circular(14),
        ),
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.image_not_supported_outlined,
                size: 16, color: AppColors.textMuted),
            SizedBox(width: 8),
            Text(
              'No image attached',
              style: TextStyle(fontSize: 11.5, color: AppColors.textMuted),
            ),
          ],
        ),
      );
    }

    return SizedBox(
      height: 96,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: attachments.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final attachment = attachments[index];
          return _AttachmentThumb(
            attachment: attachment,
            onTap: () => _openFullScreen(context, attachment),
          );
        },
      ),
    );
  }

  void _openFullScreen(BuildContext context, Attachment attachment) {
    final url = attachment.url;
    if (url == null || url.isEmpty) return;

    showDialog<void>(
      context: context,
      builder: (context) => Dialog(
        backgroundColor: Colors.black,
        insetPadding: const EdgeInsets.all(8),
        child: Stack(
          children: [
            InteractiveViewer(
              child: Center(
                child: Image.network(
                  ApiConfig.absoluteUrl(url),
                  fit: BoxFit.contain,
                  errorBuilder: (context, error, stackTrace) => const Padding(
                    padding: EdgeInsets.all(40),
                    child: Text(
                      'Image could not be loaded.',
                      style: TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 4,
              right: 4,
              child: GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.close, color: Colors.white, size: 20),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AttachmentThumb extends StatelessWidget {
  const _AttachmentThumb({required this.attachment, required this.onTap});

  final Attachment attachment;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final url = attachment.url;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 96,
        height: 96,
        decoration: BoxDecoration(
          color: AppColors.fieldBg,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.divider),
        ),
        clipBehavior: Clip.antiAlias,
        child: (url == null || url.isEmpty)
            ? const Icon(Icons.broken_image_outlined,
                color: AppColors.textMuted)
            : Image.network(
                ApiConfig.absoluteUrl(url),
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => const Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.broken_image_outlined,
                        size: 20, color: AppColors.textMuted),
                    SizedBox(height: 4),
                    Text(
                      'Unavailable',
                      style:
                          TextStyle(fontSize: 8.5, color: AppColors.textMuted),
                    ),
                  ],
                ),
                loadingBuilder: (context, child, progress) => progress == null
                    ? child
                    : const Center(
                        child: SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 1.8),
                        ),
                      ),
              ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color = AppColors.orange;
    Color bgColor = const Color(0xFFFFF4E5);

    final s = status.toUpperCase();
    if (s == 'RESOLVED' || s == 'COMPLETE') {
      color = AppColors.green;
      bgColor = const Color(0xFFE6F5EE);
    } else if (s == 'OPEN') {
      color = AppColors.red;
      bgColor = const Color(0xFFFEE2E2);
    } else if (s == 'ASSIGNED') {
      color = AppColors.orange;
      bgColor = const Color(0xFFFFF4E5);
    } else if (s == 'IN_PROGRESS') {
      color = AppColors.blueLink;
      bgColor = const Color(0xFFE7F1FF);
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color, width: 1.5),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
          const SizedBox(width: 5),
          Text(status.toUpperCase(),
              style: TextStyle(
                  color: color,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.4)),
        ],
      ),
    );
  }
}
