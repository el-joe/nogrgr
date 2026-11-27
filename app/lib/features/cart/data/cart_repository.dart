import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/json.dart';
import '../../../core/models/product_card.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/store_api.dart';
import '../../../core/providers.dart';
import '../../auth/data/session_controller.dart';

class CartLine {
  const CartLine({
    required this.id,
    required this.storeProductId,
    required this.name,
    required this.quantity,
    required this.available,
    this.variantId,
    this.variant,
    this.image,
    this.unitPrice,
    this.subtotal,
    this.discount,
    this.total,
    this.flashPercent,
    this.issue,
    this.message,
  });

  factory CartLine.fromJson(Map<String, dynamic> json) => CartLine(
        id: jsonInt(json['id']),
        storeProductId: jsonInt(json['store_product_id']),
        variantId: jsonIntOrNull(json['variant_id']),
        name: '${json['name'] ?? ''}',
        variant: jsonString(json['variant']),
        image: jsonString(json['image_url']),
        quantity: jsonInt(json['quantity'], 1),
        available: json['available'] != false,
        unitPrice: Money.maybe(json['unit_price']),
        subtotal: Money.maybe(json['subtotal']),
        discount: Money.maybe(json['discount']),
        total: Money.maybe(json['total']),
        flashPercent: jsonIntOrNull(jsonMap(json['flash_sale'])['percentage']),
        issue: jsonString(json['issue']),
        message: jsonString(json['message']),
      );

  final int id;
  final int storeProductId;
  final int? variantId;
  final String name;
  final String? variant;
  final String? image;
  final int quantity;
  final bool available;
  final Money? unitPrice;
  final Money? subtotal;
  final Money? discount;
  final Money? total;
  final int? flashPercent;
  final String? issue;
  final String? message;
}

class CartCoupon {
  const CartCoupon({required this.code, required this.applied, this.message, this.discount});

  final String code;
  final bool applied;
  final String? message;
  final Money? discount;
}

class CartTotals {
  const CartTotals({required this.subtotal, required this.flashDiscount, required this.itemsTotal, required this.couponDiscount, required this.shipping, required this.grandTotal});

  factory CartTotals.fromJson(Object? source) {
    final json = jsonMap(source);

    return CartTotals(
      subtotal: Money.fromJson(json['subtotal']),
      flashDiscount: Money.fromJson(json['flash_discount']),
      itemsTotal: Money.fromJson(json['items_total']),
      couponDiscount: Money.fromJson(json['coupon_discount']),
      shipping: Money.fromJson(json['shipping']),
      grandTotal: Money.fromJson(json['grand_total']),
    );
  }

  final Money subtotal;
  final Money flashDiscount;
  final Money itemsTotal;
  final Money couponDiscount;
  final Money shipping;
  final Money grandTotal;
}

/// `CartResource` of the Store API.
class CartData {
  const CartData({
    required this.lines,
    required this.itemsCount,
    required this.totals,
    required this.canCheckout,
    this.coupon,
    this.shippingAvailable = true,
    this.shippingFree = false,
    this.shippingMessage,
    this.freeShippingProgress,
    this.freeShippingRemainingGrams,
    this.freeShippingReached = false,
    this.deliveryLabel,
    this.split = false,
  });

  factory CartData.fromJson(Map<String, dynamic> json) {
    final coupon = json['coupon'];
    final shipping = jsonMap(json['shipping']);
    final free = json['free_shipping'];
    final delivery = jsonMap(json['delivery']);

    return CartData(
      lines: jsonList(json['lines']).map(CartLine.fromJson).toList(),
      itemsCount: jsonInt(json['items_count']),
      totals: CartTotals.fromJson(json['totals']),
      canCheckout: json['can_checkout'] == true,
      coupon: coupon is Map<String, dynamic>
          ? CartCoupon(code: '${coupon['code'] ?? ''}', applied: coupon['applied'] == true, message: jsonString(coupon['message']), discount: Money.maybe(coupon['discount']))
          : null,
      shippingAvailable: shipping['available'] != false,
      shippingFree: shipping['free'] == true,
      shippingMessage: jsonString(shipping['message']),
      freeShippingProgress: free is Map<String, dynamic> ? jsonInt(free['progress']) : null,
      freeShippingRemainingGrams: free is Map<String, dynamic> ? jsonInt(free['remaining_grams']) : null,
      freeShippingReached: free is Map<String, dynamic> && free['reached'] == true,
      deliveryLabel: jsonString(delivery['label']),
      split: json['split'] == true,
    );
  }

