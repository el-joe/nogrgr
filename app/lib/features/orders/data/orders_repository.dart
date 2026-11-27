import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/json.dart';
import '../../../core/models/product_card.dart';
import '../../../core/network/store_api.dart';
import '../../../core/providers.dart';
import '../../auth/data/session_controller.dart';

class OrderItem {
  const OrderItem({required this.name, required this.quantity, required this.total, this.variant, this.image, this.unitPrice});

  final String name;
  final String? variant;
  final String? image;
  final int quantity;
  final Money? unitPrice;
  final Money total;
}

class TimelineEntry {
  const TimelineEntry({required this.status, required this.label, this.note, this.at});

  final String status;
  final String label;
  final String? note;
  final DateTime? at;
}

class Order {
  const Order({
    required this.uuid,
    required this.number,
    required this.status,
    required this.statusLabel,
    required this.paymentStatus,
    required this.paymentStatusLabel,
    required this.itemsCount,
    required this.cancellable,
    required this.totals,
    required this.items,
    this.placedAt,
    this.paymentGateway,
    this.couponCode,
    this.shippingAddress,
    this.billingAddress,
    this.company,
    this.trackingNumber,
    this.deliveryMin,
    this.deliveryMax,
    this.paymentExpiresAt,
    this.timeline = const [],
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    final totals = jsonMap(json['totals']);
    final shipping = jsonMap(json['shipping']);

    return Order(
      uuid: '${json['uuid'] ?? ''}',
      number: '${json['number'] ?? ''}',
      status: '${json['status'] ?? ''}',
      statusLabel: '${json['status_label'] ?? ''}',
      paymentStatus: '${json['payment_status'] ?? ''}',
      paymentStatusLabel: '${json['payment_status_label'] ?? ''}',
      paymentGateway: jsonString(json['payment_gateway']),
      placedAt: jsonDate(json['placed_at']),
      itemsCount: jsonInt(json['items_count']),
      cancellable: json['cancellable'] == true,
      totals: {for (final e in totals.entries) e.key: Money.fromJson(e.value)},
      couponCode: jsonString(json['coupon_code']),
      items: [
        for (final i in jsonList(json['items']))
          OrderItem(
            name: '${i['name'] ?? ''}',
            variant: jsonString(i['variant']),
            image: jsonString(i['image_url']),
            quantity: jsonInt(i['quantity'], 1),
            unitPrice: Money.maybe(i['unit_price']),
            total: Money.fromJson(i['total']),
          ),
      ],
      shippingAddress: _address(json['shipping_address']),
      billingAddress: _address(json['billing_address']),
      company: jsonString(shipping['company']),
      trackingNumber: jsonString(shipping['tracking_number']),
      deliveryMin: jsonDate(shipping['delivery_min_date']),
      deliveryMax: jsonDate(shipping['delivery_max_date']),
      paymentExpiresAt: jsonDate(json['payment_expires_at']),
      timeline: [
        for (final t in jsonList(json['timeline']))
          TimelineEntry(status: '${t['status'] ?? ''}', label: '${t['label'] ?? ''}', note: jsonString(t['note']), at: jsonDate(t['at'])),
      ],
    );
  }

  /// Address snapshot (JSON object) as display lines.
  static List<String>? _address(Object? source) {
    if (source is! Map) {
      return null;
    }

    final map = source.cast<String, dynamic>();
    final lines = <String>[
      for (final key in ['full_name', 'phone', 'address_line_1', 'city', 'state', 'country_name', 'country'])
        if (map[key] is String && (map[key] as String).isNotEmpty) map[key] as String,
    ];

    return lines.isEmpty ? null : lines;
  }

  final String uuid;
  final String number;
  final String status;
  final String statusLabel;
  final String paymentStatus;
  final String paymentStatusLabel;
  final String? paymentGateway;
  final DateTime? placedAt;
  final int itemsCount;
  final bool cancellable;
  final Map<String, Money> totals;
  final String? couponCode;
  final List<OrderItem> items;
  final List<String>? shippingAddress;
  final List<String>? billingAddress;
  final String? company;
  final String? trackingNumber;
  final DateTime? deliveryMin;
  final DateTime? deliveryMax;
  final DateTime? paymentExpiresAt;
  final List<TimelineEntry> timeline;

  bool get awaitingPayment => status == 'pending_payment' && paymentStatus != 'cod';

  bool get returnable => status == 'delivered' || status == 'completed';

  Money get grandTotal => totals['grand_total'] ?? const Money(amount: 0, formatted: '');
}

class CancelReason {
  const CancelReason({required this.value, required this.label, required this.requiresNote});

