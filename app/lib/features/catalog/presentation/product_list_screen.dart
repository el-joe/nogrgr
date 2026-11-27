import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/i18n/app_texts.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../../core/widgets/paged_list.dart';
import '../../../core/widgets/select_field.dart';
import '../data/catalog_models.dart';
import '../data/listing_controller.dart';

/// Category products / product lists (APP-006, APP-007): grid with infinite scroll,
/// filter sheet (facets of the API), sort sheet, empty states per list.
class ProductListScreen extends ConsumerWidget {
  const ProductListScreen({super.key, required this.source});

  final ListingSource source;

  String _title(AppTexts texts) =>
      source.title ??
      switch (source.kind) {
        'new_in' => texts.t('catalog.listing.new_in.title'),
        'best_selling' => texts.t('catalog.listing.best_selling.title'),
        'top_rated' => texts.t('catalog.listing.top_rated.title'),
        'offers' => texts.t('catalog.listing.offers.title'),
        _ => texts.t('catalog.listing.category.title'),
      };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);

    return PageScaffold(
      title: _title(texts),
      trailing: IconButton(tooltip: texts.t('search.title'), onPressed: () => context.push(Routes.search), icon: const AppIcon('search')),
      body: ListingBody(source: source),
    );
  }
}

/// Toolbar + grid + states of a listing (also used by search results).
class ListingBody extends ConsumerWidget {
  const ListingBody({super.key, required this.source, this.header});

  final ListingSource source;
  final Widget? header;

  (String, String) _empty(AppTexts texts, bool filtered) {
    if (filtered) {
      return (texts.t('catalog.empty.filtered_title'), texts.t('catalog.empty.filtered_body'));
    }

    if (source.isSearch) {
      return (texts.t('catalog.empty.search_title', {'term': source.term}), texts.t('catalog.empty.search_body'));
    }

    final kind = source.kind ?? 'category';

    return (texts.t('catalog.empty.${kind}_title'), texts.t('catalog.empty.${kind}_body'));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final provider = listingProvider(source);
    final listing = ref.watch(provider);
    final notifier = ref.read(provider.notifier);
    final current = listing.value;

    if (current == null) {
      return switch (listing) {
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(provider)),
        _ => const GridSkeleton(),
      };
    }

    final filtered = current.filters.count > 0;
    final (emptyTitle, emptyBody) = _empty(texts, filtered);

    return PagedScroll(
      onRefresh: () async => ref.invalidate(provider),
      onLoadMore: notifier.loadMore,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          if (header != null) SliverToBoxAdapter(child: header),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  AppChip(
                    key: const ValueKey('listing-filters'),
                    label: filtered ? '${texts.t('catalog.filters.title')} (${current.filters.count})' : texts.t('catalog.filters.title'),
                    selected: filtered,
                    onTap: () => showFilterSheet(context, ref, source, current),
                  ),
                  if (current.sorts.length > 1)
                    AppChip(
                      key: const ValueKey('listing-sort'),
                      label: current.sort == null ? texts.t('catalog.sort.label') : texts.t('catalog.sort.${current.sort}'),
                      selected: false,
                      onTap: () => _sortSheet(context, ref, current),
                    ),
                  if (current.reloading) const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                ],
              ),
            ),
          ),
          if (current.items.isEmpty) ...[
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: AsyncStateView.empty(
                  icon: source.isSearch ? 'search' : 'store',
                  title: emptyTitle,
                  body: emptyBody,
                  actionLabel: filtered ? texts.t('catalog.filters.reset') : null,
                  onRetry: filtered ? () => notifier.apply(filters: const ListingFilters()) : null,
                ),
              ),
            ),
            if (current.popular.isNotEmpty) ...[
              SliverToBoxAdapter(child: SectionHeader(title: texts.t('search.popular'))),
              ProductGridSliver(products: current.popular),
            ],
          ] else
            ProductGridSliver(products: current.items),
          if (current.loadingMore) const SliverToBoxAdapter(child: LoadingMore()),
          const SliverToBoxAdapter(child: SizedBox(height: 24)),
        ],
      ),
    );
  }

  Future<void> _sortSheet(BuildContext context, WidgetRef ref, ListingState state) async {
    final texts = ref.read(textsProvider);
    final picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 8), child: Text(texts.t('catalog.sort.label'), style: Theme.of(sheetContext).textTheme.titleLarge)),
            for (final sort in state.sorts)
              ListTile(
                key: ValueKey('sort-$sort'),
                title: Text(texts.t('catalog.sort.$sort')),
                trailing: sort == state.sort ? Icon(Icons.check_rounded, color: sheetContext.colors.primary) : null,
                onTap: () => Navigator.of(sheetContext).pop(sort),
              ),
          ],
        ),
      ),
    );

    if (picked != null && picked != state.sort) {
      await ref.read(listingProvider(source).notifier).apply(sort: picked);
    }
  }
}

