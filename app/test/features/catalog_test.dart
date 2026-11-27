import 'package:app/app/router.dart';
import 'package:app/features/catalog/presentation/product_list_screen.dart';
import 'package:app/features/catalog/presentation/product_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/fixtures.dart';
import '../support/harness.dart';

Map<String, dynamic> listingJson(List<Map<String, dynamic>> items, {int page = 1, int last = 1}) => {
      'items': items,
      'meta': {'current_page': page, 'last_page': last, 'per_page': 20, 'total': items.length},
      'facets': {
        'price': [
          {'value': '-4999', 'label': 'Under USD 50.00', 'count': 3},
        ],
        'rating': [],
        'options': [
          {
            'id': 5,
            'name': 'Colour',
            'values': [
              {'value': 'Red', 'count': 2},
            ],
          },
        ],
        'categories': [],
        'in_stock': 3,
      },
      'sort': 'recommended',
      'sorts': ['recommended', 'newest', 'price_asc'],
    };

Future<FakeReply> catalogHandler(SentRequest request) async {
  if (request.path == 'store/products') {
    final page = int.tryParse('${request.query['page']}') ?? 1;

    return FakeReply.ok(listingJson([cardJson(page * 100 + 1, name: 'Page $page item')], page: page, last: 2));
  }

  if (request.path == 'store/products/3') {
    return FakeReply.ok(productJson(id: 3));
  }

  if (request.path == 'store/products/404') {
    return FakeReply.error(404, 'Not found');
  }

  if (request.path == 'store/cart/items') {
    return FakeReply(201, {'success': true, 'message': 'Added', 'data': cartJson(quantity: 1)});
  }

  if (request.path == 'store/search/autocomplete') {
    return FakeReply.ok({
      'term': request.query['q'],
      'products': [cardJson(31, name: 'Headphones mini')],
      'categories': [
        {'id': 10, 'name': 'Headwear', 'slug': 'headwear'},
      ],
    });
  }

  if (request.path == 'store/search') {
    return FakeReply.ok({...listingJson([]), 'term': request.query['q'], 'popular': [cardJson(40, name: 'Popular pick')]});
  }

  if (request.path == 'store/categories') {
    return FakeReply.ok({
      'items': [
        {
          'id': 1,
          'name': 'Women',
          'slug': 'women',
          'children': [
            {'id': 2, 'name': 'Dresses', 'slug': 'dresses', 'children': []},
          ],
        },
        {'id': 3, 'name': 'Men', 'slug': 'men', 'children': []},
      ],
    });
  }

  return defaultHandler(request);
}

