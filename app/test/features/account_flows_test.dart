import 'package:app/app/router.dart';
import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/url_opener.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/fixtures.dart';
import '../support/harness.dart';

const uuid = '11111111-1111-4111-8111-111111111111';

Future<FakeReply> accountHandler(SentRequest request) async {
  switch (request.path) {
    case 'store/orders':
      return FakeReply.ok({'orders': [orderJson()], 'meta': {'current_page': 1, 'last_page': 1, 'total': 1}});
    case 'store/orders/$uuid':
      return FakeReply.ok(orderJson());
    case 'store/orders/cancellation-reasons':
      return FakeReply.ok({
        'reasons': [
          {'value': 'changed_mind', 'label': 'Changed my mind', 'requires_note': false},
          {'value': 'other', 'label': 'Other', 'requires_note': true},
        ],
      });
    case 'store/orders/$uuid/cancel':
      return FakeReply.ok(orderJson(status: 'cancelled'), message: 'Order cancelled.');
    case 'store/orders/$uuid/reorder':
      return FakeReply.ok({'cart': cartJson(), 'added': [1], 'skipped': []}, message: 'Added');
    case 'store/orders/$uuid/invoice':
      return FakeReply.ok({'url': 'https://elora.test/invoice/signed', 'expires_at': '2026-11-27T10:30:00Z'});
    case 'store/returns':
      return FakeReply.ok({'returns': [returnJson()], 'meta': {'current_page': 1, 'last_page': 1}});
    case 'store/returns/3':
      return FakeReply.ok(returnJson());
    case 'store/returns/3/messages':
      return FakeReply.ok(returnJson(), message: 'Sent');
    case 'store/cart':
      return FakeReply.ok(cartJson());
    case 'account/addresses':
      if (request.method == 'POST') {
        return FakeReply.validation({'phone': ['The phone number is not valid for Saudi Arabia.']});
      }

      return FakeReply.ok({
        'addresses': [],
        'countries': [
          {'id': 1, 'iso2': 'SA', 'name': 'Saudi Arabia', 'flag': '', 'phone_code': '966'},
        ],
      });
    case 'notifications':
      return FakeReply.ok({
        'items': [
          {'id': 'n1', 'event': 'order.shipped', 'title': 'Your order shipped', 'body': 'N-1001 is on its way', 'order_uuid': uuid, 'read': false, 'created_at': '2026-11-22T10:00:00Z'},
        ],
        'unread_count': 1,
        'meta': {'current_page': 1, 'last_page': 1},
      });
    case 'notifications/n1/read':
      return FakeReply.ok(null);
    case 'notifications/unread-count':
      return FakeReply.ok({'unread_count': 1});
    case 'account/profile':
      return FakeReply.ok({'customer': {...customerJson(), 'name': (request.body as Map)['name']}}, message: 'Profile saved.');
    case 'account':
      return FakeReply.ok(null, message: 'Your account was deleted.');
  }

  return defaultHandler(request);
}

Harness signedIn({List<String>? opened}) => Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: accountHandler,
      overrides: [
        urlOpenerProvider.overrideWithValue((url) async {
          opened?.add(url);

          return true;
        }),
      ],
    );

Future<void> go(Harness harness, WidgetTester tester, String location) async {
  harness.container.read(routerProvider).push(location);
  await Harness.settle(tester);
}

