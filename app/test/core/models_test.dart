import 'package:app/core/models/product_card.dart';
import 'package:app/features/cart/data/cart_repository.dart';
import 'package:app/features/catalog/data/catalog_models.dart';
import 'package:app/features/catalog/presentation/home_screen.dart';
import 'package:app/features/orders/data/orders_repository.dart';
import 'package:app/features/returns/data/returns_repository.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fixtures.dart';
import '../support/harness.dart';

void main() {
  group('Money', () {
    test('shows the display conversion and flags the USD charge', () {
      final money = Money.fromJson(money0(1999, display: 'SAR 74.96'));

      expect(money.label, 'SAR 74.96');
      expect(money.formatted, 'USD 19.99');
      expect(money.converted, isTrue);
      expect(money.amount, 1999);
    });

    test('falls back to USD without a conversion', () {
      final money = Money.fromJson(money0(500));

      expect(money.label, 'USD 5.00');
      expect(money.converted, isFalse);
    });
  });

  test('product card reads the Store API card', () {
    final card = ProductCard.fromJson(cardJson(7, name: 'Bag', favorited: true));

    expect(card.id, 7);
    expect(card.name, 'Bag');
    expect(card.comparePrice, isNotNull);
    expect(card.discountPercent, 20);
    expect(card.reviewCount, 128);
    expect(card.favorited, isTrue);
  });

  test('image search matches carry the match %', () {
    final card = ProductCard.fromImageMatch({'id': 3, 'name': 'Lamp', 'price': '\$10.00', 'price_minor': 1000, 'in_stock': true, 'match': 87});

    expect(card.match, 87);
    expect(card.price.label, '\$10.00');
  });

  test('listing filters become the API query', () {
    const filters = ListingFilters(priceRange: '1000-4999', rating: 4, inStock: true, discount: 20, categories: {3}, options: {5: {'Red'}});

    expect(filters.toQuery(), {
      'price_min': '1000',
      'price_max': '4999',
      'rating': 4,
      'in_stock': 1,
      'discount': 20,
      'categories[]': [3],
      'options[5][]': ['Red'],
    });
    expect(filters.count, 6);
    expect(const ListingFilters(priceRange: '-999').toQuery(), {'price_max': '999'});
  });

  test('product detail finds the variant of the picked options', () {
    final product = ProductDetail.fromJson(productJson());

    expect(product.variants, hasLength(2));
    expect(product.variantFor({1: 11})!.id, 101);
    expect(product.variantFor({1: 12})!.price.label, 'USD 149.00');
    expect(product.variantFor({1: 99}), isNull);
    expect(product.delivery!.label, 'Tue 1 Dec – Thu 3 Dec');
    expect(product.reviews.distribution[5], 10);
  });

  test('home data reads every family section', () {
    final home = HomeData.fromJson(homeJson());

    expect(home.family, 'elora');
    expect(home.bestSelling, hasLength(2));
    expect(home.categories.single.slug, 'fashion');
    expect(home.trending.single.name, 'Leather bag');
    expect(home.isEmpty, isFalse);
  });

  test('home skins follow the Figma frame of each family / variant', () {
    expect(HomeSkin.of('ecommet', 1).top, HomeTop.banner);
    expect(HomeSkin.of('elora', 1).top, HomeTop.duo);
    expect(HomeSkin.of('elora', 2).categoryTiles, isTrue);
    expect(HomeSkin.of('elora', 3).midBanner, isTrue);
    expect(HomeSkin.of('souqify', 1).railBeforeCategories, isTrue);
    expect(HomeSkin.of('souqify', 4).header, HomeHeader.greet);
  });

  test('cart reads lines, coupon, free shipping and totals', () {
    final cart = CartData.fromJson(cartJson());

    expect(cart.lines.single.name, 'Wireless headphones');
    expect(cart.lines.single.quantity, 2);
    expect(cart.coupon!.code, 'SAVE10');
    expect(cart.freeShippingProgress, 40);
    expect(cart.totals.grandTotal.label, 'SAR 900.00');
    expect(cart.canCheckout, isTrue);
  });

  test('order reads totals, timeline and the address snapshot', () {
    final order = Order.fromJson(orderJson());

    expect(order.number, 'N-1001');
    expect(order.grandTotal.formatted, 'USD 129.00');
    expect(order.timeline.single.label, 'Placed');
    expect(order.shippingAddress!.lines('en'), ['Fatma Elsonny', '+966500000000', 'King Fahd Rd 1', 'Riyadh, Riyadh Province', 'Saudi Arabia']);
    expect(order.shippingAddress!.country('ar'), 'السعودية');
    expect(order.paymentGatewayLabel, 'Stripe');
    expect(order.cancellable, isTrue);
    expect(order.awaitingPayment, isFalse);
  });

  test('return row reads the customer view', () {
    final row = ReturnRow.fromJson(returnJson());

    expect(row.reference, 'R-1');
    expect(row.reasonName, 'Damaged');
    expect(row.timeline.single.author, 'store');
    expect(row.canReply, isTrue);
  });
}

Map<String, dynamic> money0(int cents, {String? display}) => money(cents, display: display);
