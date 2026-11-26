import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../auth/data/session_controller.dart';
import '../../auth/presentation/auth_form.dart';
import '../data/wishlist_repository.dart';

/// Wishlist tab (APP-019): guest prompt, loading skeleton, error + retry, empty state,
/// paged list with pull to refresh and remove.
class WishlistScreen extends ConsumerWidget {
  const WishlistScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final signedIn = ref.watch(sessionProvider.select((s) => s.signedIn));
    final wishlist = ref.watch(wishlistProvider);

    Widget body;

    if (!signedIn) {
      body = StateView(
        icon: 'star',
        tone: StateTone.primary,
        title: texts.t('app.wishlist.title'),
        body: texts.t('app.wishlist.sign_in'),
        actionLabel: texts.t('app.account.sign_in_register'),
        onAction: () => context.push(Routes.login),
      );
    } else {
      body = switch (wishlist) {
        AsyncData(:final value) when value.items.isEmpty => RefreshIndicator(
            onRefresh: () => ref.read(wishlistProvider.notifier).refresh(),
            child: LayoutBuilder(
              builder: (context, constraints) => SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: SizedBox(
                  height: constraints.maxHeight,
                  child: StateView(
                    icon: 'star',
                    tone: StateTone.primary,
                    title: texts.t('app.wishlist.empty_title'),
                    body: texts.t('app.wishlist.empty_body'),
                  ),
                ),
              ),
            ),
          ),
        AsyncData(:final value) => _WishlistList(state: value),
        AsyncError(:final error) => StateView(
            icon: error is ApiException && error.isNetwork ? 'globe' : 'alert_triangle',
            tone: error is ApiException && error.isNetwork ? StateTone.warning : StateTone.danger,
            title: error is ApiException && error.isNetwork ? texts.t('app.state.offline_title') : texts.t('app.state.error_title'),
            body: error is ApiException ? apiErrorMessage(texts, error) : texts.t('mobile.errors.generic'),
            actionLabel: texts.t('mobile.splash.retry'),
            onAction: () => ref.invalidate(wishlistProvider),
          ),
        _ => const _WishlistSkeleton(),
      };
    }

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppTopBar(title: texts.t('app.wishlist.title')),
            Expanded(child: body),
          ],
        ),
      ),
    );
  }
}

class _WishlistList extends ConsumerWidget {
  const _WishlistList({required this.state});

  final WishlistState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifier = ref.read(wishlistProvider.notifier);

    return RefreshIndicator(
      onRefresh: notifier.refresh,
      child: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          if (notification.metrics.extentAfter < 300) {
            notifier.loadMore();
          }

          return false;
        },
        child: ListView.separated(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
          itemCount: state.items.length + (state.loadingMore ? 1 : 0),
          separatorBuilder: (_, _) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            if (index >= state.items.length) {
              return const Center(child: Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator()));
            }

            return _WishlistTile(product: state.items[index]);
          },
        ),
      ),
    );
  }
}

class _WishlistTile extends ConsumerWidget {
  const _WishlistTile({required this.product});

  final ProductSummary product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        border: Border.all(color: colors.border),
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: Container(
              width: 84,
              height: 84,
              color: colors.bg,
              child: product.image == null
                  ? AppIcon('store', color: colors.muted)
                  : Image.network(
                      product.image!,
                      fit: BoxFit.contain,
                      errorBuilder: (_, _, _) => Center(child: AppIcon('store', color: colors.muted)),
                    ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(product.name, style: text.bodyLarge, maxLines: 2, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text(product.price.label, style: text.titleMedium?.copyWith(color: colors.primary)),
                    if (product.comparePrice != null)
                      Text(
                        product.comparePrice!.label,
                        style: text.bodyMedium?.copyWith(decoration: TextDecoration.lineThrough),
                      ),
                  ],
                ),
                if (!product.inStock) ...[
                  const SizedBox(height: 4),
                  Text(texts.t('app.wishlist.out_of_stock'), style: text.labelSmall?.copyWith(color: colors.danger)),
                ],
              ],
            ),
          ),
          IconButton(
            tooltip: texts.t('app.wishlist.remove', {'name': product.name}),
            icon: AppIcon('star', color: colors.primary),
            onPressed: () async {
              try {
                await ref.read(wishlistProvider.notifier).remove(product);

                if (context.mounted) {
                  showAppMessage(context, texts.t('app.wishlist.removed'));
                }
              } on ApiException catch (error) {
                if (context.mounted) {
                  showAppMessage(context, apiErrorMessage(texts, error));
                }
              }
            },
          ),
        ],
      ),
    );
  }
}

class _WishlistSkeleton extends StatelessWidget {
  const _WishlistSkeleton();

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
      itemCount: 4,
      physics: const NeverScrollableScrollPhysics(),
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (_, _) => Container(
        height: 106,
        decoration: BoxDecoration(color: colors.border.withValues(alpha: 0.45), borderRadius: BorderRadius.circular(AppRadius.card)),
      ),
    );
  }
}
