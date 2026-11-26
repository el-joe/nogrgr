import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/i18n/app_texts.dart';
import 'package:app/core/i18n/texts_repository.dart';
import 'package:app/core/network/api_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../support/fake_api.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeAdapter adapter;
  late SharedPreferences prefs;

  TextsRepository repository() => TextsRepository(
        ApiClient(
          mobileBaseUrl: 'https://api.test/api/mobile/v1',
          storeBaseUrl: 'https://api.test/store-api/v1',
          tokens: MemoryTokenStorage(),
          dio: fakeDio(adapter),
          context: () => const RequestContext(tenantId: '7', locale: 'en'),
        ),
        prefs,
        'demo',
      );

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    prefs = await SharedPreferences.getInstance();
    adapter = FakeAdapter((request) async {
      if (request.headers['If-None-Match'] == '"v1-all"') {
        return const FakeReply(304);
      }

      return FakeReply.ok(
        {'locale': 'en', 'version': 'v1', 'texts': {'mobile.tabs.home': 'Shop'}},
        headers: {'ETag': '"v1-all"'},
      );
    });
  });

  group('TextsRepository (ETag cache)', () {
    test('first load downloads and caches the texts with the ETag', () async {
      final texts = await repository().load('en');

      expect(texts['mobile.tabs.home'], 'Shop');
      expect(repository().cached('en')!.etag, '"v1-all"');
      expect(adapter.requests.single.headers.containsKey('If-None-Match'), isFalse);
    });

    test('a later load sends If-None-Match and keeps the cache on 304', () async {
      await repository().load('en');
      final texts = await repository().load('en');

      expect(adapter.requests.last.headers['If-None-Match'], '"v1-all"');
      expect(texts['mobile.tabs.home'], 'Shop');
    });

    test('a bootstrap version equal to the cached one skips the call', () async {
      await repository().load('en');
      adapter.requests.clear();

      await repository().load('en', version: 'v1');

      expect(adapter.requests, isEmpty);
    });

    test('offline returns the cached texts', () async {
      await repository().load('en');
      adapter.offline = true;

      expect((await repository().load('en', version: 'v2'))['mobile.tabs.home'], 'Shop');
    });

    test('offline with no cache returns nothing (bundled strings take over)', () async {
      adapter.offline = true;

      expect(await repository().load('ar'), isEmpty);
    });
  });

  group('AppTexts', () {
    test('store text wins over bundled, bundled over English, then the key', () {
      const texts = AppTexts(
        locale: 'ar',
        remote: {'a': 'remote'},
        bundled: {'a': 'bundled', 'b': 'bundled-b'},
        fallback: {'c': 'english'},
      );

      expect(texts.t('a'), 'remote');
      expect(texts.t('b'), 'bundled-b');
      expect(texts.t('c'), 'english');
      expect(texts.t('missing.key'), 'missing.key');
      expect(texts.isRtl, isTrue);
    });

    test('replaces :placeholders', () {
      const texts = AppTexts(locale: 'en', bundled: {'x': 'Code sent to :email in :time'});

      expect(texts.t('x', {'email': 'a@b.c', 'time': '00:45'}), 'Code sent to a@b.c in 00:45');
    });

    test('bundled en and ar files have the same keys', () async {
      final en = await AppTexts.loadBundled('en');
      final ar = await AppTexts.loadBundled('ar');

      expect(ar.bundled.keys.toSet(), en.bundled.keys.toSet());
      expect(ar.t('app.auth.welcome_back'), isNot(en.t('app.auth.welcome_back')));
    });
  });
}
