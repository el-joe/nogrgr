import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/providers.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../../core/widgets/paged_list.dart';
import '../../../core/widgets/select_field.dart';
import '../data/orders_repository.dart';

/// Status chip tone for an order status.
StateTone orderTone(String status) => switch (status) {
      'cancelled' || 'refunded' || 'returned' => StateTone.danger,
      'pending_payment' => StateTone.warning,
      'delivered' || 'completed' => StateTone.primary,
      _ => StateTone.info,
    };

/// Orders (APP-013): status tabs, paged list, empty per tab.
class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});

  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> {
  static const _tabs = [('', 'all'), ('pending_payment', 'pending'), ('processing', 'processing'), ('shipped', 'shipped'), ('delivered', 'delivered'), ('cancelled', 'cancelled'), ('refunded', 'refunded')];
  String _status = '';

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final provider = ordersProvider(_status);
    final orders = ref.watch(provider);

    final body = switch (orders) {
      AsyncData(:final value) when value.items.isEmpty => AsyncStateView.empty(
          icon: 'orders',
          title: texts.t('orders.empty_title'),
          body: texts.t('orders.empty_text'),
          actionLabel: texts.t('orders.start_shopping'),
          onRetry: () => context.go(Routes.home),
        ),
      AsyncData(:final value) => PagedScroll(
          onRefresh: () async => ref.invalidate(provider),
          onLoadMore: () => ref.read(provider.notifier).loadMore(),
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            itemCount: value.items.length + (value.loadingMore ? 1 : 0),
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (_, index) => index >= value.items.length ? const LoadingMore() : OrderTile(order: value.items[index]),
          ),
        ),
      AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(provider)),
      _ => const ListSkeleton(height: 120, count: 4),
    };

    return PageScaffold(
      title: texts.t('orders.title'),
      body: Column(
        children: [
          SizedBox(
            height: 48,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              itemCount: _tabs.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (_, i) => AppChip(
                key: ValueKey('orders-tab-${_tabs[i].$2}'),
                label: texts.t('orders.tabs.${_tabs[i].$2}'),
                selected: _status == _tabs[i].$1,
                onTap: () => setState(() => _status = _tabs[i].$1),
              ),
            ),
          ),
          Expanded(child: body),
        ],
      ),
    );
  }
}

class OrderTile extends ConsumerWidget {
  const OrderTile({super.key, required this.order});

  final Order order;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final locale = ref.watch(localeCodeProvider);
    final text = Theme.of(context).textTheme;

    return SurfaceCard(
      key: ValueKey('order-${order.uuid}'),
      onTap: () => context.push(Routes.order(order.uuid)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(texts.t('orders.details_title', {'number': order.number}), style: text.bodyLarge?.copyWith(fontWeight: FontWeight.w600))),
              StatusPill(label: order.statusLabel, tone: orderTone(order.status)),
            ],
          ),
          const SizedBox(height: 4),
          Text(texts.t('orders.placed_on', {'date': formatDate(order.placedAt, locale)}), style: text.bodyMedium),
          const SizedBox(height: 10),
          Row(
            children: [
              for (final item in order.items.take(4)) ...[SizedBox(width: 48, height: 48, child: NetImage(item.image, radius: 6)), const SizedBox(width: 6)],
              if (order.items.length > 4) Text(texts.t('orders.more_items', {'count': order.items.length - 4}), style: text.bodyMedium),
              const Spacer(),
              Text(order.grandTotal.label, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
            ],
          ),
        ],
      ),
    );
  }
}
