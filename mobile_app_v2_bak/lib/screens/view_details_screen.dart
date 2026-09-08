import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'package:image_picker/image_picker.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../models/incident_model.dart';
import '../models/assignment_model.dart';
import '../providers/auth_provider.dart';
import '../providers/incident_provider.dart';
import '../providers/assignment_provider.dart';
import '../services/incident_service.dart';
import '../services/api_service.dart';

class ViewDetailsScreen extends StatefulWidget {
  const ViewDetailsScreen({super.key});

  @override
  State<ViewDetailsScreen> createState() => _ViewDetailsScreenState();
}

class _ViewDetailsScreenState extends State<ViewDetailsScreen> {
  final _notesController = TextEditingController();
  final _incidentService = IncidentService();
  bool _addingNote = false;
  bool _uploading = false;

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _handleResolve(Incident incident) async {
    if (!_addingNote) {
      setState(() => _addingNote = true);
      return;
    }

    final notes = _notesController.text.trim();
    setState(() => _addingNote = false);

    final provider = context.read<IncidentProvider>();
    final error = await provider.updateStatus(
      incident.id,
      status: 'resolved',
      resolutionNotes: notes,
    );

    if (!mounted) return;
    _notesController.clear();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(error ?? 'Incident marked as Resolved'),
        backgroundColor: error == null ? AppColors.green : AppColors.red,
      ),
    );
    if (error == null) {
      await context.read<AssignmentProvider>().fetchData();
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
              leading: const Icon(Icons.photo_camera_outlined, color: AppColors.navy),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined, color: AppColors.navy),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    final picked = await ImagePicker().pickImage(source: source, maxWidth: 1920);
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

  @override
  Widget build(BuildContext context) {
    final args = ModalRoute.of(context)!.settings.arguments;
    Incident? incident;
    Assignment? assignment;

    if (args is Incident) {
      incident = args;
    } else if (args is Assignment) {
      assignment = args;
      incident = assignment.incident;
    }

    final user = context.watch<AuthProvider>().user;

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
                                  style: TextStyle(color: Colors.white.withOpacity(0.6), fontSize: 9.5),
                                ),
                                Text(
                                  'Hello, ${user?.fullName ?? 'User'}',
                                  style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700),
                                ),
                              ],
                            ),
                            const Spacer(),
                            GestureDetector(
                              onTap: () => Navigator.pop(context),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Text(
                                  '✕ Close',
                                  style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                      // ── Detail body ──────────────────────────
                      if (incident == null)
                        const Padding(padding: EdgeInsets.all(20), child: Text('No details found.'))
                      else
                        Padding(
                          padding: const EdgeInsets.all(18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // Status badges
                              _StatusBadge(status: incident.status ?? 'OPEN'),
                              const SizedBox(height: 12),

                              Text(
                                'Complaint ID #MNL-${incident.id}',
                                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.navy),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                'Filed: ${incident.submittedAt != null ? DateFormat('MMM dd, yyyy · hh:mm a').format(incident.submittedAt!) : ''} · ${incident.location ?? ''}',
                                style: const TextStyle(fontSize: 10.5, color: AppColors.textMuted),
                              ),
                              const SizedBox(height: 18),

                              // Complaint Notes label
                              const Text(
                                'Complaint Description',
                                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textBody),
                              ),
                              const SizedBox(height: 8),

                              // Notes grey box
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: AppColors.fieldBg,
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      incident.description ?? 'No description provided.',
                                      style: const TextStyle(fontSize: 12, color: Color(0xFF4B5563), height: 1.65),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),

                              if (assignment != null && assignment.resolutionNotes != null) ...[
                                const Text(
                                  'Resolution Notes',
                                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textBody),
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
                                    assignment.resolutionNotes!,
                                    style: const TextStyle(fontSize: 12, color: AppColors.green, height: 1.65),
                                  ),
                                ),
                                const SizedBox(height: 16),
                              ],

                              // Add note inline field (shown when _addingNote)
                              if (_addingNote && !context.read<AuthProvider>().isCustomer) ...[
                                TextField(
                                  controller: _notesController,
                                  maxLines: 3,
                                  autofocus: true,
                                  style: const TextStyle(fontSize: 12, color: AppColors.textDark),
                                  decoration: InputDecoration(
                                    hintText: 'Type resolution notes here…',
                                    hintStyle: const TextStyle(color: AppColors.textMuted, fontSize: 12),
                                    filled: true,
                                    fillColor: AppColors.fieldBg,
                                    contentPadding: const EdgeInsets.all(14),
                                    border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(14),
                                      borderSide: BorderSide.none,
                                    ),
                                    focusedBorder: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(14),
                                      borderSide: const BorderSide(color: AppColors.navy, width: 1.5),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 10),
                              ],

                              // Add Notes button (only for staff)
                              if (!context.read<AuthProvider>().isCustomer && !incident.isResolved)
                                ElevatedButton.icon(
                                  onPressed: () => _handleResolve(incident!),
                                  icon: _uploading
                                      ? const SizedBox(
                                          height: 14,
                                          width: 14,
                                          child: CircularProgressIndicator(
                                              color: Colors.white, strokeWidth: 2),
                                        )
                                      : Icon(_addingNote ? Icons.check : Icons.add, size: 16),
                                  label: Text(
                                      _addingNote
                                          ? 'Confirm Resolved'
                                          : incident.resolutionNotes == null
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
                                        fontSize: 12, fontWeight: FontWeight.w700),
                                  ),
                                ),
                              const SizedBox(height: 10),

                              // Photo proof (staff only)
                              if (!context.read<AuthProvider>().isCustomer)
                                SizedBox(
                                  width: double.infinity,
                                  child: OutlinedButton.icon(
                                    onPressed: _uploading
                                        ? null
                                        : () => _attachPhoto(incident!),
                                    icon: const Icon(Icons.camera_alt_outlined,
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
                                    padding: const EdgeInsets.symmetric(vertical: 15),
                                    shape: const StadiumBorder(),
                                    elevation: 0,
                                  ),
                                  child: const Text(
                                    'Close Details',
                                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, letterSpacing: 1),
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
              onHome: () => context.read<AuthProvider>().navigateToHome(context),
              onReport: () => Navigator.pushNamed(context, '/complaint'),
              onSettings: () => Navigator.pushNamed(context, '/settings'),
              isCustomer: context.watch<AuthProvider>().isCustomer,
            ),
          ],
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
          Container(width: 6, height: 6, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
          const SizedBox(width: 5),
          Text(status.toUpperCase(), style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.4)),
        ],
      ),
    );
  }
}
