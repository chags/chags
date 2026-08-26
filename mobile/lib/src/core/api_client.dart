import 'dart:developer' as developer;

import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../config/app_config.dart';
import 'session_store.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});
  final String message;
  final int? statusCode;
  @override
  String toString() => message;
}

class ApiClient {
  ApiClient(this.session)
    : dio = Dio(
        BaseOptions(
          baseUrl: AppConfig.apiBaseUrl,
          connectTimeout: const Duration(seconds: 15),
          sendTimeout: const Duration(seconds: 20),
          receiveTimeout: const Duration(seconds: 30),
        ),
      );
  final SessionStore session;
  final Dio dio;

  Future<Map<String, dynamic>> requestCode(String phone) async {
    final response = await _send(
      () async => dio.post(
        '/app-unlock/whatsapp/request',
        data: {
          'phone': phone,
          'device_installation_id': await session.installationId(),
        },
      ),
    );
    return _data(response);
  }

  Future<Map<String, dynamic>> verifyCode(
    String challengeId,
    String code,
  ) async {
    final response = await _send(
      () async => dio.post(
        '/app-unlock/whatsapp/verify',
        data: {
          'challenge_id': challengeId,
          'device_installation_id': await session.installationId(),
          'code': code,
        },
      ),
    );
    final data = _data(response);
    await session.saveToken(data['access_token'] as String);
    return data;
  }

  Future<Map<String, dynamic>> me() async =>
      _data(await _authorized('GET', '/me'));

  Future<Map<String, dynamic>> createDeviceChallenge(
    String purpose,
    String platform,
  ) async => _data(
    await _authorized(
      'POST',
      '/devices/challenges',
      data: {
        'installation_id': await session.installationId(),
        'platform': platform,
        'purpose': purpose,
      },
    ),
  );

  Future<Map<String, dynamic>> registerDevice(
    Map<String, dynamic> payload,
  ) async =>
      _data(await _authorized('POST', '/devices/register', data: payload));
  Future<Map<String, dynamic>> punchStatus() async =>
      _data(await _authorized('GET', '/time-punch/status'));
  Future<Map<String, dynamic>> punch() async => _data(
    await _authorized(
      'POST',
      '/time-punch',
      headers: {'Idempotency-Key': 'punch-${const Uuid().v4()}'},
    ),
  );
  Future<Map<String, dynamic>> timeCard(String month) async =>
      _data(await _authorized('GET', '/time-card', query: {'month': month}));
  Future<Map<String, dynamic>> adjustments() async =>
      _map((await _authorized('GET', '/time-adjustments')).data);

  Future<void> createAdjustment({
    required String date,
    required String type,
    required String time,
    required String reason,
  }) async {
    await _authorized(
      'POST',
      '/time-adjustments',
      data: {
        'work_date': date,
        'requested_entries': [
          {'type': type, 'time': time},
        ],
        'reason': reason,
      },
    );
  }

  Future<void> logout() async {
    try {
      await _authorized('POST', '/auth/logout');
    } finally {
      await session.clearSession();
    }
  }

  Future<Response<dynamic>> _authorized(
    String method,
    String path, {
    Object? data,
    Map<String, dynamic>? query,
    Map<String, dynamic>? headers,
  }) async {
    final token = await session.token;
    final deviceId = await session.deviceId;
    return _send(
      () => dio.request(
        path,
        data: data,
        queryParameters: query,
        options: Options(
          method: method,
          headers: {
            'Authorization': 'Bearer $token',
            'X-Device-ID': ?deviceId,
            ...?headers,
          },
        ),
      ),
    );
  }

  Future<Response<dynamic>> _send(
    Future<Response<dynamic>> Function() callback,
  ) async {
    try {
      return await callback();
    } on DioException catch (error) {
      final body = error.response?.data;
      String message = 'Não foi possível comunicar com o servidor.';
      if (body is Map && body['message'] is String) {
        message = body['message'] as String;
      }
      if (body is Map &&
          body['errors'] is Map &&
          (body['errors'] as Map).isNotEmpty) {
        final first = (body['errors'] as Map).values.first;
        if (first is List && first.isNotEmpty) message = first.first.toString();
      }
      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.sendTimeout ||
          error.type == DioExceptionType.receiveTimeout) {
        message =
            'O servidor demorou para responder. Tente novamente em instantes.';
      }
      developer.log(
        'Falha HTTP ${error.requestOptions.method} '
        '${error.requestOptions.path}; status=${error.response?.statusCode}; '
        'request_id=${error.response?.headers.value('x-request-id') ?? '-'}',
        name: 'chags.api',
        error: error.type,
      );
      throw ApiException(message, statusCode: error.response?.statusCode);
    }
  }

  Map<String, dynamic> _data(Response<dynamic> response) =>
      _map(response.data)['data'] as Map<String, dynamic>;
  Map<String, dynamic> _map(dynamic value) =>
      Map<String, dynamic>.from(value as Map);
}
