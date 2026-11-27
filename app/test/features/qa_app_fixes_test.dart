import 'dart:async';

import 'package:app/app/router.dart';
import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/theme/app_tokens.dart';
import 'package:app/core/widgets/catalog_widgets.dart';
import 'package:app/core/widgets/select_field.dart';
import 'package:app/features/catalog/data/catalog_models.dart';
import 'package:app/features/catalog/presentation/gallery_video.dart';
import 'package:app/features/orders/data/orders_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:video_player_platform_interface/video_player_platform_interface.dart';

import '../support/fake_api.dart';
import '../support/fixtures.dart';
import '../support/harness.dart';

const _uuid = '11111111-1111-4111-8111-111111111111';

/// Native player stand-in: every player initialises at 1920×1080 / 10 s and records calls.
class FakeVideoPlatform extends VideoPlayerPlatform {
  final calls = <String>[];
  final _events = <int, StreamController<VideoEvent>>{};
  var _next = 0;

  @override
  Future<void> init() async {}

  @override
  Future<int?> createWithOptions(VideoCreationOptions options) async {
    final id = _next++;
    calls.add('create ${options.dataSource.uri}');
    _events[id] = StreamController<VideoEvent>();
    scheduleMicrotask(
      () => _events[id]!.add(VideoEvent(eventType: VideoEventType.initialized, size: const Size(1920, 1080), duration: const Duration(seconds: 10))),
    );

    return id;
  }

  @override
  Stream<VideoEvent> videoEventsFor(int playerId) => _events[playerId]!.stream;

  @override
  Future<void> play(int playerId) async => calls.add('play');

  @override
  Future<void> pause(int playerId) async => calls.add('pause');

  @override
  Future<void> dispose(int playerId) async => calls.add('dispose');

  @override
  Future<void> setLooping(int playerId, bool looping) async {}

  @override
  Future<void> setVolume(int playerId, double volume) async {}

  @override
  Future<void> setPlaybackSpeed(int playerId, double speed) async {}

  @override
  Future<void> seekTo(int playerId, Duration position) async {}

  @override
  Future<Duration> getPosition(int playerId) async => const Duration(seconds: 1);

  @override
  Future<void> setMixWithOthers(bool mixWithOthers) async {}

  @override
  Future<void> setPreventsDisplaySleepDuringVideoPlayback(int playerId, bool preventsDisplaySleep) async {}

  @override
  Widget buildViewWithOptions(VideoViewOptions options) => const ColoredBox(key: ValueKey('fake-native-video'), color: Colors.black);
}

Map<String, dynamic> _videoProduct({required int id, required List<Map<String, dynamic>> media, List<String> images = const []}) => {
      ...productJson(id: id),
      'images': images,
      'media': media,
    };

Future<FakeReply> _handler(SentRequest request, {Map<String, dynamic>? bootstrap}) async {
  switch (request.path) {
    case 'bootstrap':
      return FakeReply.ok(bootstrap ?? bootstrapJson());
    case 'store/products/3':
      return FakeReply.ok(productJson(id: 3));
    case 'store/products/5':
      // Only a video in the gallery (QA store 1, product 1).
      return FakeReply.ok(_videoProduct(id: 5, media: [
        {'type': 'video', 'url': 'https://cdn.test/v.mp4', 'poster': 'https://cdn.test/v.jpg'},
      ]));
    case 'store/products/6':
      return FakeReply.ok(_videoProduct(id: 6, images: ['https://cdn.test/a.jpg'], media: [
        {'type': 'video', 'url': 'https://cdn.test/v.mp4', 'poster': null},
        {'type': 'image', 'url': 'https://cdn.test/a.jpg', 'poster': null},
      ]));
    case 'store/products/7':
      return FakeReply.ok({
        ...productJson(id: 7),
        'flash': {'percentage': 20, 'ends_at': DateTime.now().add(const Duration(hours: 126, minutes: 2, seconds: 31)).toUtc().toIso8601String()},
      });
    case 'store/cart/items':
      return FakeReply(201, {'success': true, 'message': 'Added', 'data': cartJson(quantity: 1)});
    case 'store/orders/$_uuid':
      return FakeReply.ok(orderJson());
  }

  return defaultHandler(request);
}

Map<String, dynamic> _bootstrapWith({required String family, String? primary}) {
  final json = bootstrapJson();
  json['branding'] = {
    ...json['branding'] as Map<String, dynamic>,
    'theme': {'slug': '$family-1', 'family': family, 'variant': 1},
    'colors': {'primary': primary},
  };

  return json;
}

Future<void> _open(Harness harness, WidgetTester tester, String location) async {
  harness.container.read(routerProvider).push(location);
  await Harness.settle(tester);
}