void main() {
  testWidgets('home renders the family sections from the API with swipeable rails', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: catalogHandler);
    await harness.pump(tester);

    expect(harness.adapter.to('store/home'), hasLength(1));
    expect(find.text('Fashion'), findsOneWidget);
    expect(find.text('Eau de parfum'), findsWidgets);
    expect(find.byKey(const ValueKey('home-search')), findsOneWidget);
    expect(find.byKey(const ValueKey('home-image-search')), findsOneWidget);
    // Prices come from the API display fields.
    expect(find.text('USD 129.00'), findsWidgets);
  });

  testWidgets('ecommet skin uses the brand header and lightning deals', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async {
        if (request.path == 'store/home') {
          final json = homeJson(family: 'ecommet');
          (json['sections'] as Map<String, dynamic>)['deals'] = [cardJson(70, name: 'Deal item')];

          return FakeReply.ok(json);
        }

        return catalogHandler(request);
      },
    );
    await harness.pump(tester);

    expect(find.text('Lightning deals'), findsOneWidget);
    expect(find.text('Deal item'), findsOneWidget);
  });

  testWidgets('product details: variant picker updates price; add to cart sends the variant', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: catalogHandler);
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.product(3));
    await Harness.settle(tester);

    expect(find.byType(ProductScreen), findsOneWidget);
    expect(find.text('Wireless headphones'), findsOneWidget);
    expect(find.text('USD 129.00'), findsWidgets);

    await tester.tap(find.byKey(const ValueKey('option-1-12')));
    await tester.pump();
    expect(find.text('USD 149.00'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('product-add-to-cart')));
    await Harness.settle(tester);

    await tester.dragUntilVisible(find.text('Arrives Tue 1 Dec – Thu 3 Dec'), find.byType(CustomScrollView), const Offset(0, -200));
    expect(find.text('Courier company: Aramex'), findsOneWidget);

    final sent = harness.adapter.to('store/cart/items').single;
    expect(sent.body, {'store_product_id': 3, 'variant_id': 102, 'quantity': 1});
    expect(find.text('Item Added to Cart!'), findsOneWidget);
  });

  testWidgets('a product not sold in the shopping country shows the unavailable state', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: catalogHandler);
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.product(404));
    await Harness.settle(tester);

    expect(find.text('Not available in your country'), findsOneWidget);
  });

  testWidgets('product list pages on scroll and applies filters from the API facets', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: catalogHandler);
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.listing(kind: 'new_in'));
    await Harness.settle(tester);

    expect(find.byType(ProductListScreen), findsOneWidget);
    expect(find.text('New In'), findsOneWidget);
    expect(find.text('Page 1 item'), findsOneWidget);
    expect(harness.adapter.to('store/products').first.query['kind'], 'new_in');

    await tester.tap(find.byKey(const ValueKey('listing-filters')));
    await Harness.settle(tester);
    await tester.tap(find.text('Under USD 50.00 (3)'));
    await tester.scrollUntilVisible(find.text('Red (2)'), 150, scrollable: find.descendant(of: find.byType(BottomSheet), matching: find.byType(Scrollable)).first);
    await tester.tap(find.text('Red (2)'));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('filters-apply')));
    await Harness.settle(tester);

    final query = harness.adapter.to('store/products').last.query;
    expect(query['price_max'], '4999');
    expect(query['options[5][]'], ['Red']);
    expect(find.text('Filters (2)'), findsOneWidget);
  });

  testWidgets('search: autocomplete from 2 letters, then results with popular fallback', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: catalogHandler);
    await harness.pump(tester);
    await tester.tap(find.byKey(const ValueKey('home-search')));
    await Harness.settle(tester);

    await tester.enterText(find.byKey(const ValueKey('search-field')), 'he');
    await tester.pump(const Duration(milliseconds: 350));
    await Harness.settle(tester);

    expect(harness.adapter.to('store/search/autocomplete').single.query['q'], 'he');
    expect(find.text('Headphones mini'), findsOneWidget);
    expect(find.text('Headwear'), findsOneWidget);

    await tester.testTextInput.receiveAction(TextInputAction.search);
    await Harness.settle(tester);

    expect(harness.adapter.to('store/search').single.query['q'], 'he');
    expect(find.text('No results for “he”'), findsOneWidget);
    expect(find.text('Popular pick'), findsOneWidget);
  });

  testWidgets('categories tab shows the tree and the children of the picked category', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: catalogHandler);
    await harness.pump(tester);
    await Harness.openTab(tester, 'Categories');

    expect(find.text('All categories'), findsOneWidget);
    expect(find.byKey(const ValueKey('category-dresses')), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('category-side-men')));
    await Harness.settle(tester);
    expect(find.byKey(const ValueKey('category-dresses')), findsNothing);
  });

  testWidgets('guest favourite tap opens sign in', (tester) async {
    final harness = Harness(prefs: Harness.returning(), handler: catalogHandler);
    await harness.pump(tester);
    harness.container.read(routerProvider).push(Routes.product(3));
    await Harness.settle(tester);

    await tester.tap(find.bySemanticsLabel('Save to favourites').first);
    await Harness.settle(tester);

    expect(find.text('Welcome back'), findsOneWidget);
  });
}
