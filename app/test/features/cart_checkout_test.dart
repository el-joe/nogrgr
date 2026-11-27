import 'package:app/app/router.dart';
import 'package:app/core/auth/token_storage.dart';
import 'package:app/features/auth/presentation/login_screen.dart';
import 'package:app/features/checkout/data/checkout_repository.dart';
import 'package:app/features/checkout/presentation/checkout_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/fixtures.dart';
import '../support/harness.dart';

class FakeLauncher extends PaymentLauncher {
  FakeLauncher({this.sheet = SheetResult.completed});

  final SheetResult sheet;
  final List<String> calls = [];

  @override
  Future<SheetResult> stripe({required String publishableKey, required String clientSecret, required String merchant, required bool dark}) async {
    calls.add('stripe:$publishableKey:$clientSecret');

    return sheet;
  }

  @override
  Future<bool> browser(String url) async {
    calls.add('browser:$url');

    return false;
  }
}

Map<String, dynamic> optionsJson({List<String> methods = const ['cod', 'stripe', 'paypal'], String display = 'SAR 900.00'}) => {
      'addresses': [
        {'id': 4, 'label': 'Home', 'full_name': 'Fatma Elsonny', 'phone': '+966500000000', 'is_default': true, 'country_id': 1, 'one_line': 'King Fahd Rd 1, Riyadh'},
      ],
      'shipping_address_id': 4,
      'billing_address_id': 4,
      'billing_same_as_shipping': true,
      'cart': cartJson(display: display),
      'payment_methods': [
        for (final code in methods) {'source': 'store', 'id': 1, 'code': code, 'name': code.toUpperCase(), 'mode': 'test', 'logo_url': null, 'payment_methods': [], 'inline': code == 'stripe', 'publishable_key': code == 'stripe' ? 'pk_test_1' : null},
      ],
      'selected_payment_method': null,
      'can_pay': true,
      'block': null,
      'block_message': null,
    };

const orderUuid = '11111111-1111-4111-8111-111111111111';

Future<FakeReply> Function(SentRequest) commerceHandler({String confirmStatus = 'paid', Map<String, dynamic>? cart}) => (request) async {
      switch (request.path) {
        case 'store/cart':
          return FakeReply.ok(cart ?? cartJson());
        case 'store/cart/items/55':
          return FakeReply.ok(cartJson(quantity: (request.body as Map)['quantity'] as int));
        case 'store/cart/coupon':
          return FakeReply.ok(cartJson());
        case 'store/checkout/options':
          return FakeReply.ok(optionsJson());
        case 'store/checkout/place':
          return FakeReply(201, {
            'success': true,
            'message': 'Placed',
            'data': {
              'checkout_group': 'g1',
              'gateway': (request.body as Map)['gateway'],
              'grand_total': 26300,
              'orders': [
                {'uuid': orderUuid, 'number': 'N-1001', 'kind': 'own', 'grand_total': 26300},
              ],
              'payment_order': orderUuid,
              'replayed': false,
            },
          });
        case 'store/payments/$orderUuid/start':
          final gateway = (request.body as Map)['gateway'];

          return FakeReply(201, {
            'success': true,
            'message': '',
            'data': {
              'payment': {'reference': 'PAY-1', 'gateway': gateway, 'status': 'initiated'},
              'redirect_url': gateway == 'paypal' ? 'https://paypal.test/checkout' : null,
              'client_secret': gateway == 'stripe' ? 'pi_secret_1' : null,
              'publishable_key': gateway == 'stripe' ? 'pk_test_1' : null,
            },
          });
        case 'store/payments/$orderUuid/confirm':
          return FakeReply.ok({
            'payment': {'reference': 'PAY-1', 'status': confirmStatus, 'failure_reason': null},
            'order': orderJson(),
          }, message: 'Payment $confirmStatus');
        case 'store/orders':
          return FakeReply.ok({'orders': [], 'meta': {'current_page': 1, 'last_page': 1}});
        case 'store/orders/$orderUuid':
          return FakeReply.ok(orderJson());
      }

      return defaultHandler(request);
    };

