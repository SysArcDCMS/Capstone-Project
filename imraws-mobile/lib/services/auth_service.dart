import '../models/user_model.dart';
import 'api_config.dart';
import 'api_service.dart';

class AuthService {
  final ApiService _api = ApiService.instance;

  /// POST /api/auth/login — saves the JWT on success.
  ///
  /// [persist] mirrors the "Remember Me" choice: true keeps the token across
  /// app restarts, false confines it to this session.
  Future<User> login(
    String email,
    String password, {
    required bool persist,
  }) async {
    final data = await _api.post(ApiConfig.login, data: {
      'email': email.trim(),
      'password': password,
    });
    if (data is! Map<String, dynamic>) {
      throw ApiException(0, 'Unexpected response from server.');
    }
    return _applyToken(data, persist: persist);
  }

  /// POST /api/auth/register — saves the JWT on success.
  ///
  /// The sign-up screen has no "Remember Me" control, so this defaults to a
  /// persisted session.
  Future<User> register({
    required String fullName,
    required String email,
    required String password,
    String? contactNo,
    String? address,
    bool persist = true,
  }) async {
    final data = await _api.post(ApiConfig.register, data: {
      'full_name': fullName.trim(),
      'email': email.trim(),
      'password': password,
      if (contactNo != null && contactNo.trim().isNotEmpty)
        'contact_no': contactNo.trim(),
      if (address != null && address.trim().isNotEmpty)
        'address': address.trim(),
    });
    if (data is! Map<String, dynamic>) {
      throw ApiException(0, 'Unexpected response from server.');
    }
    return _applyToken(data, persist: persist);
  }

  /// GET /api/auth/me
  Future<User> me() async {
    final data = await _api.get(ApiConfig.me);
    final userMap = data is Map<String, dynamic> ? data['data'] : null;
    if (userMap is! Map<String, dynamic>) {
      throw ApiException(0, 'Could not load profile.');
    }
    return User.fromJson(userMap);
  }

  /// PATCH /api/auth/profile
  Future<User> updateProfile({
    String? fullName,
    String? contactNo,
    String? address,
  }) async {
    final data = await _api.patch(ApiConfig.profile, data: {
      if (fullName != null) 'full_name': fullName,
      if (contactNo != null) 'contact_no': contactNo,
      if (address != null) 'address': address,
    });
    final userMap = data is Map<String, dynamic> ? data['data'] : null;
    if (userMap is! Map<String, dynamic>) {
      throw ApiException(0, 'Could not load profile.');
    }
    return User.fromJson(userMap);
  }

  /// POST /api/auth/change-password
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    await _api.post(ApiConfig.changePassword, data: {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
  }

  /// POST /api/auth/logout
  Future<void> logout() async {
    try {
      await _api.post(ApiConfig.logout);
    } on ApiException {
      // Token may already be dead — still clear local storage below.
    } finally {
      await _api.deleteToken();
    }
  }

  /// POST /api/auth/forgot-password — emails a 6-digit reset code.
  ///
  /// The server answers identically for unknown addresses, so a success here
  /// does not confirm the account exists. [expiresIn] is the code lifetime in
  /// seconds, for the countdown hint on screen.
  Future<int> requestPasswordReset(String email) async {
    final data = await _api.post(ApiConfig.forgotPassword, data: {
      'email': email.trim(),
    });
    final expiresIn = data is Map<String, dynamic> ? data['expires_in'] : null;
    return expiresIn is int ? expiresIn : 600;
  }

  /// POST /api/auth/reset-password — spends the code and sets a new password.
  ///
  /// Throws [ApiException] with 422 for a wrong, spent or expired code and 429
  /// once the attempt budget is spent.
  Future<void> resetPassword({
    required String email,
    required String code,
    required String newPassword,
  }) async {
    await _api.post(ApiConfig.resetPassword, data: {
      'email': email.trim(),
      'code': code.trim(),
      'password': newPassword,
      'password_confirmation': newPassword,
    });
  }

  /// POST /api/auth/refresh — returns the new access token.
  ///
  /// The interceptor drives refresh automatically; this is here for the boot
  /// path and for any explicit retry. Re-persists according to the stored
  /// "Remember Me" preference so a session-only login never becomes durable.
  Future<String> refresh() async {
    final data = await _api.post(ApiConfig.refresh, data: {
      'token': await _api.getToken(),
    });
    final token = data is Map<String, dynamic> ? data['access_token'] : null;
    if (token is! String || token.isEmpty) {
      throw ApiException(0, 'Token refresh failed.');
    }
    await _api.saveToken(token, persist: await _api.isRemembered());
    return token;
  }

  /// Parses `{access_token, token_type, expires_in, user}`, persists the
  /// token, then returns the user.
  Future<User> _applyToken(
    Map<String, dynamic> data, {
    required bool persist,
  }) async {
    final token = data['access_token'];
    final userMap = data['user'];
    if (token is! String || token.isEmpty || userMap is! Map<String, dynamic>) {
      throw ApiException(0, 'Unexpected response from server.');
    }
    await _api.saveToken(token, persist: persist);
    return User.fromJson(userMap);
  }
}