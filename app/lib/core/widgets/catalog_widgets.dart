import 'dart:async';

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../features/auth/presentation/auth_form.dart';
import '../../features/wishlist/data/favorites.dart';
import '../models/product_card.dart';
import '../network/api_exception.dart';
import '../providers.dart';
import '../theme/app_tokens.dart';
import 'app_widgets.dart';

extension ImageWellColor on AppColors {
  /// Soft grey well behind contained product images.
  Color get imageWell => Color.alphaBlend(ink.withValues(alpha: 0.05), surface);
}

/// Content image: always `BoxFit.contain` (client rule) on the soft image well.
class NetImage extends StatelessWidget {
  const NetImage(this.url, {super.key, this.radius = AppRadius.card, this.background, this.icon = 'store', this.semanticLabel});

  final String? url;
  final double radius;
  final Color? background;
  final String icon;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final placeholder = Center(child: AppIcon(icon, color: colors.muted));

    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: ColoredBox(
        color: background ?? colors.imageWell,
        child: url == null || url!.isEmpty
            ? placeholder
            : Image.network(
                url!,
                fit: BoxFit.contain,
                width: double.infinity,
                height: double.infinity,
                semanticLabel: semanticLabel,
                errorBuilder: (_, _, _) => placeholder,
                frameBuilder: (context, child, frame, sync) => sync
                    ? child
                    : AnimatedOpacity(opacity: frame == null ? 0 : 1, duration: const Duration(milliseconds: 250), child: child),
              ),
      ),
    );
  }
}

/// Horizontal scrolling that also follows mouse / trackpad drags (rails swipe everywhere).
class DragScrollBehavior extends MaterialScrollBehavior {
  const DragScrollBehavior();

  @override
  Set<PointerDeviceKind> get dragDevices => PointerDeviceKind.values.toSet();
}

/// Snaps a rail to the start of the nearest item ("Rail (swipe / drag, snap 16)").
class SnapScrollPhysics extends ScrollPhysics {
  const SnapScrollPhysics({required this.itemExtent, super.parent});

  final double itemExtent;

  @override
  SnapScrollPhysics applyTo(ScrollPhysics? ancestor) => SnapScrollPhysics(itemExtent: itemExtent, parent: buildParent(ancestor));

  @override
  Simulation? createBallisticSimulation(ScrollMetrics position, double velocity) {
    if ((velocity <= 0 && position.pixels <= position.minScrollExtent) ||
        (velocity >= 0 && position.pixels >= position.maxScrollExtent)) {
      return super.createBallisticSimulation(position, velocity);
    }

    final projected = position.pixels + velocity * 0.35;
    final target = ((projected / itemExtent).round() * itemExtent).clamp(position.minScrollExtent, position.maxScrollExtent);

    if ((target - position.pixels).abs() < 0.5) {
      return null;
    }

    return ScrollSpringSimulation(spring, position.pixels, target, velocity, tolerance: toleranceFor(position));
  }

  @override
  bool get allowImplicitScrolling => false;
}

/// "App/Section header": 17 semibold title, primary "See all", optional trailing (countdown).
class SectionHeader extends ConsumerWidget {
  const SectionHeader({super.key, required this.title, this.onSeeAll, this.trailing});

  final String title;
  final VoidCallback? onSeeAll;
  final Widget? trailing;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = context.colors;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
      child: Row(
        children: [
          Expanded(child: Text(title, style: Theme.of(context).textTheme.titleLarge, maxLines: 1, overflow: TextOverflow.ellipsis)),
          if (onSeeAll != null)
            InkWell(
              onTap: onSeeAll,
              borderRadius: BorderRadius.circular(4),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                child: Text(ref.t('app.common.see_all'), style: Theme.of(context).textTheme.labelMedium?.copyWith(color: colors.primary)),
              ),
            ),
          if (trailing != null) ...[const SizedBox(width: 8), trailing!],
        ],
      ),
    );
  }
}

/// "Countdown (flip each second)": HH : MM : SS boxes in primary, ticking to [endsAt].
class Countdown extends ConsumerStatefulWidget {
  const Countdown({super.key, required this.endsAt, this.onDone});

  final DateTime endsAt;
  final VoidCallback? onDone;

  @override
  ConsumerState<Countdown> createState() => _CountdownState();
}