void main() {
  testWidgets('cart: lines, free-shipping progress, display totals + USD note, quantity change keeps the cart token', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: commerceHandler());
    await harness.pump(tester);
    await Harness.openTab(tester, 'Cart');

    expect(find.text('Wireless headphones'), findsOneWidget);
    expect(find.textContaining('to checkout'), findsOneWidget);
    expect(find.text('SAR 900.00'), findsOneWidget);
    expect(find.textContaining('You will be charged USD 263.00'), findsOneWidget);
    expect(find.textContaining('more to get free shipping'), findsOneWidget);

    await tester.tap(find.descendant(of: find.byKey(const ValueKey('cart-qty-55')), matching: find.byTooltip('Increase quantity')));
    await Harness.settle(tester);

    final patch = harness.adapter.to('store/cart/items/55').single;
    expect(patch.method, 'PATCH');
    expect(patch.body, {'quantity': 3});
    expect(patch.headers['X-Cart-Token'], 'cart-token-1');
  });

  testWidgets('cart: apply a coupon code', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: commerceHandler(cart: cartJson(withCoupon: false)));
    await harness.pump(tester);
    await Harness.openTab(tester, 'Cart');

    await tester.enterText(find.byKey(const ValueKey('coupon-field')), 'SAVE10');
    await tester.tap(find.byKey(const ValueKey('coupon-apply')));
    await Harness.settle(tester);

    expect(harness.adapter.to('store/cart/coupon').single.body, {'code': 'SAVE10'});
    expect(find.byKey(const ValueKey('coupon-remove')), findsOneWidget);
  });

  testWidgets('guest checkout asks to sign in first', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: commerceHandler());
    await harness.pump(tester);
    await Harness.openTab(tester, 'Cart');

    await tester.tap(find.byKey(const ValueKey('cart-checkout')));
    await Harness.settle(tester);

    expect(find.byType(LoginScreen), findsOneWidget);
  });

  testWidgets('checkout with cash on delivery places the order idempotently and shows success', (tester) async {
    final harness = Harness(prefs: Harness.returning(), tokens: TokenPair.fromJson(tokensJson()), handler: commerceHandler());
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.checkout);
    await Harness.settle(tester);

    expect(find.byType(CheckoutScreen), findsOneWidget);
    expect(find.text('King Fahd Rd 1, Riyadh'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('method-cod')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('checkout-pay')));
    await Harness.settle(tester);

    final place = harness.adapter.to('store/checkout/place').single;
    expect((place.body as Map)['gateway'], 'cod');
    expect((place.body as Map)['shipping_address_id'], 4);
    expect(place.headers['Idempotency-Key'], (place.body as Map)['idempotency_key']);
    expect(harness.adapter.to('store/payments/$orderUuid/start'), isEmpty);
    expect(find.byKey(const ValueKey('payment-success')), findsOneWidget);
    expect(find.textContaining('N-1001'), findsOneWidget);
  });

  testWidgets('stripe: PaymentSheet with the start client secret, then confirm', (tester) async {
    final launcher = FakeLauncher();
    final harness = Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: commerceHandler(),
      overrides: [paymentLauncherProvider.overrideWithValue(launcher)],
    );
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.checkout);
    await Harness.settle(tester);

    await tester.tap(find.byKey(const ValueKey('method-stripe')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('checkout-pay')));
    await Harness.settle(tester);

    expect(launcher.calls, ['stripe:pk_test_1:pi_secret_1']);
    expect(harness.adapter.to('store/payments/$orderUuid/confirm').single.body, {'reference': 'PAY-1'});
    expect(find.byKey(const ValueKey('payment-success')), findsOneWidget);
  });

  testWidgets('redirect gateway: in-app browser, pending confirmation offers a status check', (tester) async {
    final launcher = FakeLauncher();
    final harness = Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: commerceHandler(confirmStatus: 'pending'),
      overrides: [paymentLauncherProvider.overrideWithValue(launcher)],
    );
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.checkout);
    await Harness.settle(tester);

    await tester.tap(find.byKey(const ValueKey('method-paypal')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('checkout-pay')));
    await Harness.settle(tester);

    expect(launcher.calls, ['browser:https://paypal.test/checkout']);
    expect(find.byKey(const ValueKey('payment-pending')), findsOneWidget);

    await tester.tap(find.text('Check payment status'));
    await Harness.settle(tester);
    expect(harness.adapter.to('store/payments/$orderUuid/confirm'), hasLength(2));
  });

  testWidgets('cancelled card sheet shows the failed result with retry', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: commerceHandler(),
      overrides: [paymentLauncherProvider.overrideWithValue(FakeLauncher(sheet: SheetResult.cancelled))],
    );
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.checkout);
    await Harness.settle(tester);

    await tester.tap(find.byKey(const ValueKey('method-stripe')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('checkout-pay')));
    await Harness.settle(tester);

    expect(find.byKey(const ValueKey('payment-failed')), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);
  });
}
