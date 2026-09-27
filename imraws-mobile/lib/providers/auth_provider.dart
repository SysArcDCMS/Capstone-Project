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
  bool _rememberMe = true;

  User? get user => _user;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _user != null;

  /// Last-used "Remember Me" choice, so the login checkbox restores its state.
  bool get rememberMe => _rememberMe;

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

    _rememberMe = await api.isRemembered();

    final token = await api.getToken();
    if (token == null || token.isEmpty) return;

    try {
      _user = await _authService.me();
      notifyListeners();
    } on ApiException {
      // Token is past its grace window — secure storage was cleared already.
      await api.deleteToken();
      _user = null;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>> login(
    String email,
    String password, {
    required bool rememberMe,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      // Store the preference before the token, so a crash between the two can
      // never leave a persisted token with a stale "don't remember" flag.
      // A failed login still keeps the choice, which is what the user last
      // asked for.
      _rememberMe = rememberMe;
      await ApiService.instance.setRemembered(rememberMe);

      _user = await _authService.login(email, password, persist: rememberMe);
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

  Future<Map<String, dynamic>> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    _isLoading = true;
    notifyListeners();

    try {
      await _authService.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
      );
      _isLoading = false;
      notifyListeners();
      return {'success': true, 'message': 'Password updated successfully.'};
    } on ApiException catch (e) {
      _isLoading = false;
      _error = e.message;
      notifyListeners();
      return {'success': false, 'message': ApiService.flattenValidation(e)};
    } catch (_) {
      _isLoading = false;
      _error = 'Something went wrong. Please try again.';
      notifyListeners();
      return {'success': false, 'message': _error!};
    }
  }

  Future<void> logout() async {
    await _authService.logout();
    _user = null;
    notifyListeners();
  }

  /// Asks the server to email a reset code.
  ///
  /// The reply is identical for unknown addresses, so `success` here does not
  /// confirm the account exists — the screen must not imply that it does.
  Future<Map<String, dynamic>> requestPasswordReset(String email) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final expiresIn = await _authService.requestPasswordReset(email);
      _isLoading = false;
      notifyListeners();
      return {'success': true, 'message': '', 'expires_in': expiresIn};
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

  /// Spends the emailed code and sets a new password.
  Future<Map<String, dynamic>> resetPassword({
    required String email,
    required String code,
    required String newPassword,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      await _authService.resetPassword(
        email: email,
        code: code,
        newPassword: newPassword,
      );
      _isLoading = false;
      notifyListeners();
      return {'success': true, 'message': 'Password updated. You can now log in.'};
    } on ApiException catch (e) {
      _isLoading = false;
      _error = e.message;
      notifyListeners();
      return {'success': false, 'message': ApiService.flattenValidation(e)};
    } catch (_) {
      _isLoading = false;
      _error = 'Something went wrong. Please try again.';
      notifyListeners();
      return {'success': false, 'message': _error!};
    }
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