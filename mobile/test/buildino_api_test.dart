import 'package:buildino_mobile/src/api/buildino_api.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('password login stores the stable Sanctum token contract', () async {
    final client = MockClient((request) async {
      expect(request.url.path, '/api/v1/auth/password/login');
      expect(request.headers['X-App-Version'], '1.0.0');
      return http.Response(
        '{"data":{"id":1},"access_token":"test-token","token_type":"Bearer"}',
        200,
        headers: <String, String>{'content-type': 'application/json'},
      );
    });
    final api = BuildinoApi(
      baseUrl: 'https://buildino.test',
      deviceId: 'device-test',
      client: client,
    );

    expect(
      await api.login(login: '09120000000', password: 'secret'),
      'test-token',
    );
    expect(api.token, 'test-token');
  });

  test('API error envelope is converted to a user-safe exception', () async {
    final api = BuildinoApi(
      baseUrl: 'https://buildino.test',
      deviceId: 'device-test',
      client: MockClient(
        (_) async => http.Response(
          '{"code":"UNAUTHENTICATED","message":"Authentication is required."}',
          401,
        ),
      ),
    );

    await expectLater(
      api.bootstrap(),
      throwsA(
        isA<ApiException>()
            .having((error) => error.statusCode, 'statusCode', 401)
            .having((error) => error.code, 'code', 'UNAUTHENTICATED'),
      ),
    );
  });
}
