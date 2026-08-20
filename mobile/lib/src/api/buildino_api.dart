import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.code});

  final String message;
  final int? statusCode;
  final String? code;

  @override
  String toString() => message;
}

class BuildinoApi {
  BuildinoApi({
    required this.baseUrl,
    required this.deviceId,
    this.appVersion = '1.0.0',
    this.platform = 'android',
    http.Client? client,
  }) : _client = client ?? http.Client();

  final String baseUrl;
  final String deviceId;
  final String appVersion;
  final String platform;
  final http.Client _client;
  String? token;

  Uri _uri(String path, [Map<String, Object?> query = const {}]) {
    final base = Uri.parse(baseUrl);
    if (!base.hasScheme || !base.hasAuthority) {
      throw const ApiException('نشانی API معتبر نیست.');
    }
    if (
        base.scheme != 'https' &&
        !<String>{'localhost', '127.0.0.1', '10.0.2.2'}.contains(base.host)) {
      throw const ApiException('ارتباط API خارج از محیط محلی باید HTTPS باشد.');
    }

    final normalized = path.startsWith('/') ? path : '/$path';
    return base.replace(
      path: '${base.path.replaceFirst(RegExp(r'/$'), '')}$normalized',
      queryParameters: query.isEmpty
          ? null
          : query.map((key, value) => MapEntry(key, '$value')),
    );
  }

  Map<String, String> get _headers => <String, String>{
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-App-Version': appVersion,
        'X-Device-Id': deviceId,
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<String> login({
    required String login,
    required String password,
  }) async {
    final payload = await _json(
      await _client.post(
        _uri('/api/v1/auth/password/login'),
        headers: _headers,
        body: jsonEncode(<String, Object?>{
          'login': login,
          'password': password,
          'device_name': 'Buildino Mobile',
          'device_id': deviceId,
          'platform': platform,
        }),
      ),
    );

    final accessToken = payload['access_token'];
    if (accessToken is! String || accessToken.isEmpty) {
      throw const ApiException('توکن ورود از سرور دریافت نشد.');
    }
    token = accessToken;
    return accessToken;
  }

  Future<void> logout() async {
    await _json(
      await _client.post(
        _uri('/api/v1/auth/logout'),
        headers: _headers,
        body: jsonEncode(<String, Object?>{'device_id': deviceId}),
      ),
      allowEmpty: true,
    );
    token = null;
  }

  Future<Map<String, dynamic>> bootstrap() async => _data(
        await _client.get(
          _uri('/api/v1/app/bootstrap'),
          headers: _headers,
        ),
      );

  Future<List<Map<String, dynamic>>> invoicesForUnit(int unitId) async =>
      _collection(
        await _client.get(
          _uri('/api/v1/units/$unitId/invoices', {'per_page': 100}),
          headers: _headers,
        ),
      );

  Future<Map<String, dynamic>> invoice(int invoiceId) async => _data(
        await _client.get(
          _uri('/api/v1/invoices/$invoiceId'),
          headers: _headers,
        ),
      );

  Future<Map<String, dynamic>> loyaltyAccount() async => _data(
        await _client.get(
          _uri('/api/v1/loyalty/me'),
          headers: _headers,
        ),
      );

  Future<List<Map<String, dynamic>>> rewards() async => _collection(
        await _client.get(
          _uri('/api/v1/loyalty/rewards', {'per_page': 100}),
          headers: _headers,
        ),
      );

  Future<List<Map<String, dynamic>>> claims() async => _collection(
        await _client.get(
          _uri('/api/v1/loyalty/claims', {'per_page': 100}),
          headers: _headers,
        ),
      );

  Future<Map<String, dynamic>> claimReward(int rewardId) async => _data(
        await _client.post(
          _uri('/api/v1/loyalty/rewards/$rewardId/claims'),
          headers: _headers,
          body: jsonEncode(<String, Object?>{
            'idempotency_key':
                'mobile-$deviceId-$rewardId-${DateTime.now().microsecondsSinceEpoch}',
          }),
        ),
      );

  Future<Uint8List> paymentReceipt(int paymentId) async {
    final response = await _client.get(
      _uri('/api/v1/payments/$paymentId/receipt'),
      headers: _headers,
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      await _json(response);
    }
    return response.bodyBytes;
  }

  Future<Map<String, dynamic>> _data(http.Response response) async {
    final payload = await _json(response);
    final data = payload['data'];
    if (data is! Map<String, dynamic>) {
      throw const ApiException('ساختار پاسخ سرور معتبر نیست.');
    }
    return data;
  }

  Future<List<Map<String, dynamic>>> _collection(http.Response response) async {
    final payload = await _json(response);
    final data = payload['data'];
    if (data is! List<dynamic>) {
      throw const ApiException('ساختار فهرست سرور معتبر نیست.');
    }
    return data.whereType<Map<String, dynamic>>().toList(growable: false);
  }

  Future<Map<String, dynamic>> _json(
    http.Response response, {
    bool allowEmpty = false,
  }) async {
    Map<String, dynamic> payload = <String, dynamic>{};
    if (response.bodyBytes.isNotEmpty) {
      final decoded = jsonDecode(utf8.decode(response.bodyBytes));
      if (decoded is Map<String, dynamic>) {
        payload = decoded;
      }
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = payload['errors'];
      final message = payload['message'] ??
          (errors is Map<String, dynamic>
              ? errors.values
                  .whereType<List<dynamic>>()
                  .expand((items) => items)
                  .join(' | ')
              : null) ??
          'خطای ارتباط با سرور (${response.statusCode})';
      throw ApiException(
        '$message',
        statusCode: response.statusCode,
        code: payload['code'] as String?,
      );
    }

    if (!allowEmpty && payload.isEmpty) {
      throw const ApiException('پاسخ سرور خالی است.');
    }
    return payload;
  }

  void close() => _client.close();
}
