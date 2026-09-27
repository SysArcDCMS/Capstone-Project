import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../models/assignment_model.dart';
import '../providers/assignment_provider.dart';
import '../providers/auth_provider.dart';
import '../theme/app_colors.dart';
import '../widgets/bottom_nav_bar.dart';
import '../widgets/maynilad_logo.dart';

/// Read-only list of the team leader's resolved / rejected complaints.
class OffsiteHistoryScreen extends StatefulWidget {
  const OffsiteHistoryScreen({super.key});

  @override
  State<OffsiteHistoryScreen> createState() => _OffsiteHistoryScreenState();
}

class _OffsiteHistoryScreenState extends State<OffsiteHistoryScreen> {
  static const _historyStatuses = {'resolved', 'reject', 'reassign'};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AssignmentProvider>().fetchData();
    });
  }

  List<Assignment> _historyOf(List<Assignment> assignments) {
    return assignments
        .where((a) =>
            _historyStatuses.contains(a.actionStatus) ||
            (a.incident?.status ?? '') == 'resolved')
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final provider = context.watch<AssignmentProvider>();
    final history = _historyOf(provider.assignments);

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
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'COMPLAINT HISTORY',
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.55),
                            fontSize: 11,
                            letterSpacing: 1.2,
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
                  ),
                ],
              ),
            ),

            // ── Body ────────────────────────────────────────────
            Expanded(
              child: RefreshIndicator(
                onRefresh: () =>
                    context.read<AssignmentProvider>().fetchData(),
                child: provider.isLoading && history.isEmpty
                    ? const Center(child: CircularProgressIndicator())
                    : history.isEmpty
                        ? ListView(
                            children: const [
                              Padding(
                                padding: EdgeInsets.all(40),
                                child: Column(
                                  children: [
                                    Icon(
                                      Icons.history,
                                      size: 56,
                                      color: AppColors.textMuted,
                                    ),
                                    SizedBox(height: 12),
                                    Text(
                                      'No resolved or rejected complaints.',
                                      style: TextStyle(
                                        color: AppColors.textMuted,
                                        fontSize: 14,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          )
                        : ListView(
                            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                            children: [
                              ...history.map((a) => Padding(
                                    padding: const EdgeInsets.only(bottom: 12),
                                    child: Container(
                                      padding: const EdgeInsets.all(16),
                                      decoration: BoxDecoration(
                                        color: AppColors.cardBg,
                                        borderRadius: BorderRadius.circular(20),
                                        boxShadow: [
                                          BoxShadow(
                                            color: AppColors.navy
                                                .withOpacity(0.08),
                                            blurRadius: 12,
                                            offset: const Offset(0, 2),
                                          ),
                                        ],
                                      ),
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          _HistoryBadge(assignment: a),
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
                                            a.assignedAt != null
                                                ? DateFormat(
                                                        'MMM dd, yyyy · hh:mm a')
                                                    .format(a.assignedAt!)
                                                : '',
                                            style: const TextStyle(
                                              fontSize: 10.5,
                                              color: AppColors.textMuted,
                                            ),
                                          ),
                                          const SizedBox(height: 10),
                                          Row(
                                            children: [
                                              const Icon(
                                                Icons.location_on_outlined,
                                                size: 15,
                                                color: AppColors.textMuted,
                                              ),
                                              const SizedBox(width: 8),
                                              Expanded(
                                                child: Text(
                                                  a.incident?.location ??
                                                      'No location provided',
                                                  style: const TextStyle(
                                                    fontSize: 12,
                                                    color: Color(0xFF4B5563),
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ),
                                          const SizedBox(height: 12),
                                          SizedBox(
                                            width: double.infinity,
                                            child: ElevatedButton(
                                              onPressed: () => Navigator
                                                  .pushNamed(context,
                                                      '/viewdetails',
                                                      arguments: a),
                                              style: ElevatedButton.styleFrom(
                                                backgroundColor:
                                                    AppColors.navy,
                                                foregroundColor: Colors.white,
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                        vertical: 10),
                                                shape: const StadiumBorder(),
                                                elevation: 0,
                                              ),
                                              child: const Text(
                                                'View Details',
                                                style: TextStyle(
                                                  fontSize: 13,
                                                  fontWeight: FontWeight.w700,
                                                ),
                                              ),
                                            ),
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
              active: NavTab.history,
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

class _HistoryBadge extends StatelessWidget {
  final Assignment assignment;
  const _HistoryBadge({required this.assignment});

  @override
  Widget build(BuildContext context) {
    final rejected = assignment.actionStatus == 'reject' ||
        (assignment.incident?.status ?? '') == 'rejected';
    final color = rejected ? AppColors.red : AppColors.green;
    final bgColor =
        rejected ? const Color(0xFFFEE2E2) : const Color(0xFFE6F5EE);
    final label = rejected ? 'REJECTED' : 'RESOLVED';

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
            label,
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