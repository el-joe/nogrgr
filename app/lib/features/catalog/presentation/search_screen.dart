import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../data/catalog_models.dart';
import '../data/catalog_repository.dart';
import 'product_list_screen.dart';

/// Recent search terms on this device (per store), newest first, max 8.
class RecentSearches extends Notifier<List<String>> {
  String get _key => 'nogrgr.${ref.read(tenantConfigProvider).key}.recent_searches';

  @override
  List<String> build() => ref.watch(sharedPreferencesProvider).getStringList(_key) ?? const [];

  Future<void> add(String term) async {
    state = [term, ...state.where((t) => t.toLowerCase() != term.toLowerCase())].take(8).toList();
    await ref.read(sharedPreferencesProvider).setStringList(_key, state);
  }

  Future<void> clear() async {
    state = const [];
    await ref.read(sharedPreferencesProvider).remove(_key);
  }
}

final recentSearchesProvider = NotifierProvider<RecentSearches, List<String>>(RecentSearches.new);

/// Search (APP-008): field with autocomplete from 2 characters (products + categories),
/// recent searches, results grid with filters / sort, no results + popular products,
/// camera entry for image search.
class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key, this.initial});

  final String? initial;

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  late final _controller = TextEditingController(text: widget.initial ?? '');
  final _focus = FocusNode();
  Timer? _debounce;
  String? _submitted;
  Suggestions _suggestions = const Suggestions();
  bool _suggesting = false;
  int _request = 0;

  @override
  void initState() {
    super.initState();
    _submitted = widget.initial;

    if (widget.initial == null) {
      _focus.requestFocus();
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _changed(String value) {
    _debounce?.cancel();
    setState(() => _submitted = null);

    if (value.trim().length < 2) {
      setState(() => _suggestions = const Suggestions());

      return;
    }

    _debounce = Timer(const Duration(milliseconds: 300), () async {
      final ticket = ++_request;
      setState(() => _suggesting = true);

      try {
        final result = await ref.read(catalogRepositoryProvider).autocomplete(value.trim());

        if (mounted && ticket == _request) {
          setState(() => _suggestions = result);
        }
      } catch (_) {
        // Suggestions are optional; Enter still searches.
      } finally {
        if (mounted && ticket == _request) {
          setState(() => _suggesting = false);
        }
      }
    });
  }

  void _submit(String value) {
    final term = value.trim();

    if (term.isEmpty) {
      return;
    }

    _debounce?.cancel();
    _focus.unfocus();
    ref.read(recentSearchesProvider.notifier).add(term);
    setState(() => _submitted = term);
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final recent = ref.watch(recentSearchesProvider);

    final field = Padding(
      padding: const EdgeInsets.fromLTRB(8, 8, 16, 8),
      child: Row(
        children: [
          IconButton(
            tooltip: texts.t('app.auth.back'),
            onPressed: () => context.canPop() ? context.pop() : context.go(Routes.home),
            icon: const AppIcon('chevron_left', mirrorInRtl: true),
          ),
          Expanded(
            child: TextField(
              key: const ValueKey('search-field'),
              controller: _controller,
              focusNode: _focus,
              textInputAction: TextInputAction.search,
              onChanged: _changed,
              onSubmitted: _submit,
              decoration: InputDecoration(
                isDense: true,
                hintText: texts.t('app.search.placeholder'),
                filled: true,
                fillColor: colors.surface,
                prefixIcon: Padding(padding: const EdgeInsets.all(10), child: AppIcon('search', size: 20, color: colors.muted)),
                suffixIcon: IconButton(
                  tooltip: texts.t('search.image.open'),
                  onPressed: () => context.push(Routes.imageSearch),
                  icon: AppIcon('camera', size: 20, color: colors.primary),
                ),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(AppRadius.input), borderSide: BorderSide(color: colors.border)),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(AppRadius.input), borderSide: BorderSide(color: colors.border)),
              ),
            ),
          ),
        ],
      ),
    );

    Widget body;

    if (_submitted != null) {
      body = ListingBody(
        key: ValueKey('results-$_submitted'),
        source: ListingSource(term: _submitted),
      );
    } else if (_controller.text.trim().length >= 2) {
      body = ListView(
        padding: const EdgeInsets.symmetric(vertical: 8),
        children: [
          if (_suggesting) const LinearProgressIndicator(minHeight: 2),
          ListTile(
            leading: AppIcon('search', color: colors.primary),
            title: Text(texts.t('search.see_all')),
            subtitle: Text(_controller.text.trim()),
            onTap: () => _submit(_controller.text),
          ),
          if (_suggestions.categories.isNotEmpty) ...[
            Padding(padding: const EdgeInsets.fromLTRB(16, 12, 16, 4), child: Text(texts.t('search.categories'), style: text.labelMedium?.copyWith(color: colors.muted))),
            for (final category in _suggestions.categories)
              ListTile(
                leading: const AppIcon('grid'),
                title: Text(category.name),
                onTap: () => context.push(Routes.listing(category: category.slug, title: category.name)),
              ),
          ],
          if (_suggestions.products.isNotEmpty) ...[
            Padding(padding: const EdgeInsets.fromLTRB(16, 12, 16, 4), child: Text(texts.t('search.products'), style: text.labelMedium?.copyWith(color: colors.muted))),
            for (final product in _suggestions.products)
              ListTile(
                key: ValueKey('suggestion-${product.id}'),
                leading: SizedBox(width: 44, height: 44, child: NetImage(product.image, radius: 6)),
                title: Text(product.name, maxLines: 1, overflow: TextOverflow.ellipsis),
                subtitle: Text(product.price.label, style: text.bodyMedium?.copyWith(color: colors.primary)),
                onTap: () => context.push(Routes.product(product.id)),
              ),
          ],
          if (!_suggesting && _suggestions.products.isEmpty && _suggestions.categories.isEmpty)
            Padding(padding: const EdgeInsets.all(16), child: Text(texts.t('search.no_suggestions'), style: text.bodyMedium)),
        ],
      );
    } else if (recent.isNotEmpty) {
      body = ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(child: Text(texts.t('search.recent'), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w600))),
              AppLink(label: texts.t('search.clear_recent'), muted: true, onTap: () => ref.read(recentSearchesProvider.notifier).clear()),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final term in recent)
                ActionChip(
                  label: Text(term),
                  onPressed: () {
                    _controller.text = term;
                    _submit(term);
                  },
                ),
            ],
          ),
        ],
      );
    } else {
      body = AsyncStateView.empty(icon: 'search', title: texts.t('search.title'), body: texts.t('app.search.intro'));
    }

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(children: [field, Expanded(child: body)]),
      ),
    );
  }
}
