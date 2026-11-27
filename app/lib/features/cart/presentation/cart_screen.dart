import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../auth/data/session_controller.dart';
import '../../auth/presentation/auth_form.dart';
import '../../catalog/presentation/product_screen.dart';
import '../data/cart_repository.dart';

/// Cart tab (APP-011): lines with quantity / remove / stock issues, free-shipping
/// progress, coupon, totals from the API (never computed here), checkout. Guests keep
/// their cart by X-Cart-Token; signing in merges it.
class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final cart = ref.watch(cartProvider);

    final Widget body = switch (cart) {
      AsyncData(:final value) when value.isEmpty => AsyncStateView.empty(
          icon: 'cart',
          title: texts.t('cart.empty_title'),
          body: texts.t('cart.empty_body'),
          actionLabel: texts.t('cart.see_trending'),
          onRetry: () => context.push(Routes.listing(kind: 'best_selling')),
        ),
      AsyncData(:final value) => _CartBody(cart: value),
      AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(cartProvider)),
      _ => const ListSkeleton(height: 110, count: 3),
    };

    final value = cart.value;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppTopBar(title: value == null || value.isEmpty ? texts.t('cart.title') : '${texts.t('cart.title')} (${value.itemsCount})'),
            Expanded(child: RefreshIndicator(onRefresh: () async => ref.invalidate(cartProvider), child: body)),
          ],
        ),
      ),
      bottomNavigationBar: value == null || value.isEmpty
          ? null
          : BottomActionBar(
              child: AppButton(
                key: const ValueKey('cart-checkout'),
                label: texts.t('cart.to_checkout', {'total': value.totals.grandTotal.label}),
                onPressed: value.canCheckout
                    ? () => ref.read(sessionProvider).signedIn ? context.push(Routes.checkout) : context.push(Routes.login)
                    : null,
              ),
            ),
    );
  }
}

class _CartBody extends ConsumerWidget {
  const _CartBody({required this.cart});

  final CartData cart;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final totals = cart.totals;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        if (cart.freeShippingProgress != null) ...[
          SurfaceCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  cart.freeShippingReached
                      ? texts.t('cart.free_shipping_reached')
                      : texts.t('cart.free_shipping_more', {'weight': _weight(texts.t, cart.freeShippingRemainingGrams ?? 0), 'free': texts.t('cart.free_shipping')}),
                  style: text.labelMedium,
                ),
                const SizedBox(height: 8),
                Semantics(
                  label: texts.t('cart.free_shipping_progress'),
                  value: '${cart.freeShippingProgress}%',
                  child: TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0, end: (cart.freeShippingProgress ?? 0) / 100),
                    duration: const Duration(milliseconds: 500),
                    builder: (_, value, _) => ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(minHeight: 8, value: value, backgroundColor: colors.border, color: colors.primary),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
        ],
        if (!cart.canCheckout && cart.lines.any((l) => l.issue != null)) ...[
          FormBanner(message: texts.t('cart.cannot_checkout')),
          const SizedBox(height: 12),
        ],
        for (final line in cart.lines) ...[
          Dismissible(
            key: ValueKey('cart-line-${line.id}'),
            direction: DismissDirection.endToStart,
            background: Container(
              alignment: AlignmentDirectional.centerEnd,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              decoration: BoxDecoration(color: colors.dangerBg, borderRadius: BorderRadius.circular(AppRadius.card)),
              child: AppIcon('trash', color: colors.danger),
            ),
            onDismissed: (_) => _guard(context, ref, () => ref.read(cartProvider.notifier).remove(line)),
            child: _CartLineTile(line: line),
          ),
          const SizedBox(height: 12),
        ],
        const CouponBox(),
        const SizedBox(height: 12),
        SurfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(texts.t('cart.summary'), style: text.titleLarge),
              const SizedBox(height: 8),
              SummaryRow(label: texts.t('cart.item_total'), value: totals.subtotal.label),
              if (!totals.flashDiscount.isZero) SummaryRow(label: texts.t('cart.item_discount'), value: '-${totals.flashDiscount.label}', valueColor: colors.primary),
              if (!totals.couponDiscount.isZero)
                SummaryRow(label: texts.t('cart.coupon_discount', {'code': cart.coupon?.code ?? ''}), value: '-${totals.couponDiscount.label}', valueColor: colors.primary),
              SummaryRow(
                label: texts.t('cart.shipping'),
                value: !cart.shippingAvailable ? '—' : (cart.shippingFree || totals.shipping.isZero ? texts.t('checkout.free') : totals.shipping.label),
              ),
              if (!cart.shippingAvailable && cart.shippingMessage != null) Text(cart.shippingMessage!, style: text.labelSmall?.copyWith(color: colors.danger)),
              if (cart.deliveryLabel != null) Text(texts.t('product.arrives', {'range': cart.deliveryLabel}), style: text.bodyMedium),
              const Divider(height: 20),
              SummaryRow(label: texts.t('cart.total'), value: totals.grandTotal.label, strong: true),
              if (totals.grandTotal.converted) ...[
                const SizedBox(height: 4),
                Text(texts.t('checkout.charged_usd', {'amount': totals.grandTotal.formatted}), style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400)),
              ],
              const SizedBox(height: 4),
              Text(texts.t('cart.total_note'), style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400)),
            ],
          ),
        ),
      ],
    );
  }

  static String _weight(String Function(String, [Map<String, Object?>]) t, int grams) =>
      grams >= 1000 ? t('cart.weight_kg', {'value': (grams / 1000).toStringAsFixed(1)}) : t('cart.weight_g', {'value': grams});
}

