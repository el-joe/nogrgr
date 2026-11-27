import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../auth/presentation/auth_form.dart';
import '../../cart/data/cart_repository.dart';
import '../data/catalog_models.dart';
import '../data/catalog_repository.dart';
import 'gallery_video.dart';

final productProvider = FutureProvider.autoDispose.family<ProductDetail, int>((ref, id) {
  ref.watch(localeCodeProvider);
  ref.watch(preferencesProvider.select((p) => (p.currency, p.country)));

  return ref.watch(catalogRepositoryProvider).product(id);
});

/// Product details (APP-010): contained gallery (swipe), price / compare / flash
/// countdown, variant pickers with live price + stock, delivery estimate, quantity,
/// description, review summary, related rail; add to cart + favourite. 404 = not sold
/// in the shopping country.
class ProductScreen extends ConsumerWidget {
  const ProductScreen({super.key, required this.id});

  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final product = ref.watch(productProvider(id));

    return switch (product) {
      AsyncData(:final value) => _ProductView(product: value),
      AsyncError(:final error) => PageScaffold(
          title: '',
          body: error is ApiException && error.kind == ApiErrorKind.notFound
              ? AsyncStateView.empty(
                  icon: 'globe',
                  title: texts.t('app.product.unavailable_title'),
                  body: texts.t('app.product.unavailable_body'),
                  actionLabel: texts.t('errors.404.continue'),
                  onRetry: () => context.go(Routes.home),
                )
              : AsyncStateView.error(error: error, onRetry: () => ref.invalidate(productProvider(id))),
        ),
      _ => const PageScaffold(title: '', body: _ProductSkeleton()),
    };
  }
}

class _ProductView extends ConsumerStatefulWidget {
  const _ProductView({required this.product});

  final ProductDetail product;

  @override
  ConsumerState<_ProductView> createState() => _ProductViewState();
}

class _ProductViewState extends ConsumerState<_ProductView> {
  late Map<int, int> _picked = Map.of(widget.product.variantById(widget.product.selectedVariantId)?.options ?? const {});
  int _quantity = 1;
  bool _adding = false;
  int _image = 0;
  final _gallery = PageController();

  ProductDetail get p => widget.product;

  Variant? get _variant => p.variants.isEmpty ? null : p.variantFor(_picked);

  bool get _needsChoice => p.variants.isNotEmpty && _variant == null;

  @override
  void dispose() {
    _gallery.dispose();
    super.dispose();
  }

