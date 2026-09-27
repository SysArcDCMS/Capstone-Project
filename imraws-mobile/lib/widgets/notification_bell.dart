import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/notification_provider.dart';
import '../theme/app_colors.dart';

class NotificationBell extends StatefulWidget {
  const NotificationBell({super.key});

  @override
  State<NotificationBell> createState() => _NotificationBellState();
}

class _NotificationBellState extends State<NotificationBell> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        context.read<NotificationProvider>().fetchNotifications();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final unread = context.watch<NotificationProvider>().unreadCount;
    final badge = unread > 9 ? '9+' : '$unread';

    return Stack(
      clipBehavior: Clip.none,
      children: [
        IconButton(
          onPressed: () => Navigator.pushNamed(context, '/notifications'),
          icon: Icon(unread > 0 ? Icons.notifications : Icons.notifications_none),
          color: Colors.white,
          iconSize: 26,
          tooltip: 'Notifications',
        ),
        if (unread > 0)
          Positioned(
            right: 2,
            top: 2,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
              decoration: BoxDecoration(
                color: AppColors.red,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: AppColors.navy, width: 1.5),
              ),
              constraints: const BoxConstraints(minWidth: 18),
              child: Text(
                badge,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
      ],
    );
  }
}