Future<void> _guard(BuildContext context, WidgetRef ref, Future<Object?> Function() action) async {
  try {
    await action();
  } on ApiException catch (error) {
    if (context.mounted) {
      showAppMessage(context, apiErrorMessage(ref.read(textsProvider), error));
      ref.invalidate(cartProvider);
    }
  }
}

class _CartLineTile extends ConsumerWidget {
  const _CartLineTile({required this.line});

  final CartLine line;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return SurfaceCard(
      padding: const EdgeInsets.all(10),
      onTap: () => context.push(Routes.product(line.storeProductId)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 84, height: 84, child: NetImage(line.image, radius: 8)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(line.name, style: text.bodyLarge, maxLines: 2, overflow: TextOverflow.ellipsis),
                if (line.variant != null) Text(line.variant!, style: text.bodyMedium),
                const SizedBox(height: 4),
                Wrap(
                  spacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    if (line.total != null) Text(line.total!.label, style: text.titleMedium?.copyWith(color: colors.primary, fontWeight: FontWeight.w600)),
                    if (line.flashPercent != null) StatusPill(label: texts.t('cart.off', {'percent': line.flashPercent}), tone: StateTone.primary),
                  ],
                ),
                if (line.message != null) ...[
                  const SizedBox(height: 4),
                  Text(line.message!, style: text.labelSmall?.copyWith(color: colors.danger)),
                ],
                const SizedBox(height: 8),
                Row(
                  children: [
                    QuantityStepper(
                      key: ValueKey('cart-qty-${line.id}'),
                      value: line.quantity,
                      onChanged: (value) => _guard(context, ref, () => ref.read(cartProvider.notifier).setQuantity(line, value)),
                    ),
                    const Spacer(),
                    IconButton(
                      tooltip: texts.t('cart.remove_item', {'name': line.name}),
                      onPressed: () => _guard(context, ref, () => ref.read(cartProvider.notifier).remove(line)),
                      icon: AppIcon('trash', size: 20, color: colors.muted),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class CouponBox extends ConsumerStatefulWidget {
  const CouponBox({super.key});

  @override
  ConsumerState<CouponBox> createState() => _CouponBoxState();
}

class _CouponBoxState extends ConsumerState<CouponBox> {
  final _code = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _apply() async {
    if (_code.text.trim().isEmpty) {
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final cart = await ref.read(cartProvider.notifier).applyCoupon(_code.text.trim());

      if (cart.coupon != null && !cart.coupon!.applied) {
        setState(() => _error = cart.coupon!.message);
      } else {
        _code.clear();
      }
    } on ApiException catch (error) {
      setState(() => _error = error.fieldError('code') ?? apiErrorMessage(ref.read(textsProvider), error));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final coupon = ref.watch(cartProvider.select((c) => c.value?.coupon));
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    if (coupon != null && coupon.code.isNotEmpty) {
      return SurfaceCard(
        child: Row(
          children: [
            Icon(Icons.local_offer_outlined, color: coupon.applied ? colors.primary : colors.danger, size: 20),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(coupon.code, style: text.labelMedium),
                  if (coupon.message != null) Text(coupon.message!, style: text.labelSmall?.copyWith(color: coupon.applied ? colors.muted : colors.danger, fontWeight: FontWeight.w400)),
                ],
              ),
            ),
            TextButton(
              key: const ValueKey('coupon-remove'),
              onPressed: () => _guard(context, ref, () => ref.read(cartProvider.notifier).removeCoupon()),
              child: Text(texts.t('checkout.coupon.remove')),
            ),
          ],
        ),
      );
    }

    return SurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(texts.t('checkout.coupon.label'), style: text.labelMedium),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  key: const ValueKey('coupon-field'),
                  controller: _code,
                  textCapitalization: TextCapitalization.characters,
                  onSubmitted: (_) => _apply(),
                  decoration: InputDecoration(
                    isDense: true,
                    hintText: texts.t('cart.coupon_placeholder'),
                    errorText: _error,
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(AppRadius.input)),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              AppButton(key: const ValueKey('coupon-apply'), label: texts.t('checkout.coupon.apply'), width: 96, loading: _busy, onPressed: _apply),
            ],
          ),
        ],
      ),
    );
  }
}