  void _pick(int attributeId, int optionId) {
    setState(() {
      _picked = {..._picked, attributeId: optionId};
      _quantity = 1;
      final image = _variant?.image;
      final index = image == null ? -1 : p.gallery.indexWhere((item) => !item.isVideo && item.url == image);

      if (index >= 0 && _gallery.hasClients) {
        _gallery.animateToPage(index, duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
      }
    });
  }

  Future<void> _addToCart() async {
    final texts = ref.read(textsProvider);
    setState(() => _adding = true);

    try {
      await ref.read(cartProvider.notifier).add(p.id, variantId: _variant?.id, quantity: _quantity);

      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
            key: const ValueKey('cart-added-snack'),
            content: Text(texts.t('cart.added.title')),
            // An action makes a SnackBar persist by default: auto-dismiss like the others.
            duration: const Duration(seconds: 4),
            persist: false,
            action: SnackBarAction(label: texts.t('cart.view_cart'), onPressed: () => _openCart(context)),
          ));
      }
    } on ApiException catch (error) {
      if (mounted) {
        showAppMessage(context, apiErrorMessage(texts, error));
      }
    } finally {
      if (mounted) {
        setState(() => _adding = false);
      }
    }
  }

  /// Leaves for the cart without the "added" message covering its checkout button.
  void _openCart(BuildContext context) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    context.go(Routes.cart);
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final variant = _variant;
    final price = variant?.price ?? p.price;
    final compare = variant != null ? variant.comparePrice : p.comparePrice;
    final discount = variant?.discountPercent ?? p.discountPercent;
    final inStock = variant?.inStock ?? p.inStock;
    final stock = variant?.stock ?? p.stock;
    // Gallery = `media` (images + videos in order), else `images`; empty = placeholder.
    final gallery = p.gallery;
    final slides = gallery.isEmpty ? 1 : gallery.length;
    final maxQty = stock != null && stock > 0 ? stock.clamp(1, 99) : 99;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: AppTopBar(
                title: '',
                backLabel: texts.t('app.auth.back'),
                onBack: () => context.canPop() ? context.pop() : context.go(Routes.home),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    FavoriteButton(productId: p.id, initial: p.favorited, size: 36),
                    IconButton(tooltip: texts.t('cart.title'), onPressed: () => _openCart(context), icon: const AppIcon('cart')),
                  ],
                ),
              ),
            ),
            // Gallery: swipe (touch + mouse drag), contained images + inline videos, dots.
            SliverToBoxAdapter(
              child: Column(
                children: [
                  AspectRatio(
                    aspectRatio: 1,
                    child: ScrollConfiguration(
                      behavior: const DragScrollBehavior(),
                      child: PageView.builder(
                        key: const ValueKey('product-gallery'),
                        controller: _gallery,
                        itemCount: slides,
                        onPageChanged: (index) => setState(() => _image = index),
                        itemBuilder: (_, index) => Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          child: gallery.isEmpty
                              ? NetImage(null, semanticLabel: p.name)
                              : gallery[index].isVideo
                                  ? GalleryVideo(key: ValueKey('gallery-video-$index'), media: gallery[index], active: index == _image, semanticLabel: p.name)
                                  : NetImage(gallery[index].url, semanticLabel: p.name),
                        ),
                      ),
                    ),
                  ),
                  if (slides > 1)
                    Padding(
                      padding: const EdgeInsets.only(top: 10),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          for (var i = 0; i < slides; i++)
                            // Video slides show a small play mark instead of a dot.
                            gallery[i].isVideo
                                ? Padding(
                                    key: ValueKey('gallery-dot-video-$i'),
                                    padding: const EdgeInsets.symmetric(horizontal: 2),
                                    child: Icon(Icons.play_circle_fill_rounded, size: 12, color: i == _image ? colors.primary : colors.border),
                                  )
                                : AnimatedContainer(
                                    key: ValueKey('gallery-dot-$i'),
                                    duration: const Duration(milliseconds: 200),
                                    margin: const EdgeInsets.symmetric(horizontal: 3),
                                    width: i == _image ? 18 : 6,
                                    height: 6,
                                    decoration: BoxDecoration(color: i == _image ? colors.primary : colors.border, borderRadius: BorderRadius.circular(3)),
                                  ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              sliver: SliverList.list(
                children: [
                  if (p.flash != null && p.flash!.endsAt != null && p.flash!.endsAt!.isAfter(DateTime.now()))
                    Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(color: colors.primarySubtle, borderRadius: BorderRadius.circular(AppRadius.input)),
                      child: Row(
                        children: [
                          Expanded(child: Text(texts.t('product.flash', {'percent': p.flash!.percentage}), style: text.labelMedium?.copyWith(color: colors.primary))),
                          Countdown(endsAt: p.flash!.endsAt!, onDone: () => ref.invalidate(productProvider(p.id))),
                        ],
                      ),
                    ),
                  Text(p.name, key: const ValueKey('product-name'), style: text.headlineSmall),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(child: PriceLine(price: price, compare: compare, large: true)),
                      if (discount != null && discount > 0) StatusPill(label: texts.t('product.discount_off', {'percent': discount}), tone: StateTone.primary),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 12,
                    children: [
                      if (p.reviews.count > 0)
                        InkWell(
                          onTap: () => context.push(Routes.reviews(p.id)),
                          child: RatingLine(rating: p.reviews.average, count: p.reviews.count),
                        ),
                      if (p.soldCount != null && p.soldCount! > 0) Text(texts.t('product.sold', {'count': p.soldCount}), style: text.bodyMedium),
                    ],
                  ),
                  if (p.summary != null) ...[const SizedBox(height: 12), Text(p.summary!, style: text.bodyLarge?.copyWith(color: colors.muted))],
                  for (final attribute in p.attributes) ...[
                    const SizedBox(height: 16),
                    Text(attribute.name, style: text.labelMedium),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final option in attribute.options)
                          _OptionChip(
                            key: ValueKey('option-${attribute.id}-${option.id}'),
                            option: option,
                            swatch: attribute.swatch,
                            selected: _picked[attribute.id] == option.id,
                            onTap: () => _pick(attribute.id, option.id),
                          ),
                      ],
                    ),
                  ],
                  if (_needsChoice) ...[const SizedBox(height: 8), Text(texts.t('product.unavailable'), style: text.labelSmall?.copyWith(color: colors.danger))],
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Text(texts.t('product.qty'), style: text.labelMedium),
                      const SizedBox(width: 12),
                      QuantityStepper(
                        value: _quantity,
                        max: maxQty,
                        onChanged: inStock ? (value) => setState(() => _quantity = value) : null,
                      ),
                      const SizedBox(width: 12),
                      if (inStock && stock != null && stock <= 10) Expanded(child: Text(texts.t('app.product.stock_left', {'count': stock}), style: text.labelSmall?.copyWith(color: colors.warning))),
                      if (!inStock) Expanded(child: Text(texts.t('product.out_of_stock'), style: text.labelMedium?.copyWith(color: colors.danger))),
                    ],
                  ),
                  if (p.delivery != null) ...[
                    const SizedBox(height: 16),
                    SurfaceCard(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          AppIcon('truck', color: colors.primary),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(texts.t('product.arrives', {'range': p.delivery!.label}), style: text.bodyLarge?.copyWith(fontWeight: FontWeight.w600)),
                                if (p.delivery!.location != null) Text(texts.t('product.deliver_to', {'location': p.delivery!.location}), style: text.bodyMedium),
                                if (p.delivery!.company != null) Text(texts.t('product.courier', {'company': p.delivery!.company}), style: text.bodyMedium),
                                if (p.delivery!.cutoffMinutes != null)
                                  Text(
                                    texts.t('product.cutoff', {'hours': p.delivery!.cutoffMinutes! ~/ 60, 'minutes': p.delivery!.cutoffMinutes! % 60}),
                                    style: text.labelSmall?.copyWith(color: colors.primary),
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  if (!inStock) ...[const SizedBox(height: 12), Text(texts.t('product.out_of_stock_note'), style: text.bodyMedium)],
                  if (p.description != null && p.description!.trim().isNotEmpty) ...[
                    const SizedBox(height: 20),
                    Text(texts.t('product.details'), style: text.titleLarge),
                    const SizedBox(height: 8),
                    _ExpandableHtml(html: p.description!),
                  ],
                  const SizedBox(height: 20),
                  _ReviewSummaryCard(product: p),
                ],
              ),
            ),
            if (p.related.isNotEmpty) ...[
              SliverToBoxAdapter(child: SectionHeader(title: texts.t('product.related'))),
              SliverToBoxAdapter(child: ProductRail(products: p.related)),
            ],
            const SliverToBoxAdapter(child: SizedBox(height: 24)),
          ],
        ),
      ),
      bottomNavigationBar: BottomActionBar(
        child: AppButton(
          key: const ValueKey('product-add-to-cart'),
          label: !inStock ? texts.t('product.out_of_stock') : texts.t('product.add_to_cart'),
          loading: _adding,
          onPressed: inStock && !_needsChoice ? _addToCart : null,
        ),
      ),
    );
  }
}

