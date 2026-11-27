import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/models/product_card.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/url_opener.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../auth/presentation/auth_form.dart';
import '../../cart/data/cart_repository.dart';
import '../../checkout/data/checkout_repository.dart';
import '../../checkout/presentation/payment_flow.dart';
import '../data/orders_repository.dart';
import 'orders_screen.dart';

/// Order details (APP-014): status, items, totals, addresses, payment; actions complete
/// payment, track, reorder, invoice (signed link), cancel with reasons, request return.
class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({super.key, required this.uuid});

  final String uuid;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _busy = true);

    try {
      await action();
    } on ApiException catch (error) {
      if (mounted) {
        showAppMessage(context, apiErrorMessage(ref.read(textsProvider), error));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _reorder() => _run(() async {
        final texts = ref.read(textsProvider);
        final result = await ref.read(cartProvider.notifier).reorder(widget.uuid);

        if (!mounted) {
          return;
        }

        showAppMessage(
          context,
          result.skipped.isEmpty ? texts.t('orders.reorder_done') : texts.t('orders.reorder_partial', {'items': result.skipped.join(', ')}),
        );
        context.go(Routes.cart);
      });

  Future<void> _invoice() => _run(() async {
        final url = await ref.read(ordersRepositoryProvider).invoiceUrl(widget.uuid);
        await ref.read(urlOpenerProvider)(url);
      });

  Future<void> _pay(Order order) => _run(() async {
        final outcome = await payOrder(
          ref,
          orderUuid: order.uuid,
          method: PaymentMethod(code: order.paymentGateway ?? 'stripe', name: order.paymentGateway ?? '', inline: false),
          numbers: [order.number],
        );

        if (mounted) {
          context.push(Routes.paymentResult, extra: outcome);
        }
      });

  Future<void> _cancel(Order order) async {
    final message = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => CancelOrderSheet(order: order),
    );

    if (message != null && mounted) {
      showAppMessage(context, message.isEmpty ? ref.read(textsProvider).t('orders.cancel') : message);
      ref.invalidate(orderProvider(widget.uuid));
      ref.invalidate(ordersProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final order = ref.watch(orderProvider(widget.uuid));

    return PageScaffold(
      title: order.value == null ? texts.t('orders.details') : texts.t('orders.details_title', {'number': order.value!.number}),
      body: switch (order) {
        AsyncData(:final value) => Stack(
            children: [
              RefreshIndicator(onRefresh: () async => ref.invalidate(orderProvider(widget.uuid)), child: _OrderBody(order: value, actions: _actions(value))),
              if (_busy) const Positioned(top: 0, left: 0, right: 0, child: LinearProgressIndicator(minHeight: 2)),
            ],
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(orderProvider(widget.uuid))),
        _ => const ListSkeleton(height: 120, count: 4),
      },
    );
  }

  List<Widget> _actions(Order order) {
    final texts = ref.read(textsProvider);
    final colors = context.colors;

    return [
      if (order.awaitingPayment)
        AppButton(key: const ValueKey('order-pay'), label: texts.t('orders.complete_payment'), onPressed: _busy ? null : () => _pay(order)),
      AppButton(key: const ValueKey('order-track'), label: texts.t('orders.track'), style: AppButtonStyle.outline, onPressed: () => context.push(Routes.orderTracking(order.uuid))),
      AppButton(key: const ValueKey('order-reorder'), label: texts.t('orders.reorder'), style: AppButtonStyle.outline, onPressed: _busy ? null : _reorder),
      AppButton(key: const ValueKey('order-invoice'), label: texts.t('orders.download_invoice'), style: AppButtonStyle.outline, onPressed: _busy ? null : _invoice),
      AppButton(key: const ValueKey('order-support'), label: texts.t('support.tickets.new_title'), style: AppButtonStyle.outline, onPressed: () => context.push(Routes.newTicketFor(order.uuid))),
      if (order.returnable)
        AppButton(key: const ValueKey('order-return'), label: texts.t('returns.request_title'), style: AppButtonStyle.outline, onPressed: () => context.push(Routes.returnRequest(order.uuid))),
      if (order.cancellable)
        TextButton(
          key: const ValueKey('order-cancel'),
          onPressed: _busy ? null : () => _cancel(order),
          child: Text(texts.t('orders.cancel_before_shipping'), style: TextStyle(color: colors.danger)),
        ),
    ];
  }
}

class _OrderBody extends ConsumerWidget {
  const _OrderBody({required this.order, required this.actions});

  final Order order;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final locale = ref.watch(localeCodeProvider);
    final text = Theme.of(context).textTheme;
    final colors = context.colors;
    final t = order.totals;
    Money? money(String key) => t[key] == null || t[key]!.isZero ? null : t[key];

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      children: [
        SurfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: Text(texts.t('orders.placed_on', {'date': formatDate(order.placedAt, locale)}), style: text.bodyMedium)),
                  StatusPill(label: order.statusLabel, tone: orderTone(order.status)),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                '${texts.t('orders.payment')}: ${[order.paymentStatusLabel, ?(order.paymentGateway == 'cod' && order.paymentGatewayName == null ? texts.t('app.payment.cod') : order.paymentGatewayLabel)].join(' · ')}',
                key: const ValueKey('order-payment-line'),
                style: text.bodyMedium,
              ),
              if (order.deliveryMin != null)
                Text('${texts.t('orders.estimated')}: ${formatDate(order.deliveryMin, locale)} – ${formatDate(order.deliveryMax ?? order.deliveryMin, locale)}', style: text.bodyMedium),
              if (order.awaitingPayment && order.paymentExpiresAt != null)
                Text(texts.t('orders.awaiting_text', {'number': order.number}), style: text.labelSmall?.copyWith(color: colors.warning)),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Text(texts.t('orders.items_count', {'count': order.itemsCount}), style: text.titleLarge),
        const SizedBox(height: 8),
        for (final item in order.items)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Row(
              children: [
                SizedBox(width: 60, height: 60, child: NetImage(item.image, radius: 6)),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.name, style: text.bodyLarge, maxLines: 2, overflow: TextOverflow.ellipsis),
                      Text([?item.variant, texts.t('orders.qty', {'count': item.quantity})].join(' · '), style: text.bodyMedium),
                    ],
                  ),
                ),
                Text(item.total.label, style: text.bodyLarge),
              ],
            ),
          ),
        SurfaceCard(
          child: Column(
            children: [
              if (money('items_total') != null) SummaryRow(label: texts.t('orders.items_subtotal'), value: money('items_total')!.label),
              if (money('coupon_discount') != null) SummaryRow(label: texts.t('orders.coupon', {'code': order.couponCode ?? ''}), value: '-${money('coupon_discount')!.label}', valueColor: colors.primary),
              SummaryRow(label: texts.t('orders.shipping'), value: money('shipping')?.label ?? texts.t('checkout.free')),
              if (money('tax') != null) SummaryRow(label: texts.t('orders.tax'), value: money('tax')!.label),
              const Divider(height: 20),
              SummaryRow(label: texts.t('orders.total'), value: order.grandTotal.label, strong: true),
              if (order.grandTotal.converted)
                Text(texts.t('checkout.charged_usd', {'amount': order.grandTotal.formatted}), style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400)),
            ],
          ),
        ),
        if (order.shippingAddress != null) ...[
          const SizedBox(height: 16),
          Text(texts.t('orders.shipping_address'), style: text.titleLarge),
          const SizedBox(height: 6),
          SurfaceCard(
            key: const ValueKey('order-shipping-address'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final (index, line) in order.shippingAddress!.lines(locale).indexed)
                  Text(line, style: index == 0 ? text.bodyLarge?.copyWith(fontWeight: FontWeight.w600) : text.bodyMedium),
              ],
            ),
          ),
        ],
        const SizedBox(height: 20),
        for (final action in actions) Padding(padding: const EdgeInsets.only(bottom: 10), child: action),
      ],
    );
  }
}

