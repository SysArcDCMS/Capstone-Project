import 'package:flutter/material.dart';

import '../app_globals.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

class AuthProvider with ChangeNotifier {
  final AuthService _authService = AuthService();

  User? _user;
  bool _isLoading = false;
  String? _error;

  User? get user => _user;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _user != null;

  bool get isCustomer => _user?.isCustomer ?? true;

  /// Which screen tree the logged-in user belongs in.
  String get homeRoute => isCustomer ? '/home' : '/offsite';

  void navigateToHome(BuildContext context) {
    Navigator.pushNamedAndRemoveUntil(context, homeRoute, (route) => false);
  }

  /// Call once at startup: wires the 401 interceptor and attempts a silent
  /// session restore if a token is already stored.
  Future<void> init() async {
    final api = ApiService.instance;
    api.onUnauthorized = _forceLogout;
    api.init();

    final token = await api.getToken();
    if (token == null || token.isEmpty) return;

    try {
      _user = await _authService.me();
      notifyListeners();
    } on ApiException {
      // Token invalid/expired on boot — secure storage was cleared already.
      await api.deleteToken();
      _user = null;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _user = await _authService.login(email, password);
      _isLoading = false;
      notifyListeners();
      return {'success': true, 'message': ''};
    } on ApiException catch (e) {
      _isLoading = false;
      _error = e.message;
      notifyListeners();
      return {'success': false, 'message': e.message};
    } catch (_) {
      _isLoading = false;
      _error = 'Something went wrong. Please try again.';
      notifyListeners();
      return {'success': false, 'message': _error!};
    }
  }

  Future<Map<String, dynamic>> register({
    required String fullName,
    required String email,
    required String password,
    String? contactNo,
    String? address,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _user = await _authService.register(
        fullName: fullName,
        email: email,
        password: password,
        contactNo: contactNo,
        address: address,
      );
      _isLoading = false;
      notifyListeners();
      return {'success': true, 'message': ''};
    } on ApiException catch (e) {
      _isLoading = false;
      _error = e.message;
      notifyListeners();
      return {'success': false, 'message': e.message, 'errors': e.errors};
    } catch (_) {
      _isLoading = false;
      _error = 'Something went wrong. Please try again.';
      notifyListeners();
      return {'success': false, 'message': _error!};
    }
  }

  Future<Map<String, dynamic>> updateProfile({
    String? fullName,
    String? contactNo,
    String? address,
  }) async {
    try {
      _user = await _authService.updateProfile(
        fullName: fullName,
        contactNo: contactNo,
        address: address,
      );
      notifyListeners();
      return {'success': true, 'message': ''};
    } on ApiException catch (e) {
      return {'success': false, 'message': e.message};
    }
  }

  Future<void> logout() async {
    await _authService.logout();
    _user = null;
    notifyListeners();
  }

  Future<void> checkAuth() async {
    try {
      _user = await _authService.me();
    } on ApiException {
      _user = null;
    }
    notifyListeners();
  }

  void _forceLogout() {
    _user = null;
    _error = 'Your session has expired. Please log in again.';
    notifyListeners();
    appNavigatorKey.currentState?.pushNamedAndRemoveUntil(
      '/',
      (route) => false,
    );
  }
}