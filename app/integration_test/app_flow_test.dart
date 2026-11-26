import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';

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
}