class _CountdownState extends ConsumerState<Countdown> {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) {
        return;
      }

      setState(() {});

      if (!widget.endsAt.isAfter(DateTime.now())) {
        _timer?.cancel();
        widget.onDone?.call();
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    var left = widget.endsAt.difference(DateTime.now());

    if (left.isNegative) {
      left = Duration.zero;
    }

    // ≥ 24 h: a days segment ("5d" / "5 يوم") before HH:MM:SS, never a clamped hour count.
    final days = left.inDays;
    final parts = [left.inHours % 24, left.inMinutes % 60, left.inSeconds % 60];
    final texts = ref.watch(textsProvider);
    final dayLabel = days > 0 ? texts.t('app.countdown.days', {'count': days}) : null;
    final style = Theme.of(context).textTheme.labelSmall?.copyWith(color: colors.onPrimary, fontWeight: FontWeight.w600);
    final time = parts.map((part) => part.toString().padLeft(2, '0')).join(':');

    Widget segment(String value) => AnimatedSwitcher(
          duration: const Duration(milliseconds: 220),
          transitionBuilder: (child, animation) => FadeTransition(
            opacity: animation,
            child: SlideTransition(position: Tween(begin: const Offset(0, -0.3), end: Offset.zero).animate(animation), child: child),
          ),
          child: Container(
            key: ValueKey(value),
            constraints: const BoxConstraints(minWidth: 24),
            height: 19,
            padding: const EdgeInsets.symmetric(horizontal: 4),
            alignment: Alignment.center,
            decoration: BoxDecoration(color: colors.primary, borderRadius: BorderRadius.circular(4)),
            child: Text(value, style: style),
          ),
        );

    return Semantics(
      label: dayLabel == null ? time : '$dayLabel $time',
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (dayLabel != null) ...[
            KeyedSubtree(key: const ValueKey('countdown-days'), child: segment(dayLabel)),
            const SizedBox(width: 6),
          ],
          // Digits always read HH:MM:SS left to right, also in Arabic.
          Directionality(
            textDirection: TextDirection.ltr,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (var i = 0; i < parts.length; i++) ...[
                  if (i > 0) Padding(padding: const EdgeInsets.symmetric(horizontal: 3), child: Text(':', style: TextStyle(color: colors.primary, fontWeight: FontWeight.w600))),
                  segment(parts[i].toString().padLeft(2, '0')),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Star + "4.6 (128)".
class RatingLine extends StatelessWidget {
  const RatingLine({super.key, required this.rating, required this.count});

  final double rating;
  final int count;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.star_rounded, size: 14, color: Color(0xFFF5A623)),
        const SizedBox(width: 2),
        Text('${rating.toStringAsFixed(1)} ($count)', style: text.labelSmall?.copyWith(color: context.colors.muted, fontWeight: FontWeight.w400)),
      ],
    );
  }
}

/// Price in primary + struck-through compare price.
class PriceLine extends StatelessWidget {
  const PriceLine({super.key, required this.price, this.compare, this.large = false});

  final Money price;
  final Money? compare;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return Wrap(
      spacing: 6,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(price.label, style: (large ? text.headlineSmall : text.titleMedium)?.copyWith(color: colors.primary, fontWeight: FontWeight.w600)),
        if (compare != null)
          Text(compare!.label, style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400, decoration: TextDecoration.lineThrough)),
      ],
    );
  }
}

/// Favourite star toggle: signs guests in first, optimistic for customers.
class FavoriteButton extends ConsumerWidget {
  const FavoriteButton({super.key, required this.productId, this.initial = false, this.size = 32});

  final int productId;
  final bool initial;
  final double size;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = context.colors;
    final texts = ref.watch(textsProvider);
    final favorited = ref.watch(favoriteIdsProvider.select((ids) => ids?.contains(productId))) ?? initial;

