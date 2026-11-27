import '../../../core/models/json.dart';
import '../../../core/models/product_card.dart';

class HomeBanner {
  const HomeBanner({required this.id, this.title, this.subtitle, this.image, this.url, this.cta});

  factory HomeBanner.fromJson(Map<String, dynamic> json) => HomeBanner(
        id: '${json['id'] ?? ''}',
        title: jsonString(json['title']),
        subtitle: jsonString(json['subtitle']),
        image: jsonString(json['mobile_image_url']) ?? jsonString(json['image_url']),
        url: jsonString(json['url']),
        cta: jsonString(json['cta_label']),
      );

  final String id;
  final String? title;
  final String? subtitle;
  final String? image;
  final String? url;
  final String? cta;
}

class Category {
  const Category({required this.id, required this.name, required this.slug, this.parentId, this.image, this.description, this.children = const []});

  factory Category.fromJson(Map<String, dynamic> json) => Category(
        id: jsonInt(json['id']),
        parentId: jsonIntOrNull(json['parent_id']),
        name: '${json['name'] ?? ''}',
        slug: '${json['slug'] ?? ''}',
        image: jsonString(json['image']),
        description: jsonString(json['description']),
        children: jsonList(json['children']).map(Category.fromJson).toList(),
      );

  static List<Category> list(Object? source) => jsonList(source).map(Category.fromJson).toList();

  final int id;
  final int? parentId;
  final String name;
  final String slug;
  final String? image;
  final String? description;
  final List<Category> children;
}

class FlashSale {
  const FlashSale({required this.title, required this.percentage, required this.endsAt, this.banner});

  static FlashSale? maybe(Object? source) {
    if (source is! Map<String, dynamic>) {
      return null;
    }

    final ends = jsonDate(source['ends_at']);

    return ends == null
        ? null
        : FlashSale(title: '${source['title'] ?? ''}', percentage: jsonInt(source['percentage']), endsAt: ends, banner: jsonString(source['banner_url']));
  }

  final String title;
  final int percentage;
  final DateTime endsAt;
  final String? banner;
}

class BadgeShelf {
  const BadgeShelf({required this.name, required this.items});

  final String name;
  final List<ProductCard> items;
}

/// `GET home` (decision #106).
class HomeData {
  const HomeData({
    required this.family,
    required this.variant,
    this.hero = const [],
    this.promo = const [],
    this.deals = const [],
    this.clearance = const [],
    this.flash,
    this.bestSelling = const [],
    this.topRated = const [],
    this.newIn = const [],
    this.categories = const [],
    this.trending = const [],
    this.topRatedRail = const [],
    this.newRail = const [],
    this.offers = const [],
    this.recommended = const [],
    this.badges = const [],
    this.tabs = const [],
  });

  factory HomeData.fromJson(Map<String, dynamic> json) {
    final theme = jsonMap(json['theme']);
    final s = jsonMap(json['sections']);
    List<ProductCard> listing(String key) => ProductCard.list(jsonMap(s[key])['items']);

    return HomeData(
      family: '${theme['family'] ?? 'elora'}',
      variant: jsonInt(theme['variant'], 1),
      hero: jsonList(s['hero']).map(HomeBanner.fromJson).toList(),
      promo: jsonList(s['promo']).map(HomeBanner.fromJson).toList(),
      deals: ProductCard.list(s['deals']),
      clearance: ProductCard.list(s['clearance']),
      flash: FlashSale.maybe(s['flash_sale']),
      bestSelling: listing('best_selling'),
      topRated: listing('top_rated'),
      newIn: listing('new_in'),
      categories: Category.list(s['categories']),
      trending: ProductCard.list(s['trending']),
      topRatedRail: ProductCard.list(s['top_rated_rail']),
      newRail: ProductCard.list(s['new_rail']),
      offers: ProductCard.list(s['offers']),
      recommended: [for (final row in (s['recommended'] is List ? s['recommended'] as List : const [])) ...ProductCard.list(row)],
      badges: [
        for (final shelf in jsonList(s['badges'])) BadgeShelf(name: '${shelf['name'] ?? ''}', items: ProductCard.list(shelf['items'])),
      ],
      tabs: [for (final tab in jsonList(s['tabs'])) (key: '${tab['key']}', label: '${tab['label']}')],
    );
  }

