import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../auth/presentation/auth_form.dart';
import '../../cart/data/cart_repository.dart';
import '../../cart/presentation/cart_screen.dart';
import '../data/checkout_repository.dart';
import 'payment_flow.dart';

/// Checkout (APP-012): shipping address (select / add, intl phone in the form), cart
/// lines + coupon, shipping quote, payment method, totals and the USD charge note when
/// the display currency differs; place order (idempotent) then pay.
class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  CheckoutOptions? _options;
  Object? _error;
  bool _loading = true;
  bool _paying = false;
  int? _addressId;
  String? _method;
  final _idempotencyKey = newIdempotencyKey();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final options = await ref.read(checkoutRepositoryProvider).options(shippingAddressId: _addressId);

      if (mounted) {
        setState(() {
          _options = options;
          _addressId = options.shippingAddressId;
          _method ??= options.methods.length == 1 ? options.methods.first.code : null;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = error);
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _pay(CheckoutOptions options) async {
    final texts = ref.read(textsProvider);
    PaymentMethod? method;

    for (final m in options.methods) {
      if (m.code == _method) {
        method = m;
      }
    }

    if (method == null) {
      showAppMessage(context, texts.t('checkout.payment.select'));

      return;
    }

    setState(() => _paying = true);

    try {
      final placed = await ref.read(checkoutRepositoryProvider).place(idempotencyKey: _idempotencyKey, gateway: method.code, shippingAddressId: _addressId);
      ref.invalidate(cartProvider);
      final outcome = await payOrder(ref, orderUuid: placed.paymentOrder, method: method, numbers: placed.numbers);

      if (mounted) {
        context.pushReplacement(Routes.paymentResult, extra: outcome);
      }
    } on ApiException catch (error) {
      if (mounted) {
        showAppMessage(context, apiErrorMessage(texts, error));
        _load();
      }
    } finally {
      if (mounted) {
        setState(() => _paying = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final options = _options;

    // Coupon changes in the box update the cart; reload the quote.
    ref.listen(cartProvider.select((c) => c.value?.coupon?.code), (previous, next) {
      if (previous != next && _options != null) {
        _load();
      }
    });

    if (options == null) {
      return PageScaffold(
        title: texts.t('checkout.title'),
        body: _error != null ? AsyncStateView.error(error: _error!, onRetry: _load) : const ListSkeleton(height: 90, count: 4),
      );
    }

    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final cart = options.cart;
    final totals = cart.totals;

    Widget section(String title, List<Widget> children) => Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [Text(title, style: text.titleLarge), const SizedBox(height: 8), ...children],
          ),
        );

    return PageScaffold(
      title: texts.t('checkout.title'),
      bottom: AppButton(
        key: const ValueKey('checkout-pay'),
        label: texts.t('checkout.pay_total', {'total': totals.grandTotal.label}),
        loading: _paying,
        onPressed: options.canPay && _addressId != null && !_loading ? () => _pay(options) : null,
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_loading) const LinearProgressIndicator(minHeight: 2),
            if (!options.canPay && options.blockMessage != null) ...[FormBanner(message: options.blockMessage!), const SizedBox(height: 12)],
            section(texts.t('checkout.shipping_address'), [
              if (options.addresses.isEmpty) Text(texts.t('checkout.no_address'), style: text.bodyMedium),
              for (final address in options.addresses) ...[
                SurfaceCard(
                  key: ValueKey('checkout-address-${address.id}'),
                  onTap: () {
                    setState(() => _addressId = address.id);
                    _load();
                  },
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        address.id == _addressId ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                        color: address.id == _addressId ? colors.primary : colors.muted,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(address.label ?? address.fullName, style: text.bodyLarge?.copyWith(fontWeight: FontWeight.w600)),
                            Text(address.fullName, style: text.bodyMedium),
                            if (address.phone != null) Directionality(textDirection: TextDirection.ltr, child: Text(address.phone!, style: text.bodyMedium)),
                            Text(address.oneLine, style: text.bodyMedium),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
              ],
              AppButton(
                key: const ValueKey('checkout-add-address'),
                label: texts.t('account.add_address'),
                style: AppButtonStyle.outline,
                onPressed: () async {
                  final saved = await context.push<bool>(Routes.addressEdit);

                  if (saved == true) {
                    _addressId = null;
                    await _load();
                  }
                },
              ),
            ]),
            section(texts.t('checkout.summary'), [
              for (final line in cart.lines)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(
                    children: [
                      SizedBox(width: 52, height: 52, child: NetImage(line.image, radius: 6)),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(line.name, style: text.bodyLarge, maxLines: 1, overflow: TextOverflow.ellipsis),
                            Text([?line.variant, texts.t('cart.qty', {'qty': line.quantity})].join(' · '), style: text.bodyMedium),
                            if (line.message != null) Text(line.message!, style: text.labelSmall?.copyWith(color: colors.danger)),
                          ],
                        ),
                      ),
                      if (line.total != null) Text(line.total!.label, style: text.bodyLarge),
                    ],
                  ),
                ),
              const SizedBox(height: 4),
              const CouponBox(),
            ]),
            section(texts.t('checkout.payment.title'), [
              if (options.methods.isEmpty) Text(texts.t('checkout.payment.none'), style: text.bodyMedium),
              for (final method in options.methods) ...[
                SurfaceCard(
                  key: ValueKey('method-${method.code}'),
                  onTap: () => setState(() => _method = method.code),
                  child: Row(
                    children: [
                      Icon(method.code == _method ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: method.code == _method ? colors.primary : colors.muted),
                      const SizedBox(width: 10),
                      Expanded(child: Text(method.name, style: text.bodyLarge)),
                      if (method.logoUrl != null) SizedBox(width: 56, height: 24, child: NetImage(method.logoUrl, radius: 2, background: Colors.transparent)),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
              ],
            ]),
            SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SummaryRow(label: texts.t('checkout.items_subtotal'), value: totals.itemsTotal.label),
                  if (!totals.couponDiscount.isZero)
                    SummaryRow(label: texts.t('checkout.discount_code', {'code': cart.coupon?.code ?? ''}), value: '-${totals.couponDiscount.label}', valueColor: colors.primary),
                  SummaryRow(
                    label: texts.t('checkout.shipping_label'),
                    value: !cart.shippingAvailable ? '—' : (cart.shippingFree || totals.shipping.isZero ? texts.t('checkout.free') : totals.shipping.label),
                  ),
                  if (!cart.shippingAvailable && cart.shippingMessage != null) Text(cart.shippingMessage!, style: text.labelSmall?.copyWith(color: colors.danger)),
                  if (cart.deliveryLabel != null) Text(texts.t('product.arrives', {'range': cart.deliveryLabel}), style: text.bodyMedium),
                  const Divider(height: 20),
                  SummaryRow(label: texts.t('checkout.total'), value: totals.grandTotal.label, strong: true),
                  if (totals.grandTotal.converted) ...[
                    const SizedBox(height: 6),
                    Text(
                      key: const ValueKey('usd-note'),
                      texts.t('checkout.charged_usd', {'amount': totals.grandTotal.formatted}),
                      style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
