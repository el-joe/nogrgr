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
    this.paymentGatewayName,
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
      paymentGatewayName: jsonString(json['payment_gateway_name']),
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

  static OrderAddress? _address(Object? source) => source is Map ? OrderAddress.fromJson(source.cast<String, dynamic>()) : null;

  final String uuid;
  final String number;
  final String status;
  final String statusLabel;
  final String paymentStatus;
  final String paymentStatusLabel;
  final String? paymentGateway;

  /// Display name from the API when it sends one (`payment_gateway_name`).
  final String? paymentGatewayName;

  /// "Stripe" / "PayPal" for the raw gateway code: API name → known brand → capitalised.
  String? get paymentGatewayLabel {
    if (paymentGatewayName != null && paymentGatewayName!.trim().isNotEmpty) {
      return paymentGatewayName;
    }

    final code = paymentGateway?.trim();

    if (code == null || code.isEmpty) {
      return null;
    }

    return gatewayDisplayName(code);
  }
  final DateTime? placedAt;
  final int itemsCount;
  final bool cancellable;
  final Map<String, Money> totals;
  final String? couponCode;
  final List<OrderItem> items;
  final OrderAddress? shippingAddress;
  final OrderAddress? billingAddress;
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

/// Known gateway codes → brand spelling; others are title-cased (`bank_transfer` → "Bank Transfer").
String gatewayDisplayName(String code) {
  const known = {
    'stripe': 'Stripe',
    'paypal': 'PayPal',
    'paymob': 'Paymob',
    'tap': 'Tap',
    'moyasar': 'Moyasar',
    'hyperpay': 'HyperPay',
    'tabby': 'Tabby',
    'tamara': 'Tamara',
    'myfatoorah': 'MyFatoorah',
    'paytabs': 'PayTabs',
    'checkout': 'Checkout.com',
    'razorpay': 'Razorpay',
  };
  final key = code.toLowerCase();

  if (known.containsKey(key)) {
    return known[key]!;
  }

  return key
      .split(RegExp(r'[_\-\s]+'))
      .where((word) => word.isNotEmpty)
      .map((word) => word[0].toUpperCase() + word.substring(1))
      .join(' ');
}

/// Address snapshot copied onto the order (`OrderAddressSnapshotData`: full_name, phone,
/// address_line1, city, state, country_name {locale: name}, country_iso2).
class OrderAddress {
  const OrderAddress({
    required this.fullName,
    this.phone,
    this.street = const [],
    this.city,
    this.state,
    this.countryNames = const {},
    this.countryIso2,
  });

  factory OrderAddress.fromJson(Map<String, dynamic> json) {
    String? text(String key) {
      final value = json[key];

      return value is String && value.trim().isNotEmpty ? value.trim() : null;
    }

    final names = json['country_name'];

    return OrderAddress(
      fullName: text('full_name') ?? '',
      phone: text('phone'),
      street: [?(text('address_line1') ?? text('address_line_1')), ?(text('address_line2') ?? text('address_line_2'))],
      city: text('city'),
      state: text('state'),
      countryNames: names is Map
          ? {for (final entry in names.entries) if (entry.value is String) '${entry.key}': entry.value as String}
          : (names is String ? {'': names} : const {}),
      countryIso2: text('country_iso2'),
    );
  }

  final String fullName;
  final String? phone;
  final List<String> street;
  final String? city;
  final String? state;
  final Map<String, String> countryNames;
  final String? countryIso2;

  String? country(String locale) =>
      countryNames[locale] ?? countryNames['en'] ?? countryNames[''] ?? (countryNames.isEmpty ? countryIso2 : countryNames.values.first);

  /// Name, phone, street lines, "city, state", country (like the checkout address card).
  List<String> lines(String locale) => [
        if (fullName.isNotEmpty) fullName,
        ?phone,
        ...street,
        if (city != null || state != null) [?city, ?state].join(', '),
        ?country(locale),
      ];
}
