import 'package:app/app/router.dart';
import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/providers.dart';
import 'package:app/core/push/push_service.dart';
import 'package:app/features/account/data/notification_preferences.dart';
import 'package:app/features/notifications/data/push_coordinator.dart';
import 'package:app/features/notifications/presentation/push_listener.dart';
import 'package:app/features/orders/presentation/order_detail_screen.dart';
import 'package:app/features/support/presentation/tickets_screens.dart';
import 'package:app/features/notifications/presentation/inbox_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/fixtures.dart';
import '../support/harness.dart';
import 'support_tickets_test.dart' show ticketJson;

const uuid = '11111111-1111-4111-8111-111111111111';

Map<String, Map<String, bool>> matrix({bool support = true}) => {
      for (final type in notificationTypes) type: {'email': true, 'push': type == 'support' ? support : type != 'promotions'},
    };

Future<FakeReply> pushHandler(SentRequest request, {bool pushEnabled = true, bool failSave = false}) async {
  switch (request.path) {
    case 'bootstrap':
      final json = bootstrapJson();

      return FakeReply.ok({...json, 'push': {'enabled': pushEnabled, 'provider': 'fcm'}});
    case 'store/orders/$uuid':
      return FakeReply.ok(orderJson());
    case 'store/support/tickets/41':
      return FakeReply.ok({'ticket': ticketJson(detail: true)});
    case 'store/support/tickets/options':
      return FakeReply.ok({'topics': [], 'orders': [], 'max_files': 5, 'max_file_kb': 10240, 'file_types': []});
    case 'notifications':
      return FakeReply.ok({'items': [], 'unread_count': 0, 'meta': {'current_page': 1, 'last_page': 1}});
    case 'notifications/n7/read':
      return FakeReply.ok(null);
    case 'notifications/unread-count':
      return FakeReply.ok({'unread_count': 3});
    case 'account/notification-preferences':
      if (request.method == 'PUT') {
        if (failSave) {
          return FakeReply.error(500, 'Server error');
        }

        final changed = ((request.body as Map)['preferences'] as Map).cast<String, Map>();

        return FakeReply.ok({
          'preferences': {
            ...matrix(),
            for (final entry in changed.entries) entry.key: {...matrix()[entry.key]!, ...entry.value.cast<String, bool>()},
          },
        }, message: 'Saved');
      }

      return FakeReply.ok({'preferences': matrix()});
    case 'store/orders':
      final search = request.query['search'];

      return FakeReply.ok({
        'orders': search == 'zzz' ? [] : [orderJson()],
        'meta': {'current_page': 1, 'last_page': 1, 'total': search == 'zzz' ? 0 : 4},
      });
  }

  return defaultHandler(request);
}

Harness pushHarness({bool pushEnabled = true, bool failSave = false, bool signedIn = true}) => Harness(
      prefs: Harness.returning(),
      tokens: signedIn ? TokenPair.fromJson(tokensJson()) : null,
      handler: (request) => pushHandler(request, pushEnabled: pushEnabled, failSave: failSave),
    );

PushService push(Harness harness) => harness.container.read(pushServiceProvider);

