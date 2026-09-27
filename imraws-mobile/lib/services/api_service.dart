import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api_config.dart';

/// Thrown for any non-2xx API response (or network failure).
///
/// Carries the parsed Laravel `message` and field-level `errors` map so
/// screens can show friendly, actionable text.
class ApiException implements Exception {
  final int statusCode;
  final String message;
  final Map<String, dynamic>? errors;

  ApiException(this.statusCode, this.message, [this.errors]);

  bool get isUnauthorized => statusCode == 401;
  bool get isValidation => statusCode == 422;

  @override
  String toString() => 'ApiException($statusCode): $message';
}

/// Thin, shared Dio client.
///
/// - Attaches the live JWT as a Bearer token. The token always lives in RAM
///   for the current session and is mirrored into secure storage only when
///   the user ticks "Remember Me".
/// - On a 401 it attempts exactly one silent refresh and replays the request;
///   only if that fails does it clear the token and fire [onUnauthorized].
/// - All helper methods translate Dio errors into [ApiException].
class ApiService {
  ApiService._();

  static final ApiService instance = ApiService._();

  static const String _tokenKey = 'access_token';
  static const String _rememberKey = 'remember_me';
  static const _storage = FlutterSecureStorage();

  /// Set by the app root to force-navigate to Login when a token dies.
  VoidCallback? onUnauthorized;

  /// The live token for this process. Populated for the whole session —
  /// storage is only the copy that has to survive an app restart.
  String? _memoryToken;

  late final Dio dio = Dio(
    BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: const {'Accept': 'application/json'},
    ),
  );

  /// Un-intercepted client used only for the refresh call, so the retry path
  /// can never re-enter the interceptor that triggered it.
  late final Dio _refreshClient = Dio(
    BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: const {'Accept': 'application/json'},
    ),
  );

  /// De-duplicates concurrent refreshes, so a burst of parallel 401s causes
  /// one token exchange rather than one per in-flight request.
  Future<String>? _refreshInFlight;

  bool _initialized = false;

  void init() {
    if (_initialized) return;
    _initialized = true;

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await getToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onResponse: (response, handler) => handler.next(response),
        onError: (error, handler) async {
          final request = error.requestOptions;
          final isExpired = error.response?.statusCode == 401;
          final canRetry = isExpired &&
              request.path != ApiConfig.refresh &&
              request.extra['auth_retried'] != true;

          if (canRetry) {
            try {
              final fresh = await _refreshToken();
              request.extra['auth_retried'] = true;
              request.headers['Authorization'] = 'Bearer $fresh';
              final replayed = await dio.fetch<dynamic>(request);
              handler.resolve(replayed);
              return;
            } catch (_) {
              // Refresh failed — fall through to the hard logout below.
            }
          }

          if (isExpired) {
            await deleteToken();
            onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }

  /// Memory first, then secure storage (the persisted "Remember Me" copy).
  Future<String?> getToken() async {
    final memory = _memoryToken;
    if (memory != null && memory.isNotEmpty) return memory;
    return _storage.read(key: _tokenKey);
  }

  /// Holds [token] in RAM for the session. With `persist: true` it is also
  /// written to secure storage so the next launch can restore it; with
  /// `persist: false` any previously stored copy is erased, which is what
  /// makes an unchecked "Remember Me" log the user out when the app closes.
  Future<void> saveToken(String token, {required bool persist}) async {
    _memoryToken = token;
    if (persist) {
      await _storage.write(key: _tokenKey, value: token);
    } else {
      await _storage.delete(key: _tokenKey);
    }
  }

  Future<void> deleteToken() async {
    _memoryToken = null;
    await _storage.delete(key: _tokenKey);
  }

  /// Whether the user last asked to stay signed in across app restarts.
  Future<bool> isRemembered() async =>
      await _storage.read(key: _rememberKey) == 'true';

  Future<void> setRemembered(bool value) => _storage.write(
        key: _rememberKey,
        value: value ? 'true' : 'false',
      );

  /// Exchanges the live token for a fresh one. Shared across callers so only
  /// one network round-trip happens per expiry.
  Future<String> _refreshToken() {
    return _refreshInFlight ??= _performRefresh().whenComplete(() {
      _refreshInFlight = null;
    });
  }

  Future<String> _performRefresh() async {
    final token = await getToken();
    if (token == null || token.isEmpty) {
      throw ApiException(401, 'No session to refresh.');
    }

    final res = await _refreshClient.post<dynamic>(
      ApiConfig.refresh,
      data: {'token': token},
    );
    final data = res.data;
    final fresh = data is Map<String, dynamic> ? data['access_token'] : null;
    if (fresh is! String || fresh.isEmpty) {
      throw ApiException(0, 'Token refresh failed.');
    }

    await saveToken(fresh, persist: await isRemembered());
    return fresh;
  }

  // ── HTTP verbs ──────────────────────────────────────────────────────

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    try {
      final res = await dio.get<dynamic>(path, queryParameters: query);
      return res.data;
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  Future<dynamic> post(String path, {Object? data}) async {
    try {
      final res = await dio.post<dynamic>(path, data: data);
      return res.data;
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  Future<dynamic> patch(String path, {Object? data}) async {
    try {
      final res = await dio.patch<dynamic>(path, data: data);
      return res.data;
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  Future<dynamic> put(String path, {Object? data}) async {
    try {
      final res = await dio.put<dynamic>(path, data: data);
      return res.data;
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  Future<dynamic> delete(String path) async {
    try {
      final res = await dio.delete<dynamic>(path);
      return res.data;
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  /// Multipart upload of a single `file` field plus an optional `caption`.
  Future<dynamic> upload(
    String path, {
    required Uint8List bytes,
    required String filename,
    String? caption,
  }) async {
    try {
      final form = FormData.fromMap({
        'file': MultipartFile.fromBytes(bytes, filename: filename),
        if (caption != null && caption.isNotEmpty) 'caption': caption,
      });
      final res = await dio.post<dynamic>(path, data: form);
      return res.data;
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  ApiException _toApiException(DioException e) {
    final status = e.response?.statusCode;
    final data = e.response?.data;

    if (status == null || data == null) {
      return ApiException(
        0,
        'Network error. Please check your connection and try again.',
      );
    }

    if (data is Map<String, dynamic>) {
      final message = data['message']?.toString() ?? 'Request failed.';
      final rawErrors = data['errors'];
      if (rawErrors is Map<String, dynamic>) {
        return ApiException(status, message, rawErrors);
      }
      return ApiException(status, message);
    }

    return ApiException(status, 'Request failed.');
  }

  /// Creates the friendly message string used by providers/screens.
  static String flattenValidation(ApiException e) {
    if (e.errors == null || e.errors!.isEmpty) return e.message;
    final first = e.errors!.entries.first;
    final values = first.value;
    if (values is List && values.isNotEmpty) {
      return values.first.toString();
    }
    return e.message;
  }
}