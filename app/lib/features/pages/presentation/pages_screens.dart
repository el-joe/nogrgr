import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/providers.dart';
import '../../../core/url_opener.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../account/presentation/account_screen.dart';
import '../../catalog/data/catalog_models.dart';
import '../../catalog/data/catalog_repository.dart';

final storePagesProvider = FutureProvider<List<StorePage>>((ref) {
  ref.watch(localeCodeProvider);

  return ref.watch(catalogRepositoryProvider).pages();
});

final storePageProvider = FutureProvider.autoDispose.family<StorePage, String>((ref, slug) {
  ref.watch(localeCodeProvider);

  return ref.watch(catalogRepositoryProvider).page(slug);
});

/// Store pages (APP-031): published CMS pages (about, terms, privacy, FAQ …).
class PagesScreen extends ConsumerWidget {
  const PagesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final pages = ref.watch(storePagesProvider);

    return PageScaffold(
      title: texts.t('pages.menu_label'),
      body: switch (pages) {
        AsyncData(:final value) when value.isEmpty => AsyncStateView.empty(icon: 'file', title: texts.t('app.pages.empty')),
        AsyncData(:final value) => ListView(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            children: [
              for (final page in value) AccountRow(key: ValueKey('page-${page.slug}'), icon: 'file', label: page.title, onTap: () => context.push(Routes.page(page.slug))),
            ],
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(storePagesProvider)),
        _ => const ListSkeleton(height: 52, count: 6),
      },
    );
  }
}

class StorePageScreen extends ConsumerWidget {
  const StorePageScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final page = ref.watch(storePageProvider(slug));

    return PageScaffold(
      title: page.value?.title ?? '',
      body: switch (page) {
        AsyncData(:final value) => SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: HtmlWidget(
              value.body ?? '',
              textStyle: Theme.of(context).textTheme.bodyLarge,
              onTapUrl: (url) async {
                final uri = Uri.tryParse(url);
                final segments = uri?.pathSegments ?? const [];

                if (segments.length == 2 && segments.first == 'pages') {
                  context.push(Routes.page(segments[1]));

                  return true;
                }

                return ref.read(urlOpenerProvider)(url);
              },
            ),
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(storePageProvider(slug))),
        _ => const ListSkeleton(height: 18, count: 10),
      },
    );
  }
}