/// Filter sheet: price buckets, rating, availability, discount, flash, new in, category
/// and option facets. Applies on "Show results".
Future<void> showFilterSheet(BuildContext context, WidgetRef ref, ListingSource source, ListingState state) async {
  final result = await showModalBottomSheet<ListingFilters>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => FractionallySizedBox(heightFactor: 0.85, child: _FilterSheet(state: state, source: source)),
  );

  if (result != null) {
    await ref.read(listingProvider(source).notifier).apply(filters: result);
  }
}

class _FilterSheet extends ConsumerStatefulWidget {
  const _FilterSheet({required this.state, required this.source});

  final ListingState state;
  final ListingSource source;

  @override
  ConsumerState<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends ConsumerState<_FilterSheet> {
  late ListingFilters _f = widget.state.filters;

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final facets = widget.state.facets;
    final text = Theme.of(context).textTheme;

    Widget title(String label) => Padding(padding: const EdgeInsets.only(top: 18, bottom: 10), child: Text(label, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w600)));
    Widget chips(List<Widget> children) => Wrap(spacing: 8, runSpacing: 8, children: children);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: [
              Expanded(child: Text(texts.t('catalog.filters.title'), style: text.titleLarge)),
              AppLink(label: texts.t('catalog.filters.reset'), onTap: () => setState(() => _f = const ListingFilters())),
            ],
          ),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            children: [
              if (facets.price.isNotEmpty) ...[
                title(texts.t('catalog.filters.price')),
                chips([
                  for (final bucket in facets.price)
                    AppChip(
                      label: '${bucket.label} (${bucket.count})',
                      selected: _f.priceRange == bucket.value,
                      onTap: () => setState(() => _f = _f.copyWith(priceRange: () => _f.priceRange == bucket.value ? null : bucket.value)),
                    ),
                ]),
              ],
              title(texts.t('catalog.filters.rating')),
              chips([
                for (final stars in [4, 3, 2, 1])
                  AppChip(
                    label: '$stars★ ${texts.t('catalog.filters.and_up')}',
                    selected: _f.rating == stars,
                    onTap: () => setState(() => _f = _f.copyWith(rating: () => _f.rating == stars ? null : stars)),
                  ),
                AppChip(label: texts.t('catalog.filters.five_only'), selected: _f.rating == 5, onTap: () => setState(() => _f = _f.copyWith(rating: () => _f.rating == 5 ? null : 5))),
              ]),
              title(texts.t('catalog.filters.availability')),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(texts.t('catalog.filters.in_stock')),
                value: _f.inStock,
                onChanged: (value) => setState(() => _f = _f.copyWith(inStock: value)),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(texts.t('catalog.filters.flash_only')),
                value: _f.flash,
                onChanged: (value) => setState(() => _f = _f.copyWith(flash: value)),
              ),
              title(texts.t('catalog.filters.discount')),
              chips([
                for (final percent in [10, 20, 30, 50])
                  AppChip(
                    label: texts.t('catalog.filters.discount_min', {'percent': percent}),
                    selected: _f.discount == percent,
                    onTap: () => setState(() => _f = _f.copyWith(discount: () => _f.discount == percent ? null : percent)),
                  ),
              ]),
              if (widget.source.kind == 'new_in') ...[
                title(texts.t('catalog.filters.added')),
                chips([
                  for (final days in [7, 30])
                    AppChip(
                      label: texts.t('catalog.filters.added_$days'),
                      selected: _f.added == days,
                      onTap: () => setState(() => _f = _f.copyWith(added: () => _f.added == days ? null : days)),
                    ),
                ]),
              ],
              if (facets.categories.isNotEmpty) ...[
                title(texts.t('catalog.filters.category')),
                chips([
                  for (final category in facets.categories)
                    AppChip(
                      label: '${category.label} (${category.count})',
                      selected: _f.categories.contains(int.tryParse(category.value)),
                      onTap: () {
                        final id = int.tryParse(category.value);

                        if (id == null) {
                          return;
                        }

                        final next = {..._f.categories};
                        next.contains(id) ? next.remove(id) : next.add(id);
                        setState(() => _f = _f.copyWith(categories: next));
                      },
                    ),
                ]),
              ],
              for (final option in facets.options) ...[
                title(option.name),
                chips([
                  for (final value in option.values)
                    AppChip(
                      label: '${value.label} (${value.count})',
                      selected: _f.options[option.id]?.contains(value.value) ?? false,
                      onTap: () {
                        final next = {for (final e in _f.options.entries) e.key: {...e.value}};
                        final set = next.putIfAbsent(option.id, () => <String>{});
                        set.contains(value.value) ? set.remove(value.value) : set.add(value.value);
                        setState(() => _f = _f.copyWith(options: next));
                      },
                    ),
                ]),
              ],
            ],
          ),
        ),
        BottomActionBar(
          child: AppButton(
            key: const ValueKey('filters-apply'),
            label: texts.t('app.catalog.apply_filters'),
            onPressed: () => Navigator.of(context).pop(_f),
          ),
        ),
      ],
    );
  }
}