void main() {
  test('pushLocation: order first, then ticket, else inbox', () {
    expect(pushLocation(orderUuid: uuid, ticketId: 41), Routes.order(uuid));
    expect(pushLocation(ticketId: 41), Routes.ticket(41));
    expect(pushLocation(orderUuid: '', ticketId: 0), Routes.notifications);
    expect(pushLocation(), Routes.notifications);
  });

  test('PushMessage reads the FCM data payload', () {
    const message = PushMessage(data: {'notification_id': 'n7', 'ticket_id': '41', 'order_uuid': 'null'});

    expect(message.notificationId, 'n7');
    expect(message.ticketId, 41);
    expect(message.orderUuid, isNull);
  });

  testWidgets('tapped push with ticket_id opens the ticket and marks the inbox row read', (tester) async {
    final harness = pushHarness();
    await harness.pump(tester);

    push(harness).emitOpened(const PushMessage(title: 'New reply', data: {'notification_id': 'n7', 'event': 'ticket_replied', 'ticket_id': '41'}));
    await Harness.settle(tester);

    expect(find.byType(TicketScreen), findsOneWidget);
    expect(find.text('It leaves the warehouse today.'), findsWidgets);
    expect(harness.adapter.to('notifications/n7/read'), hasLength(1));
  });

  testWidgets('tapped push with order_uuid opens the order; none opens the inbox', (tester) async {
    final harness = pushHarness();
    await harness.pump(tester);

    push(harness).emitOpened(const PushMessage(data: {'event': 'order.shipped', 'order_uuid': uuid}));
    await Harness.settle(tester);
    expect(find.byType(OrderDetailScreen), findsOneWidget);

    push(harness).emitOpened(const PushMessage(data: {'event': 'promo'}));
    await Harness.settle(tester);
    expect(find.byType(InboxScreen), findsOneWidget);
  });

  testWidgets('cold-start push waits for the start-up gates', (tester) async {
    final harness = Harness(
      tokens: TokenPair.fromJson(tokensJson()),
      // Onboarding not seen yet: the deep link must wait.
      prefs: {...Harness.returning()}..remove('nogrgr.test.onboarding_seen'),
      handler: pushHandler,
    );
    await harness.pump(tester);
    push(harness).emitOpened(const PushMessage(data: {'ticket_id': '41'}));
    await Harness.settle(tester);
    expect(find.byType(TicketScreen), findsNothing);
    expect(harness.container.read(pushCoordinatorProvider).pending, Routes.ticket(41));

    await harness.container.read(preferencesProvider.notifier).update((p) => p.copyWith(onboardingSeen: true));
    await Harness.settle(tester);
    expect(find.byType(TicketScreen), findsOneWidget);
    expect(harness.container.read(pushCoordinatorProvider).pending, isNull);
  });

  testWidgets('foreground push shows the banner, refreshes the badge; tap opens it', (tester) async {
    final harness = pushHarness();
    await harness.pump(tester);
    final before = harness.adapter.to('notifications/unread-count').length;

    push(harness).emitForeground(const PushMessage(title: 'Your order shipped', body: 'N-1001 is on its way', data: {'order_uuid': uuid}));
    await Harness.settle(tester);

    expect(find.byType(PushBanner), findsOneWidget);
    expect(find.text('Your order shipped'), findsOneWidget);
    expect(harness.adapter.to('notifications/unread-count').length, greaterThan(before));

    await tester.tap(find.byKey(const ValueKey('push-banner-open')));
    await Harness.settle(tester);
    expect(find.byType(PushBanner), findsNothing);
    expect(find.byType(OrderDetailScreen), findsOneWidget);
  });

  testWidgets('banner closes by itself and with the close button', (tester) async {
    final harness = pushHarness();
    await harness.pump(tester);

    push(harness).emitForeground(const PushMessage(title: 'Hello'));
    await Harness.settle(tester);
    expect(find.byType(PushBanner), findsOneWidget);
    await tester.pump(pushBannerDuration);
    await tester.pump();
    expect(find.byType(PushBanner), findsNothing);

    push(harness).emitForeground(const PushMessage(title: 'Again'));
    await Harness.settle(tester);
    await tester.tap(find.byKey(const ValueKey('push-banner-close')));
    await tester.pump();
    expect(find.byType(PushBanner), findsNothing);
  });

  testWidgets('settings: push switch per type saves a partial update', (tester) async {
    final harness = pushHarness();
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.settings);
    await Harness.settle(tester);

    await tester.scrollUntilVisible(find.byKey(const ValueKey('push-promotions')), 200, scrollable: find.descendant(of: find.byType(ListView).first, matching: find.byType(Scrollable)).first);
    await tester.pump();
    expect(find.text('Push notifications'), findsOneWidget);
    expect(tester.widget<SwitchListTile>(find.byKey(const ValueKey('push-promotions'))).value, isFalse);

    await tester.tap(find.byKey(const ValueKey('push-promotions')));
    await Harness.settle(tester);

    final put = harness.adapter.requests.singleWhere((r) => r.path == 'account/notification-preferences' && r.method == 'PUT');
    expect(put.body, {
      'preferences': {
        'promotions': {'push': true},
      },
    });
    expect(tester.widget<SwitchListTile>(find.byKey(const ValueKey('push-promotions'))).value, isTrue);
  });

  testWidgets('settings: failed save restores the switch and shows the error', (tester) async {
    final harness = pushHarness(failSave: true);
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.settings);
    await Harness.settle(tester);

    await tester.scrollUntilVisible(find.byKey(const ValueKey('push-support')), 200, scrollable: find.descendant(of: find.byType(ListView).first, matching: find.byType(Scrollable)).first);
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('push-support')));
    await Harness.settle(tester);

    expect(tester.widget<SwitchListTile>(find.byKey(const ValueKey('push-support'))).value, isTrue);
    expect(find.text('Server error'), findsOneWidget);
  });

  testWidgets('settings: no push section for guests or stores without push', (tester) async {
    final guest = pushHarness(signedIn: false);
    await guest.pump(tester);
    guest.container.read(routerProvider).push(Routes.settings);
    await Harness.settle(tester);
    expect(find.byKey(const ValueKey('settings-push')), findsNothing);
  });

  testWidgets('settings: store with push off hides the switches', (tester) async {
    final harness = pushHarness(pushEnabled: false);
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.settings);
    await Harness.settle(tester);
    expect(find.byKey(const ValueKey('settings-push')), findsNothing);
    expect(harness.adapter.to('account/notification-preferences'), isEmpty);
  });

  testWidgets('orders search sends the term, shows the no-match state and clears', (tester) async {
    final harness = pushHarness();
    await harness.pump(tester);
    await Harness.openTab(tester, 'Account');

    // Row counts: 4 orders, 3 unread notifications.
    expect(find.descendant(of: find.byKey(const ValueKey('account-orders')), matching: find.text('4')), findsOneWidget);
    expect(find.descendant(of: find.byKey(const ValueKey('account-inbox')), matching: find.text('3')), findsOneWidget);

    harness.container.read(routerProvider).push(Routes.orders);
    await Harness.settle(tester);
    expect(find.text('Order N-1001'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('orders-search')), 'zzz');
    await tester.pump(const Duration(milliseconds: 500));
    await Harness.settle(tester);

    expect(harness.adapter.to('store/orders').last.query['search'], 'zzz');
    expect(find.text('No orders match “zzz”.'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('orders-search-clear')));
    await Harness.settle(tester);
    expect(find.text('Order N-1001'), findsOneWidget);
  });
}
