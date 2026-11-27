import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../data/catalog_models.dart';
import 'home_screen.dart';

/// Categories tab (APP-005): store category tree — top-level list on the start side,
/// the selected category's children as contained tiles, "all products" of each level.
class CategoriesScreen extends ConsumerStatefulWidget {
  const CategoriesScreen({super.key});

  @override
  ConsumerState<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends ConsumerState<CategoriesScreen> {
  int _selected = 0;

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final tree = ref.watch(homeCategoriesProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    final body = switch (tree) {
      AsyncData(:final value) when value.isEmpty => AsyncStateView.empty(
          icon: 'grid',
          title: texts.t('catalog.categories.empty_title'),
          body: texts.t('catalog.categories.empty_body'),
        ),
      AsyncData(:final value) => Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              width: 104,
              color: colors.surface,
              child: ListView.builder(
                itemCount: value.length,
                itemBuilder: (_, index) {
                  final active = index == _selected.clamp(0, value.length - 1);

                  return InkWell(
                    key: ValueKey('category-side-${value[index].slug}'),
                    onTap: () => setState(() => _selected = index),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 14),
                      decoration: BoxDecoration(
                        color: active ? colors.bg : Colors.transparent,
                        border: BorderDirectional(start: BorderSide(color: active ? colors.primary : Colors.transparent, width: 3)),
                      ),
                      child: Text(
                        value[index].name,
                        style: text.labelMedium?.copyWith(color: active ? colors.primary : colors.ink, fontWeight: active ? FontWeight.w600 : FontWeight.w500),
                      ),
                    ),
                  );
                },
              ),
            ),
            Expanded(child: _Children(category: value[_selected.clamp(0, value.length - 1)])),
          ],
        ),
      AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(homeCategoriesProvider)),
      _ => const ListSkeleton(height: 48, count: 8),
    };

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppTopBar(title: texts.t('catalog.categories.title')),
            const Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 12), child: SearchEntry()),
            Expanded(child: RefreshIndicator(onRefresh: () async => ref.invalidate(homeCategoriesProvider), child: body)),
          ],
        ),
      ),
    );
  }
}

class _Children extends ConsumerWidget {
  const _Children({required this.category});

  final Category category;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final text = Theme.of(context).textTheme;
    final children = category.children;

    return ListView(
      key: ValueKey('category-children-${category.slug}'),
      padding: const EdgeInsets.all(16),
      children: [
        InkWell(
          onTap: () => context.push(Routes.listing(category: category.slug, title: category.name)),
          borderRadius: BorderRadius.circular(AppRadius.card),
          child: SizedBox(height: 96, child: NetImage(category.image, icon: 'grid')),
        ),
        const SizedBox(height: 12),
        Text(category.name, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
        AppLink(label: texts.t('catalog.empty.browse_all'), onTap: () => context.push(Routes.listing(category: category.slug, title: category.name))),
        const SizedBox(height: 12),
        if (children.isNotEmpty)
          Wrap(
            spacing: 12,
            runSpacing: 16,
            children: [
              for (final child in children)
                EntranceFade(
                  child: SizedBox(
                    width: 72,
                    child: CategoryBubble(
                      key: ValueKey('category-${child.slug}'),
                      name: child.name,
                      image: child.image,
                      onTap: () => context.push(Routes.listing(category: child.slug, title: child.name)),
                    ),
                  ),
                ),
            ],
          ),
      ],
    );
  }
}