    return Semantics(
      button: true,
      toggled: favorited,
      label: texts.t('product.favorite'),
      excludeSemantics: true,
      child: Material(
        color: colors.surface,
        shape: const CircleBorder(),
        elevation: 0,
        child: InkResponse(
          radius: size / 2 + 4,
          onTap: () async {
            try {
              final result = await ref.read(favoriteIdsProvider.notifier).toggle(productId, current: favorited);

              if (result == null && context.mounted) {
                context.push(Routes.login);
              } else if (context.mounted) {
                showAppMessage(context, texts.t(result! ? 'favorites.added' : 'favorites.removed'));
              }
            } on ApiException catch (error) {
              if (context.mounted) {
                showAppMessage(context, apiErrorMessage(texts, error));
              }
            }
          },
          child: SizedBox(
            width: size,
            height: size,
            child: Center(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 200),
                transitionBuilder: (child, animation) => ScaleTransition(scale: animation, child: child),
                child: Icon(
                  favorited ? Icons.star_rounded : Icons.star_outline_rounded,
                  key: ValueKey(favorited),
                  size: size * 0.62,
                  color: favorited ? colors.primary : colors.ink,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// "App/Product card" (Figma 145:99867): square contained image on the soft well with
/// the discount badge and favourite star, name, price + compare, rating.
class ProductTile extends ConsumerWidget {
  const ProductTile({super.key, required this.product, this.width});

  final ProductCard product;
  final double? width;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final texts = ref.watch(textsProvider);
    final discount = product.flash?.percentage ?? product.discountPercent;

    final tile = InkWell(
      key: ValueKey('product-${product.id}'),
      borderRadius: BorderRadius.circular(AppRadius.card),
      onTap: () => context.push(Routes.product(product.id)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AspectRatio(
            aspectRatio: 1,
            child: Stack(
              children: [
                Positioned.fill(child: NetImage(product.image, semanticLabel: product.name)),
                if (discount != null && discount > 0)
                  PositionedDirectional(
                    start: 8,
                    top: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(color: colors.primary, borderRadius: BorderRadius.circular(4)),
                      child: Text('-$discount%', style: text.labelSmall?.copyWith(color: colors.onPrimary)),
                    ),
                  ),
                PositionedDirectional(end: 8, top: 8, child: FavoriteButton(productId: product.id, initial: product.favorited)),
                if (!product.inStock)
                  PositionedDirectional(
                    start: 8,
                    bottom: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(color: colors.ink.withValues(alpha: 0.75), borderRadius: BorderRadius.circular(4)),
                      child: Text(texts.t('catalog.card.out_of_stock'), style: text.labelSmall?.copyWith(color: colors.bg)),
                    ),
                  ),
                if (product.match != null)
                  PositionedDirectional(
                    start: 8,
                    bottom: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(color: colors.primarySubtle, borderRadius: BorderRadius.circular(4)),
                      child: Text(texts.t('search.image.match', {'percent': product.match}), style: text.labelSmall?.copyWith(color: colors.primary)),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          Text(product.name, style: text.bodyLarge, maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 2),
          PriceLine(price: product.price, compare: product.comparePrice),
          if (product.reviewCount > 0) ...[const SizedBox(height: 2), RatingLine(rating: product.rating, count: product.reviewCount)],
        ],
      ),
    );

    return width == null ? tile : SizedBox(width: width, child: tile);
  }
}

/// "Rail (swipe / drag, snap 16)": horizontal product cards, touch + mouse drag, snapping.
class ProductRail extends StatelessWidget {
  const ProductRail({super.key, required this.products, this.itemWidth = 160, this.height = 262});

  final List<ProductCard> products;
  final double itemWidth;
  final double height;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      child: ScrollConfiguration(
        behavior: const DragScrollBehavior(),
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          physics: SnapScrollPhysics(itemExtent: itemWidth + 12, parent: const BouncingScrollPhysics()),
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: products.length,
          separatorBuilder: (_, _) => const SizedBox(width: 12),
          itemBuilder: (_, index) => EntranceFade(index: index, child: ProductTile(product: products[index], width: itemWidth)),
        ),
      ),
    );
  }
}

/// Two-column product grid as a sliver.
class ProductGridSliver extends StatelessWidget {
  const ProductGridSliver({super.key, required this.products, this.padding = const EdgeInsets.symmetric(horizontal: 16)});

  final List<ProductCard> products;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return SliverPadding(
      padding: padding,
      sliver: SliverGrid(
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: 12,
          mainAxisSpacing: 16,
          mainAxisExtent: 262,
        ),
        delegate: SliverChildBuilderDelegate(
          (_, index) => EntranceFade(index: index % 6, child: ProductTile(product: products[index])),
          childCount: products.length,
        ),
      ),
    );
  }
}

/// Entrance motion (fade + 12 px rise, staggered by index); none with reduced motion.
class EntranceFade extends StatelessWidget {
  const EntranceFade({super.key, required this.child, this.index = 0});

  final Widget child;
  final int index;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) {
      return child;
    }

    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 280 + 50 * index.clamp(0, 6)),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) => Opacity(
        opacity: value,
        child: Transform.translate(offset: Offset(0, 12 * (1 - value)), child: child),
      ),
      child: child,
    );
  }
}

/// Circle category rail item (Figma 145:99834): 64 circle image + 11 label.
class CategoryBubble extends StatelessWidget {
  const CategoryBubble({super.key, required this.name, required this.image, required this.onTap});

