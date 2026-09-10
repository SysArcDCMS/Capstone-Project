import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../providers/auth_provider.dart';
import '../providers/incident_provider.dart';
import '../providers/assignment_provider.dart';
import '../models/assignment_model.dart';
import '../services/availability_service.dart';

class OffsiteScreen extends StatefulWidget {
  const OffsiteScreen({super.key});

  @override
  State<OffsiteScreen> createState() => _OffsiteScreenState();
}

class _OffsiteScreenState extends State<OffsiteScreen> {
  static const _categories = [
    'Billing',
    'Water Quality',
    'Metering',
    'Operations',
  ];
  static const _severities = ['High', 'Medium', 'Low'];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AssignmentProvider>().fetchData();
    });
  }

  Future<void> _accept(Assignment a) async {
    final provider = context.read<AssignmentProvider>();
    final error = await provider.teamLeaderAction(a.id, action: 'accept');
    if (!mounted) return;
    _toast(
      error ?? 'Incident accepted',
      success: error == null,
    );
  }

  Future<void> _reject(Assignment a) async {
    final reason = TextEditingController();
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Reject Assignment'),
        content: TextField(
          controller: reason,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Rejection reason (e.g. wrong department)…',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Reject'),
          ),
        ],
      ),
    );

    if (confirm != true) return;
    if (!mounted) return;

    final provider = context.read<AssignmentProvider>();
    final error = await provider.teamLeaderAction(
      a.id,
      action: 'reject',
      rejectionReason: reason.text.trim(),
    );
    if (!mounted) return;
    _toast(
      error ?? 'Incident flagged for Engineer review',
      success: error == null,
    );
  }

  Future<void> _correct(Assignment a) async {
    String? category;
    String? severity;

    final saved = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Correct Classification'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                initialValue: category,
                decoration: const InputDecoration(labelText: 'Corrected Category'),
                items: _categories
                    .map((c) => DropdownMenuItem(value: c, child: Text(c)))
                    .toList(),
                onChanged: (v) => setDialogState(() => category = v),
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                initialValue: severity,
                decoration: const InputDecoration(labelText: 'Corrected Severity'),
                items: _severities
                    .map((s) => DropdownMenuItem(value: s, child: Text(s)))
                    .toList(),
                onChanged: (v) => setDialogState(() => severity = v),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Submit'),
            ),
          ],
        ),
      ),
    );

    if (!mounted) return;

    if (saved != true || (category == null && severity == null)) {
      if (saved == true) {
        _toast('Select a corrected category or severity.', success: false);
      }
      return;
    }

    final provider = context.read<AssignmentProvider>();
    final error = await provider.teamLeaderAction(
      a.id,
      action: 'correct',
      correctedCategory: category,
      correctedSeverity: severity,
    );
    if (!mounted) return;
    _toast(
      error ?? 'Correction submitted for Engineer review',
      success: error == null,
    );
  }

  Future<void> _updateStatus(Assignment a) async {
    final notes = TextEditingController();
    String status = 'in_progress';

    final saved = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Update Status'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                initialValue: status,
                decoration: const InputDecoration(labelText: 'New Status'),
                items: const [
                  DropdownMenuItem(value: 'in_progress', child: Text('In Progress')),
                  DropdownMenuItem(value: 'resolved', child: Text('Resolved')),
                ],
                onChanged: (v) => setDialogState(() => status = v ?? 'in_progress'),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: notes,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Resolution Notes',
                  hintText: 'Describe the action taken…',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );

    if (saved != true) return;
    if (!mounted) return;

    final provider = context.read<IncidentProvider>();
    final error = await provider.updateStatus(
      a.incidentId,
      status: status,
      resolutionNotes: notes.text.trim(),
    );
    if (!mounted) return;
    _toast(
      error ?? (status == 'resolved' ? 'Incident marked as Resolved' : 'Status updated'),
      success: error == null,
    );
    if (error == null) {
      await context.read<AssignmentProvider>().fetchData();
    }
  }

  void _toast(String message, {bool success = true}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: success ? AppColors.green : AppColors.red,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final provider = context.watch<AssignmentProvider>();
    final assignments = provider.assignments;
    final activeAssignment = assignments.isNotEmpty ? assignments.first : null;

    return Scaffold(
      backgroundColor: AppColors.pageBg,
      body: SafeArea(
        child: Column(
          children: [
            // ── Header ──────────────────────────────────────────
            Container(
              color: AppColors.navy,
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 18),
              child: Row(
                children: [
                  const MayniladLogo(size: 44),
                  const Spacer(),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        '${user?.role.toUpperCase() ?? 'STAFF'},',
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.55),
                          fontSize: 11,
                        ),
                      ),
                      Text(
                        'Hello, ${user?.fullName ?? 'User'}!',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // ── Availability Selector ───────────────────────────
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Row(
                children: [
                  const Text('Status:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  const SizedBox(width: 8),
                  DropdownButton<String>(
                    value: provider.availability,
                    items: AvailabilityStatus.all
                        .map((s) => DropdownMenuItem(
                              value: s,
                              child: Text(AvailabilityStatus.label(s),
                                  style: const TextStyle(fontSize: 12)),
                            ))
                        .toList(),
                    onChanged: (val) async {
                      if (val == null || val == provider.availability) return;
                      final error =
                          await context.read<AssignmentProvider>().updateAvailability(val);
                      if (!mounted) return;
                      _toast(
                        error ?? 'Availability set to ${AvailabilityStatus.label(val)}',
                        success: error == null,
                      );
                    },
                  ),
                ],
              ),
            ),

            // ── Body ────────────────────────────────────────────
            Expanded(
              child: provider.isLoading && assignments.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : RefreshIndicator(
                      onRefresh: () =>
                          context.read<AssignmentProvider>().fetchData(),
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                        children: [
                          // Active assignment card
                          if (activeAssignment != null)
                            _SectionCard(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const _SectionLabel('Active Assignment'),
                                  const SizedBox(height: 12),

                                  Wrap(
                                    spacing: 8,
                                    children: [
                                      _Badge(
                                        label: activeAssignment.incident?.status?.toUpperCase() ?? 'OPEN',
                                        color: AppColors.orange,
                                        bgColor: const Color(0xFFFFF4E5),
                                        dot: true,
                                      ),
                                      if (activeAssignment.incident?.severity == 'High')
                                        const _Badge(
                                          label: '▲ High Priority',
                                          color: AppColors.red,
                                          bgColor: Color(0xFFFEE2E2),
                                        ),
                                    ],
                                  ),
                                  const SizedBox(height: 10),

                                  Text(
                                    'Complaint ID #MNL-${activeAssignment.incidentId}',
                                    style: const TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.navy,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    activeAssignment.assignedAt != null
                                        ? DateFormat('MMM dd, yyyy · hh:mm a')
                                            .format(activeAssignment.assignedAt!)
                                        : '',
                                    style: const TextStyle(
                                      fontSize: 10.5,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                  const SizedBox(height: 12),

                                  _InfoRow(
                                    icon: Icons.location_on_outlined,
                                    text: activeAssignment.incident?.location ??
                                        'No location provided',
                                  ),
                                  if (activeAssignment.incident?.category != null)
                                    Padding(
                                      padding: const EdgeInsets.only(top: 8),
                                      child: _InfoRow(
                                        icon: Icons.label_outline,
                                        text: '${activeAssignment.incident!.category}'
                                            ' · Severity ${activeAssignment.incident!.severity ?? '—'}',
                                      ),
                                    ),
                                  const SizedBox(height: 16),

                                  // Actions
                                  Wrap(
                                    spacing: 8,
                                    runSpacing: 8,
                                    children: [
                                      _ActionButton(
                                        label: 'Accept',
                                        color: AppColors.green,
                                        icon: Icons.check,
                                        onTap: () => _accept(activeAssignment),
                                      ),
                                      _ActionButton(
                                        label: 'Reject',
                                        color: AppColors.red,
                                        icon: Icons.close,
                                        onTap: () => _reject(activeAssignment),
                                      ),
                                      _ActionButton(
                                        label: 'Correct',
                                        color: AppColors.orange,
                                        icon: Icons.edit,
                                        onTap: () => _correct(activeAssignment),
                                      ),
                                      _ActionButton(
                                        label: 'Update Status',
                                        color: AppColors.navy,
                                        icon: Icons.update,
                                        onTap: () => _updateStatus(activeAssignment),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),

                                  ElevatedButton(
                                    onPressed: () => Navigator.pushNamed(
                                        context, '/viewdetails',
                                        arguments: activeAssignment),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: AppColors.navy,
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 22, vertical: 10),
                                      shape: const StadiumBorder(),
                                      elevation: 0,
                                    ),
                                    child: const Text('View Details',
                                        style: TextStyle(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w700)),
                                  ),
                                ],
                              ),
                            ),

                          if (activeAssignment == null)
                            const _SectionCard(
                              child: Center(child: Text('No active assignments.')),
                            ),

                          const SizedBox(height: 16),

                          // Task queue
                          if (assignments.length > 1)
                            const Padding(
                              padding: EdgeInsets.only(bottom: 10),
                              child: Text(
                                'TASK QUEUE',
                                style: TextStyle(
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.textMuted,
                                  letterSpacing: 1.2,
                                ),
                              ),
                            ),

                          ...assignments.skip(1).map((a) => Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: _SectionCard(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 16, vertical: 14),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.center,
                                    children: [
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            _Badge(
                                              label:
                                                  '● ${a.incident?.status?.toUpperCase() ?? 'OPEN'}',
                                              color: AppColors.red,
                                              bgColor: const Color(0xFFFEE2E2),
                                            ),
                                            const SizedBox(height: 6),
                                            Text(
                                              '#MNL-${a.incidentId}',
                                              style: const TextStyle(
                                                  fontSize: 12.5,
                                                  fontWeight: FontWeight.w700,
                                                  color: AppColors.navy),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(a.incident?.location ?? '',
                                                style: const TextStyle(
                                                    fontSize: 10.5,
                                                    color:
                                                        AppColors.textMuted)),
                                            Text(
                                              a.assignedAt != null
                                                  ? DateFormat(
                                                          'MMM dd, yyyy')
                                                      .format(a.assignedAt!)
                                                  : '',
                                              style: const TextStyle(
                                                  fontSize: 10,
                                                  color: AppColors.textMuted),
                                            ),
                                          ],
                                        ),
                                      ),
                                      ElevatedButton(
                                        onPressed: () => Navigator.pushNamed(
                                            context, '/viewdetails',
                                            arguments: a),
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: AppColors.navy,
                                          foregroundColor: Colors.white,
                                          padding: const EdgeInsets.symmetric(
                                              horizontal: 14, vertical: 8),
                                          shape: const StadiumBorder(),
                                          elevation: 0,
                                          textStyle: const TextStyle(
                                              fontSize: 10.5,
                                              fontWeight: FontWeight.w700),
                                        ),
                                        child: const Text('View Details'),
                                      ),
                                    ],
                                  ),
                                ),
                              )),
                        ],
                      ),
                    ),
            ),

            MayniladBottomNav(
              active: NavTab.home,
              onHome: () =>
                  context.read<AuthProvider>().navigateToHome(context),
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

class _ActionButton extends StatelessWidget {
  final String label;
  final Color color;
  final IconData icon;
  final VoidCallback onTap;

  const _ActionButton({
    required this.label,
    required this.color,
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: Colors.white),
            const SizedBox(width: 5),
            Text(
              label,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final Widget child;
  final EdgeInsets padding;
  const _SectionCard({required this.child, this.padding = const EdgeInsets.all(18)});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: AppColors.navy.withOpacity(0.08), blurRadius: 12, offset: const Offset(0, 2))],
      ),
      child: child,
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String text;
  const _SectionLabel(this.text);

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: AppColors.textMuted, letterSpacing: 1.2),
    );
  }
}

class _Badge extends StatelessWidget {
  final String label;
  final Color color;
  final Color bgColor;
  final bool dot;
  const _Badge({required this.label, required this.color, required this.bgColor, this.dot = false});

  @override
  Widget build(BuildContext context) {
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
          if (dot) ...[
            Container(width: 6, height: 6, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
            const SizedBox(width: 5),
          ],
          Text(label, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.4)),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String text;
  const _InfoRow({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 15, color: AppColors.textMuted),
        const SizedBox(width: 8),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 12, color: Color(0xFF4B5563)))),
      ],
    );
  }
}