class _OptionChip extends StatelessWidget {
  const _OptionChip({super.key, required this.option, required this.swatch, required this.selected, required this.onTap});

  final AttributeOption option;
  final bool swatch;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final color = swatch ? _parse(option.swatch) : null;

    if (color != null) {
      return Semantics(
        button: true,
        selected: selected,
        label: option.name,
        child: InkResponse(
          onTap: onTap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            width: 36,
            height: 36,
            padding: const EdgeInsets.all(3),
            decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: selected ? colors.primary : colors.border, width: selected ? 2 : 1)),
            child: DecoratedBox(decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
          ),
        ),
      );
    }

    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: selected ? colors.primarySubtle : colors.surface,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadius.input), side: BorderSide(color: selected ? colors.primary : colors.border, width: selected ? 1.5 : 1)),
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
            child: Text(option.name, style: Theme.of(context).textTheme.labelMedium?.copyWith(color: selected ? colors.primary : colors.ink)),
          ),
        ),
      ),
    );
  }

  static Color? _parse(String? hex) {
    if (hex == null || !hex.startsWith('#') || (hex.length != 7 && hex.length != 4)) {
      return null;
    }

    var value = hex.substring(1);

    if (value.length == 3) {
      value = value.split('').map((c) => '$c$c').join();
    }

    final parsed = int.tryParse(value, radix: 16);

    return parsed == null ? null : Color(0xFF000000 | parsed);
  }
}