/// Two-step cancel (reason → confirm with optional note; "other" needs a note).
class CancelOrderSheet extends ConsumerStatefulWidget {
  const CancelOrderSheet({super.key, required this.order});

  final Order order;

  @override
  ConsumerState<CancelOrderSheet> createState() => _CancelOrderSheetState();
}

class _CancelOrderSheetState extends ConsumerState<CancelOrderSheet> {
  List<CancelReason>? _reasons;
  String? _reason;
  bool _confirm = false;
  bool _busy = false;
  String? _error;
  final _note = TextEditingController();

  @override
  void initState() {
    super.initState();
    ref.read(ordersRepositoryProvider).cancellationReasons().then((r) {
      if (mounted) {
        setState(() => _reasons = r);
      }
    }, onError: (Object _) {
      if (mounted) {
        setState(() => _reasons = const []);
      }
    });
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final texts = ref.read(textsProvider);
    final reason = _reasons?.where((r) => r.value == _reason).firstOrNull;

    if (reason != null && reason.requiresNote && _note.text.trim().isEmpty) {
      setState(() => _error = texts.t('app.validation.required'));

      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final message = await ref.read(ordersRepositoryProvider).cancel(widget.order.uuid, reasonCode: _reason, note: _note.text.trim().isEmpty ? null : _note.text.trim());

      if (mounted) {
        Navigator.of(context).pop(message);
      }
    } on ApiException catch (error) {
      setState(() => _error = error.fieldError('reason') ?? apiErrorMessage(texts, error));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final text = Theme.of(context).textTheme;
    final reasons = _reasons;

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(texts.t('orders.cancel_title'), style: text.titleLarge),
          const SizedBox(height: 4),
          Text(texts.t(_confirm ? 'orders.cancel_step_two' : 'orders.cancel_step_one'), style: text.bodyMedium),
          const SizedBox(height: 12),
          if (reasons == null)
            const Center(child: CircularProgressIndicator())
          else if (!_confirm) ...[
            for (final reason in reasons)
              ListTile(
                key: ValueKey('cancel-reason-${reason.value}'),
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  _reason == reason.value ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                  color: _reason == reason.value ? context.colors.primary : context.colors.muted,
                ),
                title: Text(reason.label),
                onTap: () => setState(() => _reason = reason.value),
              ),
            if (_error != null) Text(_error!, style: TextStyle(color: context.colors.danger)),
            const SizedBox(height: 8),
            AppButton(
              key: const ValueKey('cancel-continue'),
              label: texts.t('orders.continue'),
              onPressed: () => _reason == null && reasons.isNotEmpty ? setState(() => _error = texts.t('orders.choose_reason')) : setState(() {
                    _confirm = true;
                    _error = null;
                  }),
            ),
          ] else ...[
            Text(texts.t('orders.cancel_summary', {'number': widget.order.number}), style: text.bodyLarge),
            if (widget.order.paymentStatus == 'paid') Text(texts.t('orders.refund_note', {'amount': widget.order.grandTotal.label}), style: text.bodyMedium),
            const SizedBox(height: 12),
            AppTextField(fieldKey: const ValueKey('cancel-note'), label: texts.t('orders.note_label'), controller: _note, error: _error),
            AppButton(key: const ValueKey('cancel-confirm'), label: texts.t('orders.cancel'), loading: _busy, onPressed: _submit),
            const SizedBox(height: 8),
            AppButton(label: texts.t('orders.keep_order'), style: AppButtonStyle.outline, onPressed: () => Navigator.of(context).pop()),
          ],
        ],
      ),
    );
  }
}