  final String family;
  final int variant;
  final List<HomeBanner> hero;
  final List<HomeBanner> promo;
  final List<ProductCard> deals;
  final List<ProductCard> clearance;
  final FlashSale? flash;
  final List<ProductCard> bestSelling;
  final List<ProductCard> topRated;
  final List<ProductCard> newIn;
  final List<Category> categories;
  final List<ProductCard> trending;
  final List<ProductCard> topRatedRail;
  final List<ProductCard> newRail;
  final List<ProductCard> offers;
  final List<ProductCard> recommended;
  final List<BadgeShelf> badges;
  final List<({String key, String label})> tabs;

  bool get isEmpty =>
      hero.isEmpty && promo.isEmpty && deals.isEmpty && bestSelling.isEmpty && newIn.isEmpty && topRated.isEmpty && trending.isEmpty && categories.isEmpty;
}

class FacetValue {
  const FacetValue({required this.value, required this.label, required this.count});

  final String value;
  final String label;
  final int count;
}

class OptionFacet {
  const OptionFacet({required this.id, required this.name, required this.values});

  final int id;
  final String name;
  final List<FacetValue> values;
}

class Facets {
  const Facets({this.price = const [], this.rating = const [], this.options = const [], this.categories = const [], this.inStock = 0});

  factory Facets.fromJson(Object? source) {
    final json = jsonMap(source);

    return Facets(
      price: [for (final p in jsonList(json['price'])) FacetValue(value: '${p['value']}', label: '${p['label']}', count: jsonInt(p['count']))],
      rating: [for (final r in jsonList(json['rating'])) FacetValue(value: '${r['value']}', label: '${r['value']}', count: jsonInt(r['count']))],
      options: [
        for (final o in jsonList(json['options']))
          OptionFacet(
            id: jsonInt(o['id']),
            name: '${o['name']}',
            values: [for (final v in jsonList(o['values'])) FacetValue(value: '${v['value']}', label: '${v['value']}', count: jsonInt(v['count']))],
          ),
      ],
      categories: [for (final c in jsonList(json['categories'])) FacetValue(value: '${c['id']}', label: '${c['name']}', count: jsonInt(c['count']))],
      inStock: jsonInt(json['in_stock']),
    );
  }

  final List<FacetValue> price;
  final List<FacetValue> rating;
  final List<OptionFacet> options;
  final List<FacetValue> categories;
  final int inStock;
}

/// One page of `GET products` / `GET search`.
class Listing {
  const Listing({required this.items, required this.meta, this.facets = const Facets(), this.sort, this.sorts = const [], this.popular = const []});

  factory Listing.fromJson(Map<String, dynamic> json) => Listing(
        items: ProductCard.list(json['items']),
        meta: PageMeta.fromJson(json['meta']),
        facets: Facets.fromJson(json['facets']),
        sort: jsonString(json['sort']),
        sorts: [for (final s in (json['sorts'] is List ? json['sorts'] as List : const [])) '$s'],
        popular: ProductCard.list(json['popular']),
      );

  final List<ProductCard> items;
  final PageMeta meta;
  final Facets facets;
  final String? sort;
  final List<String> sorts;
  final List<ProductCard> popular;
}

/// Filters of a listing (all optional; prices in USD cents as the API expects).
class ListingFilters {
  const ListingFilters({this.priceRange, this.rating, this.inStock = false, this.flash = false, this.discount, this.added, this.categories = const {}, this.options = const {}});

  /// Facet bucket value `"min-max"` (either side may be empty).
  final String? priceRange;
  final int? rating;
  final bool inStock;
  final bool flash;
  final int? discount;
  final int? added;
  final Set<int> categories;
  final Map<int, Set<String>> options;

  int get count =>
      (priceRange != null ? 1 : 0) +
      (rating != null ? 1 : 0) +
      (inStock ? 1 : 0) +
      (flash ? 1 : 0) +
      (discount != null ? 1 : 0) +
      (added != null ? 1 : 0) +
      categories.length +
      options.values.fold(0, (sum, values) => sum + values.length);

  Map<String, dynamic> toQuery() {
    final query = <String, dynamic>{};

    if (priceRange != null) {
      final parts = priceRange!.split('-');

      if (parts.isNotEmpty && parts.first.isNotEmpty) {
        query['price_min'] = parts.first;
      }

      if (parts.length > 1 && parts[1].isNotEmpty) {
        query['price_max'] = parts[1];
      }
    }

    if (rating != null) {
      query['rating'] = rating;
    }

    if (inStock) {
      query['in_stock'] = 1;
    }

    if (flash) {
      query['flash'] = 1;
    }

    if (discount != null) {
      query['discount'] = discount;
    }

    if (added != null) {
      query['added'] = added;
    }

    if (categories.isNotEmpty) {
      query['categories[]'] = categories.toList();
    }

    options.forEach((attribute, values) {
      if (values.isNotEmpty) {
        query['options[$attribute][]'] = values.toList();
      }
    });

    return query;
  }

