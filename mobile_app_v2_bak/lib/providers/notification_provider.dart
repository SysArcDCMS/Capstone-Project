import 'package:flutter/material.dart';

import '../models/notification_model.dart';
import '../services/api_service.dart';
import '../services/notification_service.dart';

class NotificationProvider with ChangeNotifier {
  final NotificationService _service = NotificationService();

  List<AppNotification> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = false;
  String? _error;

  List<AppNotification> get notifications => _notifications;
  int get unreadCount => _unreadCount;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> fetchNotifications() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final result = await _service.getNotifications();
      _notifications = result.items;
      _unreadCount = result.unreadCount;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Could not load notifications.';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> markRead(AppNotification notification) async {
    if (notification.isRead) return;
    try {
      await _service.markRead(notification.id);
      await fetchNotifications();
    } on ApiException {
      // Silent — badge refresh will retry on next fetch.
    }
  }

  Future<void> markAllRead() async {
    try {
      await _service.markAllRead();
      _unreadCount = 0;
      await fetchNotifications();
    } on ApiException {
      // ignore
    }
  }
}