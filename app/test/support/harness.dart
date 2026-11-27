import 'package:app/app/app.dart';
import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/config/tenant_config.dart';
import 'package:app/core/i18n/app_texts.dart';
import 'package:app/core/network/api_client.dart';
import 'package:app/core/providers.dart';
import 'package:app/features/shell/app_tab_bar.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'fake_api.dart';

const testConfig = TenantConfig(
  key: 'test',
  tenantId: '7',
  appName: 'Test Store',
  apiBaseUrl: 'https://api.test',
  themeFamily: 'elora',
  themeVariant: 1,
  primaryColor: '#FF4D00',
);

Map<String, dynamic> bootstrapJson({
  String update = 'none',
  bool maintenance = false,
  String? maintenanceMessage,
}) =>
    {
      'store': {'id': 7, 'name': 'Elora Test', 'url': 'https://elora.test', 'logo_url': null, 'support_email': 'help@elora.test'},
      'branding': {
        'theme': {'slug': 'elora-1', 'family': 'elora', 'variant': 1},
        'colors': {'primary': '#FF4D00'},
        'fonts': {'ltr': 'Outfit', 'rtl': 'Noto Sans Arabic'},
        'splash': {'background': '#FF4D00', 'logo_url': null},
      },
      'locale': {
        'current': 'en',
        'dir': 'ltr',
        'default': 'en',
        'languages': [
          {'code': 'en', 'name': 'English', 'native': 'English', 'dir': 'ltr', 'is_default': true},
          {'code': 'ar', 'name': 'Arabic', 'native': 'العربية', 'dir': 'rtl', 'is_default': false},
        ],
      },
      'currency': {
        'base': 'USD',
        'charge': 'USD',
        'default': 'USD',
        'available': [
          {'code': 'USD', 'name': 'US dollar', 'symbol': r'$', 'decimals': 2, 'rate': '1', 'is_base': true},
          {'code': 'SAR', 'name': 'Saudi riyal', 'symbol': 'SAR', 'decimals': 2, 'rate': '3.75', 'is_base': false},
        ],
      },
      'countries': {
        'default_id': 1,
        'available': [
          {'id': 1, 'iso2': 'SA', 'name': 'Saudi Arabia', 'flag': '', 'phone_code': '966', 'currency_code': 'SAR'},
          {'id': 2, 'iso2': 'EG', 'name': 'Egypt', 'flag': '', 'phone_code': '20', 'currency_code': 'EGP'},
        ],
      },
      'features': {'wishlist': true, 'push_notifications': false},
      'push': {'enabled': false, 'provider': 'fcm'},
      'app': {
        'platform': 'android',
        'version': '1.0.0',
        'update': {'state': update, 'min_version': null, 'latest_version': '2.0.0', 'store_url': 'https://play.example/app'},
        'maintenance': {'enabled': maintenance, 'message': maintenanceMessage},
      },
      'texts': {'version': 'v1', 'locale': 'en'},
    };

Map<String, dynamic> customerJson({bool verified = true}) => {
      'id': 5,
      'name': 'Fatma Elsonny',
      'email': 'fatma@example.com',
      'email_verified': verified,
      'phone': null,
      'phone_country': null,
      'locale': 'en',
    };

Map<String, dynamic> tokensJson() => {
      'token_type': 'Bearer',
      'access_token': 'access',
      'refresh_token': 'refresh',
      'expires_at': DateTime.now().add(const Duration(days: 1)).toIso8601String(),
      'refresh_expires_at': DateTime.now().add(const Duration(days: 60)).toIso8601String(),
    };

Map<String, dynamic> money(int cents, {String? display}) => {
      'amount': cents,
      'currency': 'USD',
      'formatted': 'USD ${(cents / 100).toStringAsFixed(2)}',
      'display': display == null ? null : {'currency': 'SAR', 'amount': cents, 'formatted': display},
    };

Map<String, dynamic> cardJson(int id, {String? name, int price = 12900, bool favorited = false}) => {
      'id': id,
      'name': name ?? 'Product $id',
      'slug': 'product-$id',
      'image': null,
      'price': money(price),
      'compare_price': money(price + 3000),
      'discount_percent': 20,
      'flash': null,
      'rating': 4.6,
      'review_count': 128,
      'in_stock': true,
      'has_variants': false,
      'favorited': favorited,
    };

Map<String, dynamic> emptyCartJson() => {
      'token': 'cart-token-1',
      'items_count': 0,
      'lines': [],
      'coupon': null,
      'shipping': {'available': true, 'free': false, 'amount': money(0)},
      'free_shipping': null,
      'totals': {for (final k in ['subtotal', 'flash_discount', 'items_total', 'coupon_discount', 'shipping', 'grand_total']) k: money(0)},
      'can_checkout': false,
    };

