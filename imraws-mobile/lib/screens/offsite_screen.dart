import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../widgets/notification_bell.dart';
import '../widgets/error_card.dart';
import '../providers/auth_provider.dart';
import '../providers/incident_provider.dart';
import '../providers/assignment_provider.dart';
import '../models/assignment_model.dart';
import '../services/availability_service.dart';
import '../widgets/confirm_dialog.dart';

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
    final confirmed = await confirmAction(
      context,
      title: 'Accept Assignment?',
      message: 'This assignment will be locked to you and shown as your active incident.',
      confirmLabel: 'Accept',
    );
    if (confirmed != true) return;
    if (!mounted) return;

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

  /// The action set for a complaint card.
  ///
  /// Triage and progress are mutually exclusive: a team leader triages an
  /// untouched complaint (Accept / Reject / Correct), and can only start
  /// changing the status once they have accepted it. `pending`, `assigned`,
  /// `correct` and `override` are all still un-accepted, so a leader who
  /// corrected the classification can still accept it from the card.
  List<Widget> _actionButtons(Assignment a) {
    const accepted = {'accept', 'in_progress'};

    if (accepted.contains(a.actionStatus)) {
      return [
        _ActionButton(
          label: 'CHANGE STATUS',
          color: AppColors.navy,
          onTap: () => _updateStatus(a),
        ),
      ];
    }

    return [
      _ActionButton(
        label: 'Accept',
        color: AppColors.green,
        onTap: () => _accept(a),
        expand: true,
      ),
      _ActionButton(
        label: 'Reject',
        color: AppColors.red,
        onTap: () => _reject(a),
        expand: true,
      ),
      _ActionButton(
        label: 'Correct',
        color: AppColors.orange,
        onTap: () => _correct(a),
        expand: true,
      ),
    ];
  }

  /// Lays the badge and its actions out on one line. The actions take the
  /// space the badge leaves behind, so three triage buttons share a narrow
  /// phone instead of overflowing it.
  Widget _statusBlock(Assignment a) {
    final badge = _StatusBadge(status: a.incident?.status ?? 'OPEN');
    final actions = _actionButtons(a);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        badge,
        const SizedBox(width: 8),
        if (actions.length == 1)
          // A lone button keeps its natural width, right-aligned.
          Expanded(
            child: Align(alignment: Alignment.centerRight, child: actions.first),
          )
        else
          Expanded(
            child: Row(
              children: [
                for (var i = 0; i < actions.length; i++) ...[
                  if (i > 0) const SizedBox(width: 6),
                  Expanded(child: actions[i]),
                ],
              ],
            ),
          ),
      ],
    );
  }

  /// The date the customer filed the complaint, not the date the assignment
  /// row was created — the card has always shown the latter, which reads as
  /// "filed" on a complaint list. Falls back to `assigned_at` for the rare
  /// row whose incident carries no submitted timestamp.
  String _filedDate(Assignment a) {
    final filed = a.incident?.submittedAt ?? a.assignedAt;
    if (filed == null) return '';
    return DateFormat('MMM dd, yyyy · hh:mm a').format(filed);
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
    const historyStatuses = {'resolved', 'reject', 'reassign'};
    final activeAssignments = assignments
        .where((a) =>
            !historyStatuses.contains(a.actionStatus) &&
            (a.incident?.status ?? '') != 'resolved')
        .toList();
    final historyAssignments = assignments
        .where((a) =>
            historyStatuses.contains(a.actionStatus) ||
            (a.incident?.status ?? '') == 'resolved')
        .toList();

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
                  // Expanded, not Spacer: a Spacer only absorbs slack, so a long
                  // name still overflows the header. Expanded hands the greeting
                  // whatever space is left and the ellipsis absorbs the rest, so
                  // the logo and bell keep their place at any width.
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${user?.role.toUpperCase() ?? 'STAFF'},',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.right,
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.55),
                            fontSize: 11,
                          ),
                        ),
                        Text(
                          'Hello, ${user?.fullName ?? 'User'}!',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.right,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  const NotificationBell(),
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
                          // Active complaints list
                          const Padding(
                            padding: EdgeInsets.only(bottom: 10),
                            child: Text(
                              'ACTIVE COMPLAINTS',
                              style: TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w700,
                                color: AppColors.textMuted,
                                letterSpacing: 1.2,
                              ),
                            ),
                          ),

                          if (provider.error != null && assignments.isEmpty)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 16),
                              child: ErrorCard(
                                message: provider.error!,
                                onRetry: () => context
                                    .read<AssignmentProvider>()
                                    .fetchData(),
                              ),
                            )
                          else if (activeAssignments.isEmpty)
                            const _SectionCard(
                              child: Center(child: Text('No active complaints.')),
                            ),

                          ...activeAssignments.map((a) => Padding(
                                padding: const EdgeInsets.only(bottom: 12),
                                child: _SectionCard(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      _statusBlock(a),
                                      const SizedBox(height: 10),

                                      Text(
                                        'Complaint ID #MNL-${a.incidentId}',
                                        style: const TextStyle(
                                          fontSize: 15,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.navy,
                                        ),
                                      ),
                                      const SizedBox(height: 3),
                                      Text(
                                        _filedDate(a),
                                        style: const TextStyle(
                                          fontSize: 10.5,
                                          color: AppColors.textMuted,
                                        ),
                                      ),
                                      const SizedBox(height: 12),

                                      _InfoRow(
                                        icon: Icons.location_on_outlined,
                                        text: a.incident?.location ??
                                            'No location provided',
                                      ),
                                      if (a.incident?.category != null)
                                        Padding(
                                          padding: const EdgeInsets.only(top: 8),
                                          child: _InfoRow(
                                            icon: Icons.label_outline,
                                            text: '${a.incident!.category}'
                                                ' · Severity ${a.incident!.severity ?? '—'}',
                                          ),
                                        ),
                                      const SizedBox(height: 16),

                                      SizedBox(
                                        width: double.infinity,
                                        child: ElevatedButton(
                                          onPressed: () => Navigator.pushNamed(
                                              context, '/viewdetails',
                                              arguments: a),
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
                                      ),
                                    ],
                                  ),
                                ),
                              )),

                          const SizedBox(height: 16),

                          // Complaint history
                          _SectionCard(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 16, vertical: 14),
                            child: InkWell(
                              onTap: () => Navigator.pushNamed(
                                  context, '/offsite-history'),
                              borderRadius: BorderRadius.circular(20),
                              child: Row(
                                children: [
                                  Container(
                                    width: 44,
                                    height: 44,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFE6F5EE),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: const Icon(Icons.history,
                                        color: AppColors.green),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        const Text(
                                          'COMPLAINT HISTORY',
                                          style: TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.w800,
                                            color: AppColors.navy,
                                            letterSpacing: 0.8,
                                          ),
                                        ),
                                        const SizedBox(height: 3),
                                        Text(
                                          historyAssignments.isEmpty
                                              ? 'No resolved or rejected complaints'
                                              : '${historyAssignments.length} resolved / rejected complaint(s)',
                                          style: const TextStyle(
                                            fontSize: 11,
                                            color: AppColors.textMuted,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const Icon(Icons.chevron_right,
                                      color: Color(0xFFC0C9D4), size: 22),
                                ],
                              ),
                            ),
                          ),
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

