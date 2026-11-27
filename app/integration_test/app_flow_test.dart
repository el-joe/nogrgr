import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/push/push_service.dart';

import '../test/features/support_tickets_test.dart' show ticketsHandler;
import '../test/support/fake_api.dart';
import '../test/support/harness.dart';

/// On-device flow (emulator / simulator): `flutter test integration_test`.
/// Runs the real app against the fake API: first launch → onboarding → setup →
/// sign in → account → sign out.
void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('first launch to signed-in account and back to guest', (tester) async {
    final harness = Harness(
      handler: (request) async => switch (request.path) {
        'auth/login' => FakeReply.ok({'customer': customerJson(), 'tokens': tokensJson()}),
        'auth/logout' => FakeReply.ok(null),
        _ => defaultHandler(request),
      },
    );
    await harness.pump(tester);

    await tester.tap(find.text('Skip'));
    await Harness.settle(tester);
    await tester.tap(find.text('Continue'));
    await Harness.settle(tester);

    // Setup lands on Home (batch 2); sign in from the Account tab.
    await Harness.openTab(tester, 'Account');
    await tester.tap(find.byKey(const ValueKey('account-sign-in')));
    await Harness.settle(tester);
    await tester.enterText(find.byKey(const ValueKey('login-email')), 'fatma@example.com');
    await tester.enterText(find.byKey(const ValueKey('login-password')), 'secret-pass');
    await tester.tap(find.byKey(const ValueKey('login-submit')));
    await Harness.settle(tester);
    expect(find.text('Fatma Elsonny'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('account-logout')));
    await Harness.settle(tester);
    await tester.tap(find.widgetWithText(TextButton, 'Log out'));
    await Harness.settle(tester);
    expect(find.text('Sign in for the best experience'), findsOneWidget);
  });

  testWidgets('support push opens the ticket; the customer replies', (tester) async {
    final harness = Harness(prefs: Harness.returning(), tokens: TokenPair.fromJson(tokensJson()), handler: ticketsHandler);
    await harness.pump(tester);

    harness.container.read(pushServiceProvider).emitOpened(const PushMessage(data: {'event': 'ticket_replied', 'ticket_id': '41'}));
    await Harness.settle(tester);
    expect(find.text('Where is my parcel?'), findsWidgets);

    await tester.scrollUntilVisible(find.byKey(const ValueKey('ticket-reply')), 200, scrollable: find.byType(Scrollable).first);
    await tester.enterText(find.byKey(const ValueKey('ticket-reply')), 'Thank you');
    await tester.scrollUntilVisible(find.byKey(const ValueKey('ticket-reply-send')), 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.byKey(const ValueKey('ticket-reply-send')));
    await Harness.settle(tester);
    expect(harness.adapter.to('store/support/tickets/41/messages'), hasLength(1));
  });
}