  ListingFilters copyWith({
    String? Function()? priceRange,
    int? Function()? rating,
    bool? inStock,
    bool? flash,
    int? Function()? discount,
    int? Function()? added,
    Set<int>? categories,
    Map<int, Set<String>>? options,
  }) =>
      ListingFilters(
        priceRange: priceRange != null ? priceRange() : this.priceRange,
        rating: rating != null ? rating() : this.rating,
        inStock: inStock ?? this.inStock,
        flash: flash ?? this.flash,
        discount: discount != null ? discount() : this.discount,
        added: added != null ? added() : this.added,
        categories: categories ?? this.categories,
        options: options ?? this.options,
      );
}

/// What a product list shows: a kind (new_in …), a category or a search term.
class ListingSource {
  const ListingSource({this.kind, this.category, this.term, this.title});

  final String? kind;
  final String? category;
  final String? term;
  final String? title;

  bool get isSearch => term != null;

  Map<String, dynamic> toQuery() => {
        if (kind != null) 'kind': kind,
        if (category != null) 'category': category,
        if (term != null) 'q': term,
      };

  @override
  bool operator ==(Object other) => other is ListingSource && other.kind == kind && other.category == category && other.term == term;

  @override
  int get hashCode => Object.hash(kind, category, term);
}

class AttributeOption {
  const AttributeOption({required this.id, required this.name, this.swatch});

  final int id;
  final String name;
  final String? swatch;
}

class ProductAttribute {
  const ProductAttribute({required this.id, required this.name, required this.swatch, required this.options});

  final int id;
  final String name;
  final bool swatch;
  final List<AttributeOption> options;
}

class Variant {
  const Variant({required this.id, required this.label, required this.options, required this.price, required this.inStock, this.comparePrice, this.discountPercent, this.stock, this.image});

  final int id;
  final String label;

  /// attributeId → optionId.
  final Map<int, int> options;
  final Money price;
  final Money? comparePrice;
  final int? discountPercent;
  final bool inStock;
  final int? stock;
  final String? image;
}

class DeliveryEstimate {
  const DeliveryEstimate({required this.label, this.company, this.cutoffMinutes, this.location});

  final String label;
  final String? company;
  final int? cutoffMinutes;
  final String? location;
}

class ReviewSummary {
  const ReviewSummary({this.average = 0, this.count = 0, this.distribution = const {}});

  factory ReviewSummary.fromJson(Object? source) {
    final json = jsonMap(source);
    final distribution = <int, int>{};
    jsonMap(json['distribution']).forEach((key, value) => distribution[int.tryParse(key) ?? 0] = jsonInt(value));

    return ReviewSummary(average: jsonDouble(json['average']), count: jsonInt(json['count']), distribution: distribution);
  }

  final double average;
  final int count;
  final Map<int, int> distribution;
}

/// `GET products/{id}`.
class ProductDetail {
  const ProductDetail({
    required this.id,
    required this.name,
    required this.images,
    required this.price,
    required this.inStock,
    this.summary,
    this.description,
    this.comparePrice,
    this.discountPercent,
    this.flash,
    this.stock,
    this.soldCount,
    this.favorited = false,
    this.attributes = const [],
    this.variants = const [],
    this.selectedVariantId,
    this.category,
    this.delivery,
    this.reviews = const ReviewSummary(),
    this.related = const [],
    this.url,
  });

