import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/network/api_client.dart';
import 'package:app/core/network/api_exception.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';

Map<String, dynamic> tokensJson(String access, String refresh) => {
      'token_type': 'Bearer',
      'access_token': access,
      'refresh_token': refresh,
      'expires_at': DateTime.now().add(const Duration(hours: 24)).toIso8601String(),
      'refresh_expires_at': DateTime.now().add(const Duration(days: 60)).toIso8601String(),
    };

void main() {
  late FakeAdapter adapter;
  late MemoryTokenStorage tokens;
  late int expired;

  ApiClient client() => ApiClient(
        mobileBaseUrl: 'https://api.test/api/mobile/v1',
        storeBaseUrl: 'https://api.test/store-api/v1',
        tokens: tokens,
        dio: fakeDio(adapter),
        onSessionExpired: () => expired++,
        context: () => const RequestContext(
          tenantId: '42',
          locale: 'ar',
          currency: 'SAR',
          country: 'SA',
          platform: 'android',
          appVersion: '1.2.0',
        ),
      );

  setUp(() {
    tokens = MemoryTokenStorage(TokenPair.fromJson(tokensJson('access-1', 'refresh-1')));
    expired = 0;
    adapter = FakeAdapter((request) async => FakeReply.ok({'pong': true}));
  });

  test('sends tenant, locale, currency, country, app headers and the bearer token', () async {
    final response = await client().get('ping');

    final headers = adapter.requests.single.headers;
    expect(headers['X-Tenant'], '42');
    expect(headers['X-Locale'], 'ar');
    expect(headers['X-Currency'], 'SAR');
    expect(headers['X-Country'], 'SA');
    expect(headers['X-App-Platform'], 'android');
    expect(headers['X-App-Version'], '1.2.0');
    expect(headers['Authorization'], 'Bearer access-1');
    expect(response.map['pong'], isTrue);
  });

  test('public calls skip the bearer token', () async {
    await client().get('bootstrap', auth: false);

    expect(adapter.requests.single.headers.containsKey('Authorization'), isFalse);
  });

  test('maps the error envelope and 422 field errors', () async {
    adapter.handler = (request) async => request.path == 'auth/login'
        ? FakeReply.validation({'email': ['The email is wrong.']})
        : FakeReply.error(404, 'Store not found.');

    await expectLater(
      client().post('auth/login', body: {}, auth: false),
      throwsA(isA<ApiException>()
          .having((e) => e.kind, 'kind', ApiErrorKind.validation)
          .having((e) => e.fieldError('email'), 'email', 'The email is wrong.')),
    );
    await expectLater(
      client().get('bootstrap'),
      throwsA(isA<ApiException>()
          .having((e) => e.kind, 'kind', ApiErrorKind.notFound)
          .having((e) => e.message, 'message', 'Store not found.')),
    );
  });

  test('429 carries Retry-After', () async {
    adapter.handler = (request) async => const FakeReply(429, {'success': false, 'message': 'Slow down'}, {'Retry-After': '30'});

    await expectLater(
      client().get('me'),
      throwsA(isA<ApiException>().having((e) => e.retryAfter, 'retryAfter', const Duration(seconds: 30))),
    );
  });

  test('no connection is a network error', () async {
    adapter.offline = true;

    await expectLater(client().get('me'), throwsA(isA<ApiException>().having((e) => e.isNetwork, 'network', isTrue)));
  });

  test('401 refreshes the pair once and retries with the new access token', () async {
    adapter.handler = (request) async {
      if (request.path == 'auth/refresh') {
        expect(request.body, {'refresh_token': 'refresh-1'});
        expect(request.headers['X-Tenant'], '42');

        return FakeReply.ok({'tokens': tokensJson('access-2', 'refresh-2')});
      }

      return request.headers['Authorization'] == 'Bearer access-2'
          ? FakeReply.ok({'customer': {'id': 1}})
          : FakeReply.error(401, 'Unauthenticated.');
    };

    final response = await client().get('me');

    expect(response.map['customer'], {'id': 1});
    expect(adapter.to('auth/refresh'), hasLength(1));
    expect((await tokens.read())!.refreshToken, 'refresh-2');
    expect(expired, 0);
  });

  test('parallel 401s share one refresh call', () async {
    adapter.handler = (request) async {
      if (request.path == 'auth/refresh') {
        await Future<void>.delayed(const Duration(milliseconds: 20));

        return FakeReply.ok({'tokens': tokensJson('access-2', 'refresh-2')});
      }

      return request.headers['Authorization'] == 'Bearer access-2' ? FakeReply.ok({}) : FakeReply.error(401, 'Unauthenticated.');
    };
    final api = client();

    await Future.wait([api.get('me'), api.get('favorites'), api.get('notifications')]);

    expect(adapter.to('auth/refresh'), hasLength(1));
  });

  test('a rejected refresh token clears the session and reports expiry', () async {
    adapter.handler = (request) async => FakeReply.error(401, 'Unauthenticated.');

    await expectLater(
      client().get('me'),
      throwsA(isA<ApiException>().having((e) => e.kind, 'kind', ApiErrorKind.unauthorized)),
    );
    expect(await tokens.read(), isNull);
    expect(expired, 1);
  });

  test('a refresh that fails offline keeps the pair', () async {
    adapter.handler = (request) async {
      // The connection drops right after the 401: the refresh call cannot go out.
      adapter.offline = true;

      return FakeReply.error(401, 'Unauthenticated.');
    };

    await expectLater(client().get('me'), throwsA(isA<ApiException>()));
    expect((await tokens.read())!.refreshToken, 'refresh-1');
    expect(expired, 0);
  });

  test('builds Store API URLs', () {
    expect(client().storeUrl('cart'), 'https://api.test/store-api/v1/cart');
  });
}
