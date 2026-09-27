import 'dart:async';

import '../models/notification_model.dart';
import 'api_config.dart';
import 'api_service.dart';

class NotificationService {
  final ApiService _api = ApiService.instance;

  /// GET /api/notifications
  /// Returns notifications plus the server's unread count.
  Future<({List<AppNotification> items, int unreadCount})> getNotifications({
    bool unreadOnly = false,
  }) async {
    final data = await _api.get(ApiConfig.notifications, query: {
      if (unreadOnly) 'unread_only': unreadOnly,
      'per_page': 100,
    });
    final rawList = data is Map<String, dynamic> ? data['data'] : null;
    final items = rawList is List
        ? rawList.whereType<Map<String, dynamic>>().map(AppNotification.fromJson).toList()
        : <AppNotification>[];
    final unread = data is Map<String, dynamic> ? data['unread_count'] : null;
    return (
      items: items,
      unreadCount: (unread is num) ? unread.toInt() : items.where((n) => !n.isRead).length,
    );
  }

  /// GET /api/notifications?unread_only=true — polled by the unread badge.
  Future<int> unreadCount() async {
    final result = await getNotifications(unreadOnly: true);
    return result.unreadCount;
  }

  /// PATCH /api/notifications/{id}/read
  Future<void> markRead(int id) async {
    await _api.patch(ApiConfig.notificationRead(id));
  }

  /// POST /api/notifications/mark-all-read
  Future<void> markAllRead() async {
    await _api.post(ApiConfig.notificationMarkAllRead);
  }

  /// Reports the unread count every [interval], used by the badge watcher.
  Stream<int> watchUnread({Duration interval = const Duration(seconds: 30)}) {
    return Stream.periodic(interval, (_) => 0).asyncMap((_) => unreadCount());
  }
}