  static const empty = CartData(
    lines: [],
    itemsCount: 0,
    canCheckout: false,
    totals: CartTotals(
      subtotal: Money(amount: 0, formatted: ''),
      flashDiscount: Money(amount: 0, formatted: ''),
      itemsTotal: Money(amount: 0, formatted: ''),
      couponDiscount: Money(amount: 0, formatted: ''),
      shipping: Money(amount: 0, formatted: ''),
      grandTotal: Money(amount: 0, formatted: ''),
    ),
  );

  final List<CartLine> lines;
  final int itemsCount;
  final CartTotals totals;
  final bool canCheckout;
  final CartCoupon? coupon;
  final bool shippingAvailable;
  final bool shippingFree;
  final String? shippingMessage;
  final int? freeShippingProgress;
  final int? freeShippingRemainingGrams;
  final bool freeShippingReached;
  final String? deliveryLabel;
  final bool split;

  bool get isEmpty => lines.isEmpty;
}

/// Reorder result: what was added and what was skipped (with the reason).
class ReorderResult {
  const ReorderResult({required this.cart, required this.added, required this.skipped});

  final CartData cart;
  final int added;
  final List<String> skipped;
}

class CartRepository {
  CartRepository(this._api);

  final StoreApi _api;

  Future<CartData> _cart(Future<ApiResponse> Function() call) async {
    final map = (await call()).map;
    await _api.rememberCart(map);

    return CartData.fromJson(map);
  }

  Future<CartData> show() => _cart(() => _api.get('cart'));

  Future<CartData> add(int storeProductId, {int? variantId, int quantity = 1}) =>
      _cart(() => _api.post('cart/items', body: {'store_product_id': storeProductId, 'variant_id': ?variantId, 'quantity': quantity}));

  Future<CartData> update(int lineId, int quantity) => _cart(() => _api.patch('cart/items/$lineId', body: {'quantity': quantity}));

  Future<CartData> remove(int lineId) => _cart(() => _api.delete('cart/items/$lineId'));

  Future<CartData> applyCoupon(String code) => _cart(() => _api.post('cart/coupon', body: {'code': code}));

  Future<CartData> removeCoupon() => _cart(() => _api.delete('cart/coupon'));

  Future<ReorderResult> reorder(String orderUuid) async {
    final data = (await _api.post('orders/$orderUuid/reorder')).map;
    final cart = jsonMap(data['cart']);
    await _api.rememberCart(cart);

    return ReorderResult(
      cart: CartData.fromJson(cart),
      added: data['added'] is List ? (data['added'] as List).length : jsonInt(data['added']),
      skipped: [for (final s in jsonList(data['skipped'])) '${s['name'] ?? ''}'],
    );
  }
}

final cartRepositoryProvider = Provider<CartRepository>((ref) => CartRepository(ref.watch(storeApiProvider)));

/// The cart (APP-011). Reloads on sign-in / sign-out (the guest cart token merges into
/// the customer's cart server-side) and when the display currency / country changes.
class CartController extends AsyncNotifier<CartData> {
  CartRepository get _repo => ref.read(cartRepositoryProvider);

  @override
  Future<CartData> build() {
    ref.watch(sessionProvider.select((s) => s.signedIn));
    ref.watch(localeCodeProvider);
    ref.watch(preferencesProvider.select((p) => (p.currency, p.country)));

    return _repo.show();
  }

  Future<CartData> _run(Future<CartData> Function() call) async {
    final cart = await call();
    state = AsyncData(cart);

    return cart;
  }

  Future<CartData> add(int productId, {int? variantId, int quantity = 1}) => _run(() => _repo.add(productId, variantId: variantId, quantity: quantity));

  Future<CartData> setQuantity(CartLine line, int quantity) => _run(() => quantity <= 0 ? _repo.remove(line.id) : _repo.update(line.id, quantity));

  Future<CartData> remove(CartLine line) => _run(() => _repo.remove(line.id));

  Future<CartData> applyCoupon(String code) => _run(() => _repo.applyCoupon(code));

  Future<CartData> removeCoupon() => _run(_repo.removeCoupon);

  Future<ReorderResult> reorder(String orderUuid) async {
    final result = await _repo.reorder(orderUuid);
    state = AsyncData(result.cart);

    return result;
  }

  void replace(CartData cart) => state = AsyncData(cart);
}

final cartProvider = AsyncNotifierProvider<CartController, CartData>(CartController.new);

/// Tab bar badge.
final cartCountProvider = Provider<int>((ref) => ref.watch(cartProvider).value?.itemsCount ?? 0);