AppColors _colors(WidgetTester tester) => Theme.of(tester.element(find.byType(Scaffold).first)).extension<AppColors>()!;

void main() {
  group('RQA-APP-004 brand colour precedence', () {
    test('bootstrap colour → bootstrap family default → build colour before bootstrap', () {
      final before = AppBrand.resolve(buildFamily: 'ecommet', buildPrimary: '#DE1709');
      expect(before.family, ThemeFamily.ecommet);
      expect(before.effectivePrimary, const Color(0xFFDE1709));

      final switched = AppBrand.resolve(buildFamily: 'ecommet', buildPrimary: '#DE1709', bootstrapped: true, bootstrapFamily: 'souqify');
      expect(switched.family, ThemeFamily.souqify);
      expect(switched.primary, isNull);
      expect(switched.effectivePrimary, const Color(0xFF0159ED));

      final explicit = AppBrand.resolve(buildFamily: 'ecommet', buildPrimary: '#DE1709', bootstrapped: true, bootstrapFamily: 'souqify', bootstrapPrimary: '#123456');
      expect(explicit.effectivePrimary, const Color(0xFF123456));
    });

    testWidgets('a store switched to Souqify without its own colour paints Souqify blue + ink', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: (r) => _handler(r, bootstrap: _bootstrapWith(family: 'souqify')));
      await harness.pump(tester);

      // The build colour (#FF4D00, Elora) is not kept.
      expect(_colors(tester).primary, const Color(0xFF0159ED));
      expect(_colors(tester).ink, AppColors.familyInk(ThemeFamily.souqify));
    });

    testWidgets('an explicit store colour still overrides the family default', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: (r) => _handler(r, bootstrap: _bootstrapWith(family: 'ecommet', primary: '#0A7A3B')));
      await harness.pump(tester);

      expect(_colors(tester).primary, const Color(0xFF0A7A3B));
      expect(_colors(tester).ink, AppColors.familyInk(ThemeFamily.ecommet));
    });
  });

  group('RQA-APP-005 product gallery videos', () {
    late FakeVideoPlatform platform;

    setUp(() {
      platform = FakeVideoPlatform();
      VideoPlayerPlatform.instance = platform;
    });

    test('gallery follows media (videos included) and falls back to images', () {
      final withMedia = ProductDetail.fromJson(_videoProduct(id: 6, images: ['https://cdn.test/a.jpg'], media: [
        {'type': 'video', 'url': 'https://cdn.test/v.mp4', 'poster': 'https://cdn.test/v.jpg'},
        {'type': 'image', 'url': 'https://cdn.test/a.jpg', 'poster': 'ignored'},
        {'type': 'video', 'url': ''},
      ]));
      expect(withMedia.gallery, const [
        ProductMedia(type: MediaType.video, url: 'https://cdn.test/v.mp4', poster: 'https://cdn.test/v.jpg'),
        ProductMedia.image('https://cdn.test/a.jpg'),
      ]);
      // Cards / variant images keep reading `images`.
      expect(withMedia.images, ['https://cdn.test/a.jpg']);

      final legacy = ProductDetail.fromJson(productJson(id: 3));
      expect(legacy.gallery.map((m) => m.url), ['https://cdn.test/a.jpg', 'https://cdn.test/b.jpg']);
      expect(legacy.gallery.every((m) => !m.isVideo), isTrue);
    });

    testWidgets('a video-only gallery shows the poster with a play button, not the placeholder', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: _handler);
      await harness.pump(tester);
      await _open(harness, tester, Routes.product(5));

      expect(find.byType(GalleryVideo), findsOneWidget);
      expect(find.byKey(const ValueKey('gallery-video-play')), findsOneWidget);
      final poster = tester.widget<NetImage>(find.descendant(of: find.byType(GalleryVideo), matching: find.byType(NetImage)));
      expect(poster.url, 'https://cdn.test/v.jpg');
      expect(find.bySemanticsLabel('Play video'), findsOneWidget);

      // Tap plays inline with the native player, contained at the video's aspect ratio.
      await tester.tap(find.byKey(const ValueKey('gallery-video')));
      await Harness.settle(tester);
      expect(platform.calls.first, 'create https://cdn.test/v.mp4');
      expect(platform.calls.last, 'play');
      expect(find.byKey(const ValueKey('fake-native-video')), findsOneWidget);
      final ratio = tester.widget<AspectRatio>(find.ancestor(of: find.byKey(const ValueKey('gallery-video-player')), matching: find.byType(AspectRatio)).first);
      expect(ratio.aspectRatio, closeTo(16 / 9, 0.001));

      // Leaving the screen pauses / releases the player.
      harness.container.read(routerProvider).pop();
      await Harness.settle(tester);
      // The native release completes outside the fake clock.
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 20)));
      expect(find.byType(GalleryVideo), findsNothing);
      expect(platform.calls, containsAllInOrder(['play', 'pause', 'dispose']));
    });

    testWidgets('swiping away from a playing video pauses it; indicators include the video', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: _handler);
      await harness.pump(tester);
      await _open(harness, tester, Routes.product(6));

      expect(find.byKey(const ValueKey('gallery-dot-video-0')), findsOneWidget);
      expect(find.byKey(const ValueKey('gallery-dot-1')), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('gallery-video')));
      await Harness.settle(tester);
      expect(platform.calls.last, 'play');

      await tester.drag(find.byKey(const ValueKey('product-gallery')), const Offset(-400, 0));
      await Harness.settle(tester);
      expect(platform.calls, contains('pause'));
      expect(platform.calls.lastIndexOf('pause'), greaterThan(platform.calls.indexOf('play')));
    });

    testWidgets('RTL: the video gallery still renders and swipes', (tester) async {
      final harness = Harness(prefs: Harness.returning(locale: 'ar'), locale: 'ar', handler: _handler);
      await harness.pump(tester);
      await _open(harness, tester, Routes.product(6));

      expect(Directionality.of(tester.element(find.byType(GalleryVideo))), TextDirection.rtl);
      expect(find.bySemanticsLabel('تشغيل الفيديو'), findsOneWidget);
      // In RTL the next slide is to the left: drag right.
      await tester.drag(find.byKey(const ValueKey('product-gallery')), const Offset(400, 0));
      await Harness.settle(tester);
      final dot = tester.widget<AnimatedContainer>(find.byKey(const ValueKey('gallery-dot-1')));
      expect((dot.decoration! as BoxDecoration).color, _colors(tester).primary);
    });
  });

  group('RQA-APP-006 "added to cart" message', () {
    testWidgets('auto-dismisses after about 4 s even though it has an action', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: _handler);
      await harness.pump(tester);
      await _open(harness, tester, Routes.product(3));

      await tester.tap(find.byKey(const ValueKey('option-1-12')));
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('product-add-to-cart')));
      await tester.pump(const Duration(milliseconds: 500));

      final snack = tester.widget<SnackBar>(find.byKey(const ValueKey('cart-added-snack')));
      expect(snack.persist, isFalse);
      expect(snack.duration, const Duration(seconds: 4));

      Future<void> wait(int ms) async {
        for (var i = 0; i < ms ~/ 100; i++) {
          await tester.pump(const Duration(milliseconds: 100));
        }
      }

      await wait(3000);
      expect(find.byKey(const ValueKey('cart-added-snack')), findsOneWidget);
      await wait(3000);
      expect(find.byKey(const ValueKey('cart-added-snack')), findsNothing);
    });

    testWidgets('"View cart" opens the cart without the message covering it', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: _handler);
      await harness.pump(tester);
      await _open(harness, tester, Routes.product(3));

      await tester.tap(find.byKey(const ValueKey('option-1-12')));
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('product-add-to-cart')));
      await tester.pump(const Duration(milliseconds: 800));
      // The action's own handler (the snack's hide is ours, not SnackBarAction's).
      tester.widget<SnackBarAction>(find.byType(SnackBarAction)).onPressed();
      await tester.pump(const Duration(milliseconds: 600));

      expect(find.byKey(const ValueKey('cart-added-snack')), findsNothing);
      expect(harness.container.read(routerProvider).state.uri.path, Routes.cart);
    });

    testWidgets('switching tabs clears a message from the previous tab', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: _handler);
      await harness.pump(tester);
      final messenger = ScaffoldMessenger.of(tester.element(find.byType(Scaffold).first));
      messenger.showSnackBar(const SnackBar(key: ValueKey('tab-snack'), content: Text('Saved'), duration: Duration(minutes: 1)));
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.byKey(const ValueKey('tab-snack')), findsOneWidget);

      await Harness.openTab(tester, 'Account');
      expect(find.byKey(const ValueKey('tab-snack')), findsNothing);
    });
  });

  group('RQA-APP-003 flash countdown', () {
    testWidgets('a sale ending in 126 h shows a days segment, not a clamped 99 h', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: _handler);
      await harness.pump(tester);
      await _open(harness, tester, Routes.product(7));

      final countdown = find.byType(Countdown);
      expect(find.descendant(of: countdown, matching: find.text('5d')), findsOneWidget);
      expect(find.descendant(of: countdown, matching: find.text('06')), findsOneWidget);
      expect(find.descendant(of: countdown, matching: find.text('99')), findsNothing);
    });

    testWidgets('Arabic: translated day label at the start (right) of the timer', (tester) async {
      final harness = Harness(prefs: Harness.returning(locale: 'ar'), locale: 'ar', handler: _handler);
      await harness.pump(tester);
      await _open(harness, tester, Routes.product(7));

      final days = find.descendant(of: find.byType(Countdown), matching: find.text('5 يوم'));
      expect(days, findsOneWidget);
      final hours = find.descendant(of: find.byType(Countdown), matching: find.text('06'));
      expect(tester.getCenter(days).dx, greaterThan(tester.getCenter(hours).dx));
    });
  });

  group('RQA-APP-007 status bar follows the surface', () {
    testWidgets('light mode: dark icons on pushed screens', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: _handler, tokens: TokenPair.fromJson(tokensJson()));
      await harness.pump(tester);
      await _open(harness, tester, Routes.order(_uuid));

      expect(SystemChrome.latestStyle?.statusBarIconBrightness, Brightness.dark);
    });

    testWidgets('dark mode: light icons', (tester) async {
      final harness = Harness(prefs: {...Harness.returning(), 'nogrgr.test.theme_mode': 'dark'}, handler: _handler);
      await harness.pump(tester);
      await _open(harness, tester, Routes.product(3));

      expect(SystemChrome.latestStyle?.statusBarIconBrightness, Brightness.light);
    });

    testWidgets('Ecommet home brand header: light icons', (tester) async {
      final harness = Harness(
        prefs: Harness.returning(),
        handler: (request) async => request.path == 'store/home'
            ? FakeReply.ok(homeJson(family: 'ecommet'))
            : _handler(request, bootstrap: _bootstrapWith(family: 'ecommet')),
      );
      await harness.pump(tester);

      expect(SystemChrome.latestStyle?.statusBarIconBrightness, Brightness.light);
    });
  });

  group('RQA-APP-001 select field', () {
    Widget field(TextDirection direction) => MaterialApp(
          theme: ThemeData(extensions: [AppColors.light(family: ThemeFamily.elora)]),
          home: Directionality(
            textDirection: direction,
            child: Scaffold(
              body: SizedBox(
                width: 240,
                child: AppSelectField<int>(
                  label: 'Country',
                  value: 1,
                  options: const [SelectOption(value: 1, label: 'A very long country name that needs ellipsis')],
                  onChanged: (_) {},
                ),
              ),
            ),
          ),
        );

    testWidgets('chevron icon at the inline end, value ellipsized before it (LTR + RTL)', (tester) async {
      for (final direction in TextDirection.values) {
        await tester.pumpWidget(field(direction));
        final value = find.byKey(const ValueKey('select-field-value'));
        final chevron = find.byKey(const ValueKey('select-field-chevron'));
        expect(chevron, findsOneWidget);
        expect(find.textContaining('▾'), findsNothing);
        expect(tester.widget<Text>(value).overflow, TextOverflow.ellipsis);

        final box = tester.getRect(find.byType(InkWell));
        if (direction == TextDirection.ltr) {
          expect(tester.getRect(chevron).right, closeTo(box.right - 10, 1));
          expect(tester.getRect(value).right, lessThanOrEqualTo(tester.getRect(chevron).left));
        } else {
          expect(tester.getRect(chevron).left, closeTo(box.left + 10, 1));
          expect(tester.getRect(value).left, greaterThanOrEqualTo(tester.getRect(chevron).right));
        }
      }
    });
  });

  group('RQA-APP-008 order detail', () {
    test('gateway display name: API name → known brand → title case', () {
      Order order(Map<String, dynamic> extra) => Order.fromJson({...orderJson(), ...extra});
      expect(order({}).paymentGatewayLabel, 'Stripe');
      expect(order({'payment_gateway': 'paypal'}).paymentGatewayLabel, 'PayPal');
      expect(order({'payment_gateway': 'bank_transfer'}).paymentGatewayLabel, 'Bank Transfer');
      expect(order({'payment_gateway_name': 'Card (Stripe)'}).paymentGatewayLabel, 'Card (Stripe)');
      expect(order({'payment_gateway': null}).paymentGatewayLabel, isNull);
    });

    testWidgets('shows the gateway name and the full shipping address with the street', (tester) async {
      final harness = Harness(prefs: Harness.returning(), handler: _handler, tokens: TokenPair.fromJson(tokensJson()));
      await harness.pump(tester);
      await _open(harness, tester, Routes.order(_uuid));

      expect(find.text('Payment: Paid · Stripe'), findsOneWidget);
      expect(find.textContaining('stripe'), findsNothing);

      await tester.ensureVisible(find.byKey(const ValueKey('order-shipping-address')));
      for (final line in ['Fatma Elsonny', '+966500000000', 'King Fahd Rd 1', 'Riyadh, Riyadh Province', 'Saudi Arabia']) {
        expect(find.descendant(of: find.byKey(const ValueKey('order-shipping-address')), matching: find.text(line)), findsOneWidget);
      }
    });
  });
}
