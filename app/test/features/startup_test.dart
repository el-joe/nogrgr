import 'package:app/core/providers.dart';
import 'package:app/features/onboarding/onboarding_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/harness.dart';

void main() {
  testWidgets('first launch: splash → onboarding → first-run setup → account', (tester) async {
    final harness = Harness();
    await harness.pump(tester);

    expect(find.byType(OnboardingScreen), findsOneWidget);
    expect(find.text('Shop your favourite brands'), findsOneWidget);

    await tester.tap(find.text('Skip'));
    await Harness.settle(tester);

    expect(find.text('Set up your shopping'), findsOneWidget);
    expect(find.textContaining('Saudi Arabia'), findsOneWidget);
    expect(find.textContaining('USD — US dollar'), findsOneWidget);

    await tester.tap(find.text('Continue'));
    await Harness.settle(tester);

    // Lands on the Home tab (catalog), the account tab offers sign in.
    expect(find.text('Eau de parfum'), findsWidgets);
    await Harness.openTab(tester, 'Account');
    expect(find.text('Sign in for the best experience'), findsOneWidget);
    // Store defaults were adopted from bootstrap.
    final prefs = harness.container.read(preferencesProvider);
    expect(prefs.country, 'SA');
    expect(prefs.currency, 'USD');
  });

  testWidgets('onboarding pages swipe and end with Get started', (tester) async {
    await Harness().pump(tester);

    await tester.tap(find.text('Next'));
    await Harness.settle(tester);
    expect(find.text('Track every order'), findsOneWidget);

    await tester.fling(find.byType(PageView), const Offset(-500, 0), 1500);
    await Harness.settle(tester);
    expect(find.text('Pay your way'), findsOneWidget);
    expect(find.text('Get started'), findsOneWidget);
  });

  testWidgets('offline start shows the offline state and retries', (tester) async {
    final harness = Harness(prefs: Harness.returning());
    harness.adapter.offline = true;
    await harness.pump(tester);

    expect(find.text('You’re offline'), findsOneWidget);

    harness.adapter.offline = false;
    await tester.tap(find.text('Try again'));
    await Harness.settle(tester);

    expect(find.text('Eau de parfum'), findsWidgets);
  });

  testWidgets('unknown store shows the can’t-reach state with TENANT_404', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async => FakeReply.error(404, 'Store not found.'),
    );
    await harness.pump(tester);

    expect(find.text('We can’t reach this store'), findsOneWidget);
    expect(find.textContaining('TENANT_404'), findsOneWidget);
  });

  testWidgets('required update blocks the app', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async =>
          request.path == 'bootstrap' ? FakeReply.ok(bootstrapJson(update: 'required')) : defaultHandler(request),
    );
    await harness.pump(tester);

    expect(find.text('Update required'), findsOneWidget);
    expect(find.text('Update now'), findsOneWidget);
  });

  testWidgets('maintenance shows the store message', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async => request.path == 'bootstrap'
          ? FakeReply.ok(bootstrapJson(maintenance: true, maintenanceMessage: 'Back at 14:00'))
          : defaultHandler(request),
    );
    await harness.pump(tester);

    expect(find.text('We will be right back'), findsOneWidget);
    expect(find.text('Back at 14:00'), findsOneWidget);
  });

  testWidgets('optional update is offered once', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async =>
          request.path == 'bootstrap' ? FakeReply.ok(bootstrapJson(update: 'optional')) : defaultHandler(request),
    );
    await harness.pump(tester);

    expect(find.text('A new version is available'), findsOneWidget);
    await tester.tap(find.text('Later'));
    await Harness.settle(tester);
    expect(find.text('A new version is available'), findsNothing);
  });

  testWidgets('store texts from the API replace bundled strings', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async => request.path == 'texts'
          ? FakeReply.ok({'locale': 'en', 'version': 'v1', 'texts': {'mobile.tabs.account': 'Me'}})
          : defaultHandler(request),
    );
    await harness.pump(tester);

    expect(find.text('Me'), findsOneWidget);
  });
}