  final String name;
  final String? image;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(40),
      child: SizedBox(
        width: 68,
        child: Column(
          children: [
            SizedBox(width: 64, height: 64, child: NetImage(image, radius: 32, icon: 'grid')),
            const SizedBox(height: 6),
            Text(name, style: Theme.of(context).textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w400), maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

/// Loading / error / empty bodies shared by every list screen.
class AsyncStateView extends ConsumerWidget {
  const AsyncStateView.error({super.key, required Object this.error, required this.onRetry}) : title = null, body = null, icon = null, actionLabel = null;

  const AsyncStateView.empty({super.key, required this.title, this.body, this.icon = 'store', this.actionLabel, this.onRetry}) : error = null;

  final Object? error;
  final String? title;
  final String? body;
  final String? icon;
  final String? actionLabel;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);

    if (error != null) {
      final api = error is ApiException ? error as ApiException : null;
      final offline = api?.isNetwork ?? false;

      return StateView(
        icon: offline ? 'globe' : 'alert_triangle',
        tone: offline ? StateTone.warning : StateTone.danger,
        title: offline ? texts.t('app.state.offline_title') : texts.t('app.state.error_title'),
        body: api != null ? apiErrorMessage(texts, api) : texts.t('mobile.errors.generic'),
        actionLabel: texts.t('app.state.retry'),
        onAction: onRetry,
      );
    }

    return StateView(icon: icon ?? 'store', tone: StateTone.primary, title: title!, body: body, actionLabel: actionLabel, onAction: onRetry);
  }
}

/// Grey placeholder blocks while a screen loads.
class SkeletonBox extends StatelessWidget {
  const SkeletonBox({super.key, this.width, required this.height, this.radius = AppRadius.card});

  final double? width;
  final double height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(color: context.colors.border.withValues(alpha: 0.45), borderRadius: BorderRadius.circular(radius)),
    );
  }
}

class GridSkeleton extends StatelessWidget {
  const GridSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 16, mainAxisExtent: 262),
      itemCount: 6,
      itemBuilder: (_, _) => const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AspectRatio(aspectRatio: 1, child: SkeletonBox(height: 160)),
          SizedBox(height: 8),
          SkeletonBox(height: 14, width: 120, radius: 4),
          SizedBox(height: 6),
          SkeletonBox(height: 14, width: 70, radius: 4),
        ],
      ),
    );
  }
}

class ListSkeleton extends StatelessWidget {
  const ListSkeleton({super.key, this.height = 96, this.count = 5});

  final double height;
  final int count;

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: count,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (_, _) => SkeletonBox(height: height),
    );
  }
}

/// Bordered surface card used by cart / checkout / order sections.
class SurfaceCard extends StatelessWidget {
  const SurfaceCard({super.key, required this.child, this.padding = const EdgeInsets.all(14), this.onTap});

  final Widget child;
  final EdgeInsets padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Material(
      color: colors.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.card), side: BorderSide(color: colors.border)),
      clipBehavior: Clip.antiAlias,
      child: InkWell(onTap: onTap, child: Padding(padding: padding, child: child)),
    );
  }
}

/// Label ··· value row of a totals summary.
class SummaryRow extends StatelessWidget {
  const SummaryRow({super.key, required this.label, required this.value, this.strong = false, this.valueColor});

  final String label;
  final String value;
  final bool strong;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    final style = strong ? text.titleMedium?.copyWith(fontWeight: FontWeight.w600) : text.bodyLarge;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label, style: strong ? style : style?.copyWith(color: context.colors.muted))),
          const SizedBox(width: 12),
          Flexible(child: Text(value, style: style?.copyWith(color: valueColor), textAlign: TextAlign.end)),
        ],
      ),
    );
  }
}

/// Small status pill (order / return / payment status).
class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.label, this.tone = StateTone.info});

  final String label;
  final StateTone tone;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final (bg, fg) = switch (tone) {
      StateTone.warning => (colors.warningBg, colors.warning),
      StateTone.info => (colors.infoBg, colors.info),
      StateTone.danger => (colors.dangerBg, colors.danger),
      StateTone.primary => (colors.primarySubtle, colors.primary),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(label, style: Theme.of(context).textTheme.labelSmall?.copyWith(color: fg)),
    );
  }
}

/// Screen frame for pushed pages: top bar with back + scrolling body + optional bottom bar.
class PageScaffold extends ConsumerWidget {
  const PageScaffold({super.key, required this.title, required this.body, this.bottom, this.trailing, this.showBack = true});

  final String title;
  final Widget body;
  final Widget? bottom;
  final Widget? trailing;
  final bool showBack;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppTopBar(
              title: title,
              trailing: trailing,
              backLabel: ref.t('app.auth.back'),
              onBack: showBack ? () => context.canPop() ? context.pop() : context.go(Routes.home) : null,
            ),
            Expanded(child: body),
          ],
        ),
      ),
      bottomNavigationBar: bottom == null ? null : BottomActionBar(child: bottom!),
    );
  }
}

/// Confirm dialog (destructive actions).
Future<bool> confirmDialog(BuildContext context, {required String title, required String body, required String confirm, required String cancel, bool danger = true}) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(title),
      content: Text(body),
      actions: [
        TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: Text(cancel)),
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(true),
          child: Text(confirm, style: TextStyle(color: danger ? context.colors.danger : context.colors.primary)),
        ),
      ],
    ),
  );

  return result ?? false;
}

/// Date in the current language (e.g. "27 Nov 2026").
String formatDate(DateTime? date, String locale) {
  if (date == null) {
    return '';
  }

  const en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const ar = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
  final months = locale == 'ar' ? ar : en;

  return '${date.day} ${months[date.month - 1]} ${date.year}';
}