Map<String, dynamic> homeJson({String family = 'elora', int variant = 1}) => {
      'theme': {'slug': '$family-$variant', 'family': family, 'variant': variant},
      'sections': {
        'hero': [],
        'promo': [],
        'deals': [],
        'clearance': [],
        'tabs': [],
        'flash_sale': null,
        'best_selling': {'items': [cardJson(1, name: 'Wireless headphones'), cardJson(2, name: 'Smart watch Pro')]},
        'top_rated': {'items': []},
        'new_in': {'items': [cardJson(3, name: 'Eau de parfum')]},
        'categories': [
          {'id': 10, 'name': 'Fashion', 'slug': 'fashion', 'image': null, 'children': []},
        ],
        'trending': [cardJson(4, name: 'Leather bag')],
        'offers': [],
        'recommended': [],
        'badges': [],
      },
    };

/// Default API: bootstrap ready, texts empty, guest everywhere, small catalog, empty cart.
Future<FakeReply> defaultHandler(SentRequest request) async => switch (request.path) {
      'bootstrap' => FakeReply.ok(bootstrapJson()),
      'store/home' => FakeReply.ok(homeJson()),
      'store/categories' => FakeReply.ok({'items': [], 'menu_ids': []}),
      'store/cart' => FakeReply.ok(emptyCartJson()),
      'notifications/unread-count' => FakeReply.ok({'unread_count': 0}),
      'texts' => FakeReply.ok({'locale': 'en', 'version': 'v1', 'texts': <String, String>{}}),
      'me' => FakeReply.ok({'customer': customerJson()}),
      'favorites' => FakeReply.ok({'items': [], 'ids': [], 'meta': {'current_page': 1, 'last_page': 1, 'per_page': 20, 'total': 0}}),
      _ => FakeReply.error(404, 'Not found'),
    };

/// Boots the whole app against a fake API.
class Harness {
  Harness({Future<FakeReply> Function(SentRequest)? handler, this.tokens, this.prefs = const {}, this.locale = 'en', this.overrides = const []})
      : adapter = FakeAdapter(handler ?? defaultHandler);

  final FakeAdapter adapter;
  final TokenPair? tokens;
  final Map<String, Object> prefs;
  final String locale;

  /// Extra provider overrides (payment launcher, URL opener, image picker …).
  final List<Override> overrides;
  late final MemoryTokenStorage storage = MemoryTokenStorage(tokens);
  late ProviderContainer container;

  /// Onboarding + first run already done (the auth / account tests start in the app).
  static Map<String, Object> returning({String locale = 'en'}) => {
        'nogrgr.test.onboarding_seen': true,
        'nogrgr.test.setup_done': true,
        'nogrgr.test.locale': locale,
        'nogrgr.test.device_id': 'device-test-0001',
      };

  Future<void> pump(WidgetTester tester) async {
    // Phone-sized surface like the 375-wide Figma frames.
    tester.view.physicalSize = const Size(1125, 2436);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    SharedPreferences.setMockInitialValues(prefs);
    // Real async work outside the fake clock (asset + prefs futures from an earlier
    // test's zone would never complete inside this one).
    final (shared, texts) = (await tester.runAsync(
      () async => (await SharedPreferences.getInstance(), await AppTexts.loadBundled(locale)),
    ))!;
    final expiry = ValueNotifier<int>(0);

    await tester.pumpWidget(
      ProviderScope(
        retry: (_, _) => null,
        overrides: [
          tenantConfigProvider.overrideWithValue(testConfig),
          sharedPreferencesProvider.overrideWithValue(shared),
          tokenStorageProvider.overrideWithValue(storage),
          initialTextsProvider.overrideWithValue(texts),
          appPlatformProvider.overrideWithValue('android'),
          sessionExpiryProvider.overrideWithValue(expiry),
          ...overrides,
          apiClientProvider.overrideWith(
            (ref) => ApiClient(
              mobileBaseUrl: testConfig.mobileApiUrl,
              storeBaseUrl: testConfig.storeApiUrl,
              tokens: storage,
              dio: fakeDio(adapter),
              onSessionExpired: () => expiry.value++,
              context: () => RequestContext(tenantId: testConfig.tenantId, locale: ref.read(localeCodeProvider)),
            ),
          ),
        ],
        child: Builder(
          builder: (context) {
            container = ProviderScope.containerOf(context);

            return const NogrgrApp();
          },
        ),
      ),
    );
    await settle(tester);
  }

  /// Switches to a bottom tab by its label.
  static Future<void> openTab(WidgetTester tester, String label) async {
    await tester.tap(find.descendant(of: find.byType(AppTabBar), matching: find.text(label)));
    await settle(tester);
  }

  /// pumpAndSettle with a cap (the splash loader spins until bootstrap answers).
  static Future<void> settle(WidgetTester tester) async {
    for (var i = 0; i < 30; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
  }
}