  final String value;
  final String label;
  final bool requiresNote;
}

class OrdersPage {
  const OrdersPage({required this.items, required this.meta});

  final List<Order> items;
  final PageMeta meta;
}

/// Customer orders of the Store API (APP-013 … 015).
class OrdersRepository {
  OrdersRepository(this._api);

  final StoreApi _api;

  /// [search] matches the order number, an item name or a tracking number (server side).
  Future<OrdersPage> page(int page, {String? status, String? search}) async {
    final data = (await _api.get('orders', query: {'page': page, 'per_page': 20, 'status': ?status, 'search': ?search})).map;
    final items = data['items'] ?? data['orders'];

    return OrdersPage(items: jsonList(items).map(Order.fromJson).toList(), meta: PageMeta.fromJson(data['meta']));
  }

  Future<Order> show(String uuid) async {
    final data = (await _api.get('orders/$uuid')).map;

    return Order.fromJson(data['order'] is Map<String, dynamic> ? data['order'] as Map<String, dynamic> : data);
  }

  Future<List<CancelReason>> cancellationReasons() async {
    final response = await _api.get('orders/cancellation-reasons');
    final source = response.data is List ? response.data : response.map['reasons'] ?? response.map['items'];

    return [
      for (final r in jsonList(source)) CancelReason(value: '${r['value']}', label: '${r['label']}', requiresNote: r['requires_note'] == true),
    ];
  }

  Future<String> cancel(String uuid, {String? reasonCode, String? note}) async =>
      (await _api.post('orders/$uuid/cancel', body: {'reason_code': ?reasonCode, 'reason': ?note})).message;

  /// 30-minute signed link to the printable invoice.
  Future<String> invoiceUrl(String uuid) async => '${(await _api.get('orders/$uuid/invoice')).map['url']}';
}

final ordersRepositoryProvider = Provider<OrdersRepository>((ref) => OrdersRepository(ref.watch(storeApiProvider)));

/// One orders list: status tab ('' = all) + search term ('' = none).
typedef OrdersQuery = ({String status, String search});

const allOrders = (status: '', search: '');

class OrdersState {
  const OrdersState({this.items = const [], this.page = 0, this.total = 0, this.hasMore = false, this.loadingMore = false});

  final List<Order> items;
  final int page;

  /// Orders matching the query (`meta.total`).
  final int total;
  final bool hasMore;
  final bool loadingMore;
}

class OrdersController extends AsyncNotifier<OrdersState> {
  OrdersController(this.query);

  final OrdersQuery query;

  String? get _status => query.status.isEmpty ? null : query.status;

  String? get _search => query.search.trim().isEmpty ? null : query.search.trim();

  @override
  Future<OrdersState> build() async {
    if (!ref.watch(sessionProvider.select((s) => s.signedIn))) {
      return const OrdersState();
    }

    ref.watch(localeCodeProvider);
    final first = await ref.read(ordersRepositoryProvider).page(1, status: _status, search: _search);

    return OrdersState(items: first.items, page: 1, total: first.meta.total, hasMore: first.meta.hasMore);
  }

  Future<void> loadMore() async {
    final current = state.value;

    if (current == null || !current.hasMore || current.loadingMore) {
      return;
    }

    state = AsyncData(OrdersState(items: current.items, page: current.page, total: current.total, hasMore: true, loadingMore: true));

    try {
      final next = await ref.read(ordersRepositoryProvider).page(current.page + 1, status: _status, search: _search);
      state = AsyncData(OrdersState(items: [...current.items, ...next.items], page: current.page + 1, total: next.meta.total, hasMore: next.meta.hasMore));
    } catch (_) {
      state = AsyncData(current);
    }
  }
}

final ordersProvider = AsyncNotifierProvider.family<OrdersController, OrdersState, OrdersQuery>(OrdersController.new);

final orderProvider = FutureProvider.autoDispose.family<Order, String>((ref, uuid) {
  ref.watch(localeCodeProvider);

  return ref.watch(ordersRepositoryProvider).show(uuid);
});