/// A pill action on a complaint card.
///
/// Compact by design: the triage set shares one line with the status badge, so
/// the label is centred and wrapped in a [FittedBox] that scales it down
/// rather than letting it overflow or ellipsise on a narrow phone. There is no
/// icon — the three icons cost roughly 57dp in total, which is the difference
/// between fitting the row and not.
class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.label,
    required this.color,
    required this.onTap,
    this.expand = false,
  });

  final String label;
  final Color color;
  final VoidCallback onTap;

  /// Take an equal share of the row rather than hugging the label.
  final bool expand;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: expand ? double.infinity : null,
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 7),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(999),
        ),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
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

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color = AppColors.orange;
    Color bgColor = const Color(0xFFFFF4E5);

    final s = status.toUpperCase();
    if (s == 'RESOLVED') {
      color = AppColors.green;
      bgColor = const Color(0xFFE6F5EE);
    } else if (s == 'ASSIGNED') {
      color = AppColors.orange;
      bgColor = const Color(0xFFFFF4E5);
    } else if (s == 'IN_PROGRESS') {
      color = AppColors.blueLink;
      bgColor = const Color(0xFFE7F1FF);
    } else if (s == 'OPEN' || s == 'REJECTED') {
      color = AppColors.red;
      bgColor = const Color(0xFFFEE2E2);
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
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 5),
          Text(
            s,
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.4,
            ),
          ),
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