/// Order tracking (APP-015): carrier + AWB, estimated delivery, status timeline.
class OrderTrackingScreen extends ConsumerWidget {
  const OrderTrackingScreen({super.key, required this.uuid});

  final String uuid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final locale = ref.watch(localeCodeProvider);
    final order = ref.watch(orderProvider(uuid));
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return PageScaffold(
      title: order.value == null ? texts.t('orders.tracking') : texts.t('orders.tracking_title', {'number': order.value!.number}),
      body: switch (order) {
        AsyncData(:final value) => RefreshIndicator(
            onRefresh: () async => ref.invalidate(orderProvider(uuid)),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                SurfaceCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [AppIcon('truck', color: colors.primary), const SizedBox(width: 10), Expanded(child: Text(value.statusLabel, style: text.titleMedium))]),
                      if (value.trackingNumber != null) ...[
                        const SizedBox(height: 6),
                        SelectableText(texts.t('orders.awb', {'carrier': value.company ?? '', 'number': value.trackingNumber}), style: text.bodyMedium),
                      ],
                      if (value.deliveryMin != null)
                        Text('${texts.t('orders.estimated')}: ${formatDate(value.deliveryMin, locale)} – ${formatDate(value.deliveryMax ?? value.deliveryMin, locale)}', style: text.bodyMedium),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                if (value.timeline.isEmpty) Text(texts.t('orders.tracking_empty'), style: text.bodyLarge),
                for (var i = 0; i < value.timeline.length; i++)
                  IntrinsicHeight(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Column(
                          children: [
                            Container(width: 14, height: 14, decoration: BoxDecoration(color: i == value.timeline.length - 1 ? colors.primary : colors.border, shape: BoxShape.circle)),
                            if (i < value.timeline.length - 1) Expanded(child: Container(width: 2, color: colors.border)),
                          ],
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 18),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(value.timeline[i].label, style: text.bodyLarge?.copyWith(fontWeight: FontWeight.w600)),
                                Text(formatDate(value.timeline[i].at, locale), style: text.bodyMedium),
                                if (value.timeline[i].note != null) Text(value.timeline[i].note!, style: text.bodyMedium),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(orderProvider(uuid))),
        _ => const ListSkeleton(),
      },
    );
  }
}