  factory ProductDetail.fromJson(Map<String, dynamic> json) {
    final delivery = json['delivery'];

    return ProductDetail(
      id: jsonInt(json['id']),
      name: '${json['name'] ?? ''}',
      url: jsonString(json['url']),
      summary: jsonString(json['summary']),
      description: jsonString(json['description']),
      images: [for (final image in (json['images'] is List ? json['images'] as List : const [])) if (image is String) image],
      price: Money.fromJson(json['price']),
      comparePrice: Money.maybe(json['compare_price']),
      discountPercent: jsonIntOrNull(json['discount_percent']),
      flash: FlashInfo.maybe(json['flash']),
      inStock: json['in_stock'] != false,
      stock: jsonIntOrNull(json['stock']),
      soldCount: jsonIntOrNull(json['sold_count']),
      favorited: json['favorited'] == true,
      attributes: [
        for (final a in jsonList(json['attributes']))
          ProductAttribute(
            id: jsonInt(a['id']),
            name: '${a['name'] ?? ''}',
            swatch: a['swatch'] == true,
            options: [for (final o in jsonList(a['options'])) AttributeOption(id: jsonInt(o['id']), name: '${o['name'] ?? ''}', swatch: jsonString(o['swatch']))],
          ),
      ],
      variants: [
        for (final v in jsonList(json['variants']))
          Variant(
            id: jsonInt(v['id']),
            label: '${v['label'] ?? ''}',
            options: {for (final e in jsonMap(v['options']).entries) int.tryParse(e.key) ?? 0: jsonInt(e.value)},
            price: Money.fromJson(v['price']),
            comparePrice: Money.maybe(v['compare_price']),
            discountPercent: jsonIntOrNull(v['discount_percent']),
            inStock: v['in_stock'] != false,
            stock: jsonIntOrNull(v['stock']),
            image: jsonString(v['image']),
          ),
      ],
      selectedVariantId: jsonIntOrNull(json['selected_variant_id']),
      category: json['category'] is Map<String, dynamic> ? Category.fromJson(json['category'] as Map<String, dynamic>) : null,
      delivery: delivery is Map<String, dynamic>
          ? DeliveryEstimate(
              label: '${delivery['label'] ?? ''}',
              company: jsonString(delivery['company']),
              cutoffMinutes: jsonIntOrNull(delivery['cutoff_minutes']),
              location: jsonString(delivery['location']),
            )
          : null,
      reviews: ReviewSummary.fromJson(json['reviews']),
      related: ProductCard.list(json['related']),
    );
  }

  final int id;
  final String name;
  final String? url;
  final String? summary;
  final String? description;
  final List<String> images;
  final Money price;
  final Money? comparePrice;
  final int? discountPercent;
  final FlashInfo? flash;
  final bool inStock;
  final int? stock;
  final int? soldCount;
  final bool favorited;
  final List<ProductAttribute> attributes;
  final List<Variant> variants;
  final int? selectedVariantId;
  final Category? category;
  final DeliveryEstimate? delivery;
  final ReviewSummary reviews;
  final List<ProductCard> related;

  Variant? variantById(int? id) {
    for (final variant in variants) {
      if (variant.id == id) {
        return variant;
      }
    }

    return null;
  }

  /// The variant whose options equal [picked] (attributeId → optionId), if any.
  Variant? variantFor(Map<int, int> picked) {
    for (final variant in variants) {
      if (variant.options.length == picked.length && variant.options.entries.every((e) => picked[e.key] == e.value)) {
        return variant;
      }
    }

    return null;
  }
}

class Review {
  const Review({required this.id, required this.author, required this.rating, required this.body, this.title, this.createdHuman, this.verified = false});

  factory Review.fromJson(Map<String, dynamic> json) => Review(
        id: jsonInt(json['id']),
        author: '${json['author'] ?? ''}',
        rating: jsonInt(json['rating']),
        title: jsonString(json['title']),
        body: '${json['body'] ?? ''}',
        createdHuman: jsonString(json['created_human']),
        verified: json['verified_purchase'] == true,
      );

  final int id;
  final String author;
  final int rating;
  final String? title;
  final String body;
  final String? createdHuman;
  final bool verified;
}

class ReviewPage {
  const ReviewPage({required this.summary, required this.items, required this.meta, required this.canWrite, this.blockReason});

  final ReviewSummary summary;
  final List<Review> items;
  final PageMeta meta;
  final bool canWrite;
  final String? blockReason;
}

class Suggestions {
  const Suggestions({this.products = const [], this.categories = const []});

  final List<ProductCard> products;
  final List<Category> categories;
}

class ImageSearchResult {
  const ImageSearchResult({required this.title, required this.products});

  final String title;
  final List<ProductCard> products;
}

class StorePage {
  const StorePage({required this.slug, required this.title, this.group, this.body});

  factory StorePage.fromJson(Map<String, dynamic> json) =>
      StorePage(slug: '${json['slug'] ?? ''}', title: '${json['title'] ?? ''}', group: jsonString(json['group']), body: jsonString(json['body']));

  final String slug;
  final String title;
  final String? group;
  final String? body;
}