void main() {
  testWidgets('account menu lists the customer pages', (tester) async {
    final harness = signedIn();
    await harness.pump(tester);
    await Harness.openTab(tester, 'Account');

    for (final key in ['account-orders', 'account-returns', 'account-addresses', 'account-support', 'account-inbox', 'account-profile', 'account-password', 'account-pages', 'account-delete']) {
      expect(find.byKey(ValueKey(key)), findsOneWidget, reason: key);
    }
  });

  testWidgets('orders list → detail → cancel with a reason', (tester) async {
    final harness = signedIn();
    await harness.pump(tester);
    await go(harness, tester, Routes.orders);

    expect(find.text('Order N-1001'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('order-$uuid')));
    await Harness.settle(tester);

    expect(find.text('Wireless headphones'), findsOneWidget);
    await tester.scrollUntilVisible(find.byKey(const ValueKey('order-cancel')), 200, scrollable: find.byType(Scrollable).last);
    await Harness.settle(tester);
    await tester.tap(find.byKey(const ValueKey('order-cancel')));
    await Harness.settle(tester);

    await tester.tap(find.byKey(const ValueKey('cancel-reason-changed_mind')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('cancel-continue')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('cancel-confirm')));
    await Harness.settle(tester);

    expect(harness.adapter.to('store/orders/$uuid/cancel').single.body, {'reason_code': 'changed_mind'});
    expect(find.text('Order cancelled.'), findsOneWidget);
  });

  testWidgets('order invoice opens the signed link; reorder fills the cart', (tester) async {
    final opened = <String>[];
    final harness = signedIn(opened: opened);
    await harness.pump(tester);
    await go(harness, tester, Routes.order(uuid));

    // The full shipping address card pushes the actions below the fold.
    await tester.dragUntilVisible(find.byKey(const ValueKey('order-invoice')), find.byType(ListView).last, const Offset(0, -200));
    await tester.ensureVisible(find.byKey(const ValueKey('order-invoice')));
    await Harness.settle(tester);
    await tester.tap(find.byKey(const ValueKey('order-invoice')));
    await Harness.settle(tester);
    expect(opened, ['https://elora.test/invoice/signed']);

    await tester.tap(find.byKey(const ValueKey('order-reorder')));
    await Harness.settle(tester);
    expect(harness.adapter.to('store/orders/$uuid/reorder'), hasLength(1));
    expect(find.text('The items were added to your cart.'), findsOneWidget);
  });

  testWidgets('order tracking shows carrier, AWB and timeline', (tester) async {
    final harness = signedIn();
    await harness.pump(tester);
    await go(harness, tester, Routes.orderTracking(uuid));

    expect(find.text('Aramex · AWB AWB123'), findsOneWidget);
    expect(find.text('Placed'), findsOneWidget);
  });

  testWidgets('returns list → detail → reply', (tester) async {
    final harness = signedIn();
    await harness.pump(tester);
    await go(harness, tester, Routes.returns);

    expect(find.text('R-1'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('return-3')));
    await Harness.settle(tester);

    expect(find.text('Damaged'), findsOneWidget);
    await tester.ensureVisible(find.byKey(const ValueKey('return-send')));
    await Harness.settle(tester);
    await tester.enterText(find.byKey(const ValueKey('return-reply')), 'The band snapped on day one.');
    await tester.tap(find.byKey(const ValueKey('return-send')));
    await Harness.settle(tester);

    expect(harness.adapter.to('store/returns/3/messages'), hasLength(1));
  });

  testWidgets('address form validates and shows the intl phone error from the API', (tester) async {
    final harness = signedIn();
    await harness.pump(tester);
    await go(harness, tester, Routes.addresses);

    expect(find.text('No saved addresses'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('address-add')));
    await Harness.settle(tester);

    await tester.tap(find.byKey(const ValueKey('address-save')));
    await tester.pump();
    expect(find.text('This field is required.'), findsWidgets);
    expect(find.textContaining('+966'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('address-name')), 'Fatma Elsonny');
    await tester.enterText(find.byKey(const ValueKey('address-phone')), '123');
    await tester.enterText(find.byKey(const ValueKey('address-city')), 'Riyadh');
    await tester.enterText(find.byKey(const ValueKey('address-line')), 'King Fahd Rd 1');
    await tester.tap(find.byKey(const ValueKey('address-save')));
    await Harness.settle(tester);

    final sent = harness.adapter.requests.where((r) => r.path == 'account/addresses' && r.method == 'POST').single;
    expect((sent.body as Map)['phone_country'], 'SA');
    expect((sent.body as Map)['country_id'], 1);
    expect(find.text('The phone number is not valid for Saudi Arabia.'), findsOneWidget);
  });

  testWidgets('inbox lists notifications and opening one marks it read', (tester) async {
    final harness = signedIn();
    await harness.pump(tester);
    await go(harness, tester, Routes.notifications);

    expect(find.text('Your order shipped'), findsOneWidget);
    await tester.tap(find.text('Your order shipped'));
    await Harness.settle(tester);

    expect(harness.adapter.to('notifications/n1/read'), hasLength(1));
  });

  testWidgets('edit profile saves and updates the session', (tester) async {
    final harness = signedIn();
    await harness.pump(tester);
    await Harness.openTab(tester, 'Account');
    await tester.tap(find.byKey(const ValueKey('account-profile')));
    await Harness.settle(tester);

    await tester.enterText(find.byKey(const ValueKey('profile-name')), 'Fatma S');
    await tester.tap(find.byKey(const ValueKey('profile-save')));
    await Harness.settle(tester);

    expect((harness.adapter.to('account/profile').single.body as Map)['name'], 'Fatma S');
    expect(find.text('Fatma S'), findsOneWidget);
  });

  testWidgets('delete account needs the password and signs out', (tester) async {
    final harness = signedIn();
    await harness.pump(tester);
    await go(harness, tester, Routes.deleteAccount);

    await tester.tap(find.byKey(const ValueKey('delete-open')));
    await Harness.settle(tester);
    await tester.tap(find.byKey(const ValueKey('delete-confirm')));
    await tester.pump();
    expect(harness.adapter.to('account'), isEmpty);

    await tester.enterText(find.byKey(const ValueKey('delete-password')), 'secret-pass');
    await tester.tap(find.byKey(const ValueKey('delete-confirm')));
    await Harness.settle(tester);

    expect(harness.adapter.to('account').single.body, {'password': 'secret-pass'});
    expect(await harness.storage.read(), isNull);
    expect(find.text('Sign in for the best experience'), findsOneWidget);
  });
}
