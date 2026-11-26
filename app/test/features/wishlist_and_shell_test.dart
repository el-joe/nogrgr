import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/theme/app_theme.dart';
import 'package:app/core/theme/app_tokens.dart';
import 'package:app/features/shell/app_tab_bar.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/harness.dart';

Map<String, dynamic> product(int id, String name) => {
      'id': id,
      'name': name,
      'slug': 'p-$id',
      'image': null,
      'price': {'amount': 1999, 'currency': 'USD', 'formatted': r'$19.99', 'display': {'currency': 'SAR', 'amount': 7496, 'formatted': 'SAR 74.96'}},
      'compare_price': null,
      'discount_percent': null,
      'in_stock': true,
      'favorited': true,
    };

void main() {
  testWidgets('tab bar: active tab in primary, badge shown', (tester) async {
    var tapped = -1;
    await tester.pumpWidget(MaterialApp(
      theme: AppTheme.build(family: ThemeFamily.elora, brightness: Brightness.light, rtl: false),
      home: Scaffold(
        bottomNavigationBar: AppTabBar(
          currentIndex: 1,
          onTap: (i) => tapped = i,
          items: const [
            AppTabItem(icon: 'cart', label: 'Cart', badge: 3),
            AppTabItem(icon: 'users', label: 'Account'),
          ],
        ),
      ),
    ));

    expect(find.text('3'), findsOneWidget);
    final account = tester.widget<Text>(find.text('Account'));
    expect(account.style?.color, const Color(0xFFFF4D00));
    await tester.tap(find.text('Cart'));
    expect(tapped, 0);
  });

  testWidgets('guest wishlist asks to sign in', (tester) async {
    await Harness(prefs: Harness.returning()).pump(tester);
    await tester.tap(find.text('Favorites'));
    await Harness.settle(tester);

    expect(find.text('Sign in to save your favourite products.'), findsOneWidget);
  });

  testWidgets('signed-in wishlist: empty state, then items with display prices, remove', (tester) async {
    var items = <Map<String, dynamic>>[];
    final harness = Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: (request) async {
        if (request.path == 'favorites') {
          return FakeReply.ok({'items': items, 'ids': [for (final i in items) i['id']], 'meta': {'current_page': 1, 'last_page': 1}});
        }
        if (request.path == 'favorites/11' && request.method == 'DELETE') {
          return FakeReply.ok(null);
        }

        return defaultHandler(request);
      },
    );
    await harness.pump(tester);
    await tester.tap(find.text('Favorites'));
    await Harness.settle(tester);
    expect(find.text('Your wishlist is empty'), findsOneWidget);

    items = [product(11, 'Linen shirt'), product(12, 'Desk lamp')];
    await tester.fling(find.text('Your wishlist is empty'), const Offset(0, 400), 1000);
    await Harness.settle(tester);

    expect(find.text('Linen shirt'), findsOneWidget);
    expect(find.text('SAR 74.96'), findsNWidgets(2));

    await tester.tap(find.byTooltip('Remove Linen shirt from your wishlist'));
    await Harness.settle(tester);

    expect(find.text('Linen shirt'), findsNothing);
    expect(harness.adapter.requests.where((r) => r.method == 'DELETE'), hasLength(1));
  });

  testWidgets('a rejected refresh token drops to guest with the session-expired notice', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: (request) async => switch (request.path) {
        'me' || 'auth/refresh' => FakeReply.error(401, 'Unauthenticated.'),
        _ => defaultHandler(request),
      },
    );
    await harness.pump(tester);

    expect(await harness.storage.read(), isNull);
    expect(find.text('Sign in for the best experience'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('account-sign-in')));
    await Harness.settle(tester);
    expect(find.text('Your session has expired. Please sign in again.'), findsOneWidget);
  });
}
