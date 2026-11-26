import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../auth/data/session_controller.dart';

/// Money of the API: USD cents + the display conversion (decision #87, display only).
class Money {
  const Money({required this.amount, required this.formatted, this.displayFormatted});

  factory Money.fromJson(Map<String, dynamic> json) {
    final display = json['display'];

    return Money(
      amount: json['amount'] as int? ?? 0,
      formatted: '${json['formatted'] ?? ''}',
      displayFormatted: display is Map<String, dynamic> ? display['formatted'] as String? : null,
    );
  }

  final int amount;
  final String formatted;
  final String? displayFormatted;

  /// What the customer sees: the display currency when converted, else USD.
  String get label => displayFormatted ?? formatted;
}

/// Product card of `GET favorites` (ProductSummaryResource).
class ProductSummary {
  const ProductSummary({
    required this.id,
    required this.name,
    required this.slug,
    required this.price,
    required this.inStock,
    this.image,
    this.comparePrice,
    this.discountPercent,
  });

  factory ProductSummary.fromJson(Map<String, dynamic> json) => ProductSummary(
        id: json['id'] as int,
        name: '${json['name'] ?? ''}',
        slug: '${json['slug'] ?? ''}',
        image: json['image'] as String?,
        price: Money.fromJson(json['price'] as Map<String, dynamic>? ?? const {}),
        comparePrice: json['compare_price'] is Map<String, dynamic> ? Money.fromJson(json['compare_price'] as Map<String, dynamic>) : null,
        discountPercent: json['discount_percent'] as int?,
        inStock: json['in_stock'] != false,
      );

  final int id;
  final String name;
  final String slug;
  final String? image;
  final Money price;
  final Money? comparePrice;
  final int? discountPercent;
  final bool inStock;
}

class WishlistPage {
  const WishlistPage({required this.items, required this.ids, required this.currentPage, required this.lastPage});

  final List<ProductSummary> items;
  final Set<int> ids;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

class WishlistRepository {
  WishlistRepository(this._api);

  final ApiClient _api;

  Future<WishlistPage> page(int page) async {
    final data = (await _api.get('favorites', query: {'page': page, 'per_page': 20})).map;
    final meta = data['meta'] as Map<String, dynamic>? ?? const {};

    return WishlistPage(
      items: (data['items'] as List? ?? const []).whereType<Map<String, dynamic>>().map(ProductSummary.fromJson).toList(),
      ids: (data['ids'] as List? ?? const []).map((id) => id as int).toSet(),
      currentPage: meta['current_page'] as int? ?? page,
      lastPage: meta['last_page'] as int? ?? page,
    );
  }

  Future<void> add(int storeProductId) => _api.post('favorites', body: {'store_product_id': storeProductId});

  Future<void> remove(int storeProductId) => _api.delete('favorites/$storeProductId');
}

final wishlistRepositoryProvider = Provider<WishlistRepository>((ref) => WishlistRepository(ref.watch(apiClientProvider)));

class WishlistState {
  const WishlistState({this.items = const [], this.ids = const {}, this.page = 0, this.hasMore = false, this.loadingMore = false});

  final List<ProductSummary> items;
  final Set<int> ids;
  final int page;
  final bool hasMore;
  final bool loadingMore;

  WishlistState copyWith({List<ProductSummary>? items, Set<int>? ids, int? page, bool? hasMore, bool? loadingMore}) =>
      WishlistState(
        items: items ?? this.items,
        ids: ids ?? this.ids,
        page: page ?? this.page,
        hasMore: hasMore ?? this.hasMore,
        loadingMore: loadingMore ?? this.loadingMore,
      );
}

/// The signed-in customer's favorites (APP-019): paged, pull to refresh, optimistic remove.
class WishlistController extends AsyncNotifier<WishlistState> {
  @override
  Future<WishlistState> build() async {
    final signedIn = ref.watch(sessionProvider.select((s) => s.signedIn));

    if (!signedIn) {
      return const WishlistState();
    }

    final first = await ref.read(wishlistRepositoryProvider).page(1);

    return WishlistState(items: first.items, ids: first.ids, page: 1, hasMore: first.hasMore);
  }

  Future<void> refresh() async {
    ref.invalidateSelf();
    await future;
  }

  Future<void> loadMore() async {
    final current = state.value;

    if (current == null || !current.hasMore || current.loadingMore) {
      return;
    }

    state = AsyncData(current.copyWith(loadingMore: true));
    final next = await ref.read(wishlistRepositoryProvider).page(current.page + 1);
    state = AsyncData(current.copyWith(
      items: [...current.items, ...next.items],
      ids: next.ids,
      page: next.currentPage,
      hasMore: next.hasMore,
      loadingMore: false,
    ));
  }

  /// Removes at once, restores the row when the API refuses.
  Future<void> remove(ProductSummary product) async {
    final current = state.value;

    if (current == null) {
      return;
    }

    state = AsyncData(current.copyWith(
      items: current.items.where((item) => item.id != product.id).toList(),
      ids: {...current.ids}..remove(product.id),
    ));

    try {
      await ref.read(wishlistRepositoryProvider).remove(product.id);
    } catch (_) {
      state = AsyncData(current);
      rethrow;
    }
  }
}

final wishlistProvider = AsyncNotifierProvider<WishlistController, WishlistState>(WishlistController.new);