/// − n + stepper (min 1).
class QuantityStepper extends ConsumerWidget {
  const QuantityStepper({super.key, required this.value, required this.onChanged, this.max = 99, this.min = 1});

  final int value;
  final int max;
  final int min;
  final ValueChanged<int>? onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = context.colors;
    final texts = ref.watch(textsProvider);

    Widget button(IconData icon, String label, int? next) => IconButton(
          tooltip: label,
          visualDensity: VisualDensity.compact,
          onPressed: onChanged == null || next == null ? null : () => onChanged!(next),
          icon: Icon(icon, size: 18),
        );

    return Container(
      height: 36,
      decoration: BoxDecoration(border: Border.all(color: colors.border), borderRadius: BorderRadius.circular(999)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          button(Icons.remove_rounded, texts.t('app.cart.decrease'), value > min ? value - 1 : null),
          SizedBox(width: 28, child: Text('$value', key: const ValueKey('qty-value'), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium)),
          button(Icons.add_rounded, texts.t('app.cart.increase'), value < max ? value + 1 : null),
        ],
      ),
    );
  }
}

class _ExpandableHtml extends ConsumerStatefulWidget {
  const _ExpandableHtml({required this.html});

  final String html;

  @override
  ConsumerState<_ExpandableHtml> createState() => _ExpandableHtmlState();
}

class _ExpandableHtmlState extends ConsumerState<_ExpandableHtml> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AnimatedSize(
          duration: const Duration(milliseconds: 250),
          alignment: AlignmentDirectional.topStart,
          child: ConstrainedBox(
            constraints: BoxConstraints(maxHeight: _open ? double.infinity : 160),
            child: ClipRect(child: HtmlWidget(widget.html, textStyle: Theme.of(context).textTheme.bodyLarge)),
          ),
        ),
        AppLink(label: texts.t(_open ? 'product.details_less' : 'product.details_more'), onTap: () => setState(() => _open = !_open)),
      ],
    );
  }
}

class _ReviewSummaryCard extends ConsumerWidget {
  const _ReviewSummaryCard({required this.product});

  final ProductDetail product;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final text = Theme.of(context).textTheme;
    final colors = context.colors;
    final reviews = product.reviews;

    return SurfaceCard(
      onTap: () => context.push(Routes.reviews(product.id)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(texts.t('product.reviews.title'), style: text.titleLarge)),
              AppIcon('chevron_right', size: 18, color: colors.muted, mirrorInRtl: true),
            ],
          ),
          const SizedBox(height: 8),
          if (reviews.count == 0)
            Text(texts.t('product.reviews.none'), style: text.bodyMedium)
          else
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Column(
                  children: [
                    Text(reviews.average.toStringAsFixed(1), style: text.headlineSmall?.copyWith(fontSize: 32)),
                    Text(texts.t('product.reviews.count', {'count': reviews.count}), style: text.bodyMedium),
                  ],
                ),
                const SizedBox(width: 16),
                Expanded(child: RatingBars(summary: reviews)),
              ],
            ),
        ],
      ),
    );
  }
}

/// 5 → 1 star distribution bars.
class RatingBars extends StatelessWidget {
  const RatingBars({super.key, required this.summary});

  final ReviewSummary summary;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Column(
      children: [
        for (final stars in [5, 4, 3, 2, 1])
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 2),
            child: Row(
              children: [
                SizedBox(width: 14, child: Text('$stars', style: Theme.of(context).textTheme.labelSmall)),
                const Icon(Icons.star_rounded, size: 12, color: Color(0xFFF5A623)),
                const SizedBox(width: 6),
                Expanded(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(3),
                    child: LinearProgressIndicator(
                      minHeight: 6,
                      value: summary.count == 0 ? 0 : (summary.distribution[stars] ?? 0) / summary.count,
                      backgroundColor: colors.border,
                      color: colors.primary,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _ProductSkeleton extends StatelessWidget {
  const _ProductSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AspectRatio(aspectRatio: 1, child: SkeletonBox(height: 300)),
          SizedBox(height: 16),
          SkeletonBox(height: 22, width: 220, radius: 4),
          SizedBox(height: 10),
          SkeletonBox(height: 20, width: 120, radius: 4),
        ],
      ),
    );
  }
}
