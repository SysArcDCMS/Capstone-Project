import '../models/user_model.dart';
import 'api_config.dart';
import 'api_service.dart';

class AuthService {
  final ApiService _api = ApiService.instance;

  /// POST /api/auth/login — saves the JWT on success.
  Future<User> login(String email, String password) async {
    final data = await _api.post(ApiConfig.login, data: {
      'email': email.trim(),
      'password': password,
    });
    if (data is! Map<String, dynamic>) {
      throw ApiException(0, 'Unexpected response from server.');
    }
    return _applyToken(data);
  }

  /// POST /api/auth/register — saves the JWT on success.
  Future<User> register({
    required String fullName,
    required String email,
    required String password,
    String? contactNo,
    String? address,
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
    return _applyToken(data);
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

  /// POST /api/auth/refresh — returns the new access token.
  Future<String> refresh() async {
    final data = await _api.post(ApiConfig.refresh);
    final token = data is Map<String, dynamic> ? data['access_token'] : null;
    if (token is! String || token.isEmpty) {
      throw ApiException(0, 'Token refresh failed.');
    }
    await _api.saveToken(token);
    return token;
  }

  /// Parses `{access_token, token_type, expires_in, user}`, persists the
  /// token, then returns the user.
  Future<User> _applyToken(Map<String, dynamic> data) async {
    final token = data['access_token'];
    final userMap = data['user'];
    if (token is! String || token.isEmpty || userMap is! Map<String, dynamic>) {
      throw ApiException(0, 'Unexpected response from server.');
    }
    await _api.saveToken(token);
    return User.fromJson(userMap);
  }
}