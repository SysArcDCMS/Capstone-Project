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
/// - Reads the JWT from secure storage and attaches it as a Bearer token.
/// - On any 401 it clears the stored token and fires [onUnauthorized] so the
///   app can drop back to the Login screen.
/// - All helper methods translate Dio errors into [ApiException].
class ApiService {
  ApiService._();

  static final ApiService instance = ApiService._();

  static const String _tokenKey = 'access_token';
  static const _storage = FlutterSecureStorage();

  /// Set by the app root to force-navigate to Login when a token dies.
  VoidCallback? onUnauthorized;

  late final Dio dio = Dio(
    BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: const {'Accept': 'application/json'},
    ),
  );

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
          if (error.response?.statusCode == 401) {
            await deleteToken();
            onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }

  Future<String?> getToken() => _storage.read(key: _tokenKey);

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> deleteToken() => _storage.delete(key: _tokenKey);

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