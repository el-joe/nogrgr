import 'json.dart';

/// Money of the API: USD cents + the display conversion (decision #87, display only).
/// The app never computes prices: it shows `formatted` / `display.formatted` as sent.
class Money {
  const Money({required this.amount, required this.formatted, this.displayFormatted, this.displayCurrency});

  factory Money.fromJson(Object? source) {
    final json = jsonMap(source);
    final display = jsonMap(json['display']);

    return Money(
      amount: jsonInt(json['amount']),
      formatted: '${json['formatted'] ?? ''}',
      displayFormatted: display['formatted'] as String?,
      displayCurrency: display['currency'] as String?,
    );
  }

  static Money? maybe(Object? source) => source is Map<String, dynamic> ? Money.fromJson(source) : null;

  /// USD cents (what is charged).
  final int amount;

  /// USD formatted.
  final String formatted;
  final String? displayFormatted;
  final String? displayCurrency;

  /// What the customer sees: the display currency when converted, else USD.
  String get label => displayFormatted ?? formatted;

  /// True when the label is not the charged USD amount (show the USD charge note).
  bool get converted => displayFormatted != null && displayCurrency != null && displayCurrency != 'USD';

  bool get isZero => amount == 0;
}

/// Flash sale of a card / product.
class FlashInfo {
  const FlashInfo({required this.percentage, this.endsAt});

  static FlashInfo? maybe(Object? source) {
    if (source is! Map<String, dynamic>) {
      return null;
    }

    return FlashInfo(percentage: jsonInt(source['percentage']), endsAt: jsonDate(source['ends_at']));
  }

  final int percentage;
  final DateTime? endsAt;
}

/// Product card of the Store API / favourites (ProductSummaryResource + card extras).
class ProductCard {
  const ProductCard({
    required this.id,
    required this.name,
    required this.slug,
    required this.price,
    required this.inStock,
    this.image,
    this.comparePrice,
    this.discountPercent,
    this.rating = 0,
    this.reviewCount = 0,
    this.hasVariants = false,
    this.favorited = false,
    this.flash,
    this.badge,
    this.stockLeft,
    this.soldCount,
    this.match,
  });

  factory ProductCard.fromJson(Map<String, dynamic> json) => ProductCard(
        id: jsonInt(json['id']),
        name: '${json['name'] ?? ''}',
        slug: '${json['slug'] ?? ''}',
        image: jsonString(json['image']),
        price: Money.fromJson(json['price']),
        comparePrice: Money.maybe(json['compare_price']),
        discountPercent: jsonIntOrNull(json['discount_percent']),
        inStock: json['in_stock'] != false,
        rating: jsonDouble(json['rating']),
        reviewCount: jsonInt(json['review_count']),
        hasVariants: json['has_variants'] == true,
        favorited: json['favorited'] == true,
        flash: FlashInfo.maybe(json['flash']),
        badge: json['badge'] is String ? json['badge'] as String : null,
        stockLeft: jsonIntOrNull(json['stock_left']),
        soldCount: jsonIntOrNull(json['sold_count']),
      );

  /// Card of `POST search/image` (formatted strings instead of money objects).
  factory ProductCard.fromImageMatch(Map<String, dynamic> json) => ProductCard(
        id: jsonInt(json['id']),
        name: '${json['name'] ?? ''}',
        slug: '',
        image: jsonString(json['image']),
        price: Money(amount: jsonInt(json['price_minor']), formatted: '${json['price'] ?? ''}'),
        comparePrice: json['compare'] is String ? Money(amount: 0, formatted: json['compare'] as String) : null,
        inStock: json['in_stock'] != false,
        match: jsonIntOrNull(json['match']),
      );

  static List<ProductCard> list(Object? source) => jsonList(source).map(ProductCard.fromJson).toList();

  final int id;
  final String name;
  final String slug;
  final String? image;
  final Money price;
  final Money? comparePrice;
  final int? discountPercent;
  final bool inStock;
  final double rating;
  final int reviewCount;
  final bool hasVariants;
  final bool favorited;
  final FlashInfo? flash;
  final String? badge;
  final int? stockLeft;
  final int? soldCount;

  /// Image-search similarity (0–100).
  final int? match;
}
