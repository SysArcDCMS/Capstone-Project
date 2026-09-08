import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../theme/app_colors.dart';
import '../widgets/maynilad_logo.dart';
import '../widgets/bottom_nav_bar.dart';
import '../providers/auth_provider.dart';
import '../providers/incident_provider.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<IncidentProvider>().fetchMyIncidents();
    });
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final incidentProvider = context.watch<IncidentProvider>();
    final incidents = incidentProvider.myIncidents;
    final latestIncident = incidents.isNotEmpty ? incidents.first : null;

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
                        'Good morning,',
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.55),
                          fontSize: 11,
                        ),
                      ),
                      Text(
                        'Hello, ${user?.fullName ?? 'Client'}!',
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

            // ── Body ────────────────────────────────────────────
            Expanded(
              child: RefreshIndicator(
                onRefresh: () => incidentProvider.fetchMyIncidents(),
                child: incidentProvider.isLoading && incidents.isEmpty
                    ? const Center(child: CircularProgressIndicator())
                    : ListView(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                        children: [
                          // Status card (Latest)
                          if (latestIncident != null)
                            _Card(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const _SectionLabel('Latest Incident'),
                                  const SizedBox(height: 12),

                                  // Badge
                                  _StatusBadge(status: latestIncident.status ?? 'OPEN'),
                                  const SizedBox(height: 10),

                                  Text(
                                    'Complaint ID #MNL-${latestIncident.id}',
                                    style: const TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.navy,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    latestIncident.submittedAt != null
                                        ? DateFormat('MMM dd, yyyy · hh:mm a').format(latestIncident.submittedAt!)
                                        : 'Unknown date',
                                    style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
                                  ),
                                  const SizedBox(height: 12),

                                  Container(
                                    padding: const EdgeInsets.all(12),
                                    decoration: BoxDecoration(
                                      color: AppColors.pageBg,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Text(
                                      latestIncident.description ?? 'No description provided.',
                                      style: const TextStyle(fontSize: 12, color: Color(0xFF4B5563), height: 1.55),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          
                          if (latestIncident == null && !incidentProvider.isLoading)
                            const _Card(child: Center(child: Text('No complaints submitted yet.'))),

                          const SizedBox(height: 14),

                          // History card
                          if (incidents.length > 1)
                            _Card(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const _SectionLabel('History'),
                                  const SizedBox(height: 14),
                                  ...incidents.skip(1).take(5).toList().asMap().entries.map((e) {
                                    final i = e.key;
                                    final item = e.value;
                                    return Column(
                                      children: [
                                        Row(
                                          crossAxisAlignment: CrossAxisAlignment.center,
                                          children: [
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    '#MNL-${item.id}',
                                                    style: const TextStyle(
                                                      fontSize: 13,
                                                      fontWeight: FontWeight.w700,
                                                      color: AppColors.navy,
                                                    ),
                                                  ),
                                                  const SizedBox(height: 3),
                                                  Text(
                                                    item.submittedAt != null
                                                        ? DateFormat('MMM dd, yyyy · hh:mm a').format(item.submittedAt!)
                                                        : '',
                                                    style: const TextStyle(
                                                      fontSize: 10.5,
                                                      color: AppColors.textMuted,
                                                    ),
                                                  ),
                                                  const SizedBox(height: 7),
                                                  _StatusBadge(status: item.status ?? 'OPEN', small: true),
                                                ],
                                              ),
                                            ),
                                            ElevatedButton(
                                              onPressed: () => Navigator.pushNamed(context, '/viewdetails', arguments: item),
                                              style: ElevatedButton.styleFrom(
                                                backgroundColor: AppColors.navy,
                                                foregroundColor: Colors.white,
                                                padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 10),
                                                shape: const StadiumBorder(),
                                                elevation: 0,
                                              ),
                                              child: const Text('View Details', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                                            ),
                                          ],
                                        ),
                                        if (i < incidents.length - 2) ...[
                                          const SizedBox(height: 14),
                                          const Divider(color: AppColors.divider, height: 1),
                                          const SizedBox(height: 14),
                                        ],
                                      ],
                                    );
                                  }),
                                ],
                              ),
                            ),
                        ],
                      ),
              ),
            ),

            // ── Bottom Nav ───────────────────────────────────────
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
  final bool small;
  const _StatusBadge({required this.status, this.small = false});

  @override
  Widget build(BuildContext context) {
    Color color = AppColors.orange;
    Color bgColor = const Color(0xFFFFF4E5);
    
    if (status.toUpperCase() == 'RESOLVED' || status.toUpperCase() == 'COMPLETE') {
      color = AppColors.green;
      bgColor = const Color(0xFFE6F5EE);
    } else if (status.toUpperCase() == 'OPEN') {
      color = AppColors.red;
      bgColor = const Color(0xFFFEE2E2);
    }

    return Container(
      padding: EdgeInsets.symmetric(horizontal: small ? 10 : 12, vertical: small ? 3 : 5),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color, width: small ? 1 : 1.5),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (!small) ...[
            Container(
              width: 7,
              height: 7,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
            const SizedBox(width: 6),
          ],
          Text(
            status.toUpperCase(),
            style: TextStyle(
              color: color,
              fontSize: small ? 9.5 : 10.5,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.8,
            ),
          ),
        ],
      ),
    );
  }
}

class _Card extends StatelessWidget {
  final Widget child;
  const _Card({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
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
      style: const TextStyle(
        fontSize: 10.5,
        fontWeight: FontWeight.w700,
        color: AppColors.textMuted,
        letterSpacing: 1.2,
      ),
    );
  }
}
