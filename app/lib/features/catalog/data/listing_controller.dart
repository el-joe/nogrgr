import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/product_card.dart';
import '../../../core/providers.dart';
import 'catalog_models.dart';
import 'catalog_repository.dart';

class ListingState {
  const ListingState({
    required this.items,
    required this.page,
    required this.hasMore,
    required this.total,
    required this.facets,
    required this.sorts,
    this.sort,
    this.filters = const ListingFilters(),
    this.loadingMore = false,
    this.popular = const [],
    this.reloading = false,
  });

  final List<ProductCard> items;
  final int page;
  final bool hasMore;
  final int total;
  final Facets facets;
  final List<String> sorts;
  final String? sort;
  final ListingFilters filters;
  final bool loadingMore;
  final List<ProductCard> popular;

  /// Filters / sort changed; the old page stays visible until the new one answers.
  final bool reloading;

  ListingState copyWith({List<ProductCard>? items, int? page, bool? hasMore, bool? loadingMore, bool? reloading}) => ListingState(
        items: items ?? this.items,
        page: page ?? this.page,
        hasMore: hasMore ?? this.hasMore,
        total: total,
        facets: facets,
        sorts: sorts,
        sort: sort,
        filters: filters,
        loadingMore: loadingMore ?? this.loadingMore,
        popular: popular,
        reloading: reloading ?? this.reloading,
      );
}

/// One product list (APP-006 / 007 / 008): kind, category or search term; filters, sort
/// and infinite scroll. Facets and sorts come from the API.
class ListingController extends AsyncNotifier<ListingState> {
  ListingController(this.source);

  final ListingSource source;
  ListingFilters _filters = const ListingFilters();
  String? _sort;

  @override
  Future<ListingState> build() async {
    ref.watch(localeCodeProvider);
    ref.watch(preferencesProvider.select((p) => (p.currency, p.country)));

    return _load(1);
  }

  Future<ListingState> _load(int page, {List<ProductCard> before = const []}) async {
    final result = await ref.read(catalogRepositoryProvider).listing(source, filters: _filters, sort: _sort, page: page);

    return ListingState(
      items: [...before, ...result.items],
      page: result.meta.currentPage,
      hasMore: result.meta.hasMore,
      total: result.meta.total,
      facets: result.facets,
      sorts: result.sorts,
      sort: result.sort ?? _sort,
      filters: _filters,
      popular: result.popular,
    );
  }

  Future<void> apply({ListingFilters? filters, String? sort}) async {
    _filters = filters ?? _filters;
    _sort = sort ?? _sort;
    final current = state.value;

    if (current != null) {
      state = AsyncData(current.copyWith(reloading: true));
    }

    state = await AsyncValue.guard(() => _load(1));
  }

  Future<void> loadMore() async {
    final current = state.value;

    if (current == null || !current.hasMore || current.loadingMore || state.isLoading) {
      return;
    }

    state = AsyncData(current.copyWith(loadingMore: true));

    try {
      state = AsyncData(await _load(current.page + 1, before: current.items));
    } catch (_) {
      state = AsyncData(current.copyWith(loadingMore: false));
    }
  }
}

final listingProvider = AsyncNotifierProvider.autoDispose.family<ListingController, ListingState, ListingSource>(ListingController.new);
