import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/models/json.dart';
import '../../../core/network/store_api.dart';
import '../../cart/data/cart_repository.dart';

class CheckoutAddress {
  const CheckoutAddress({required this.id, required this.fullName, required this.oneLine, required this.isDefault, this.label, this.phone});

  final int id;
  final String? label;
  final String fullName;
  final String? phone;
  final String oneLine;
  final bool isDefault;
}

class PaymentMethod {
  const PaymentMethod({required this.code, required this.name, required this.inline, this.logoUrl, this.publishableKey});

  final String code;
  final String name;
  final bool inline;
  final String? logoUrl;
  final String? publishableKey;

  bool get isCod => code == 'cod';
}

class CheckoutOptions {
  const CheckoutOptions({required this.addresses, required this.cart, required this.methods, required this.canPay, this.shippingAddressId, this.block, this.blockMessage});

  factory CheckoutOptions.fromJson(Map<String, dynamic> json) => CheckoutOptions(
        addresses: [
          for (final a in jsonList(json['addresses']))
            CheckoutAddress(
              id: jsonInt(a['id']),
              label: jsonString(a['label']),
              fullName: '${a['full_name'] ?? ''}',
              phone: jsonString(a['phone']),
              oneLine: '${a['one_line'] ?? ''}',
              isDefault: a['is_default'] == true,
            ),
        ],
        shippingAddressId: jsonIntOrNull(json['shipping_address_id']),
        cart: CartData.fromJson(jsonMap(json['cart'])),
        methods: [
          for (final m in jsonList(json['payment_methods']))
            PaymentMethod(
              code: '${m['code'] ?? ''}',
              name: '${m['name'] ?? ''}',
              inline: m['inline'] == true,
              logoUrl: jsonString(m['logo_url']),
              publishableKey: jsonString(m['publishable_key']),
            ),
        ],
        canPay: json['can_pay'] == true,
        block: jsonString(json['block']),
        blockMessage: jsonString(json['block_message']),
      );

  final List<CheckoutAddress> addresses;
  final int? shippingAddressId;
  final CartData cart;
  final List<PaymentMethod> methods;
  final bool canPay;
  final String? block;
  final String? blockMessage;
}

class PlacedOrder {
  const PlacedOrder({required this.paymentOrder, required this.numbers});

  final String paymentOrder;
  final List<String> numbers;
}

class StartedPayment {
  const StartedPayment({required this.reference, required this.status, this.redirectUrl, this.clientSecret, this.publishableKey});

  final String reference;
  final String status;
  final String? redirectUrl;
  final String? clientSecret;
  final String? publishableKey;
}

class PaymentConfirmation {
  const PaymentConfirmation({required this.status, required this.message, this.failureReason, this.orderNumber});

  /// `paid` / `pending` / `failed` / `cancelled` … (PaymentStatus of the server).
  final String status;
  final String message;
  final String? failureReason;
  final String? orderNumber;

  bool get paid => status == 'paid' || status == 'succeeded' || status == 'captured' || status == 'authorized';

  bool get failed => status == 'failed' || status == 'cancelled' || status == 'expired';
}

/// Checkout + payments of the Store API (signed-in customers).
class CheckoutRepository {
  CheckoutRepository(this._api);

  final StoreApi _api;

  Future<CheckoutOptions> options({int? shippingAddressId}) async =>
      CheckoutOptions.fromJson((await _api.get('checkout/options', query: {'shipping_address_id': ?shippingAddressId, 'billing_same_as_shipping': 1})).map);

  Future<PlacedOrder> place({required String idempotencyKey, required String gateway, int? shippingAddressId}) async {
    final data = (await _api.post(
      'checkout/place',
      headers: {'Idempotency-Key': idempotencyKey},
      body: {'idempotency_key': idempotencyKey, 'gateway': gateway, 'shipping_address_id': ?shippingAddressId, 'billing_same_as_shipping': true},
    ))
        .map;

    return PlacedOrder(
      paymentOrder: '${data['payment_order'] ?? ''}',
      numbers: [for (final o in jsonList(data['orders'])) '${o['number'] ?? ''}'],
    );
  }

  Future<StartedPayment> start(String orderUuid, String gateway) async {
    final data = (await _api.post('payments/$orderUuid/start', body: {'gateway': gateway})).map;
    final payment = jsonMap(data['payment']);

    return StartedPayment(
      reference: '${payment['reference'] ?? ''}',
      status: '${payment['status'] ?? ''}',
      redirectUrl: jsonString(data['redirect_url']),
      clientSecret: jsonString(data['client_secret']),
      publishableKey: jsonString(data['publishable_key']),
    );
  }

  Future<PaymentConfirmation> confirm(String orderUuid, String reference) async {
    final response = await _api.post('payments/$orderUuid/confirm', body: {'reference': reference});
    final payment = jsonMap(response.map['payment']);

    return PaymentConfirmation(
      status: '${payment['status'] ?? ''}',
      message: response.message,
      failureReason: jsonString(payment['failure_reason']),
      orderNumber: jsonString(jsonMap(response.map['order'])['number']),
    );
  }
}

final checkoutRepositoryProvider = Provider<CheckoutRepository>((ref) => CheckoutRepository(ref.watch(storeApiProvider)));

/// Random idempotency key for one checkout attempt.
String newIdempotencyKey() {
  final random = Random.secure();

  return 'app-${List.generate(24, (_) => random.nextInt(16).toRadixString(16)).join()}';
}

enum SheetResult { completed, cancelled, failed }

/// Native payment surfaces (Stripe PaymentSheet, in-app browser); faked in tests.
class PaymentLauncher {
  const PaymentLauncher();

  Future<SheetResult> stripe({required String publishableKey, required String clientSecret, required String merchant, required bool dark}) async {
    try {
      Stripe.publishableKey = publishableKey;
      await Stripe.instance.applySettings();
      await Stripe.instance.initPaymentSheet(
        paymentSheetParameters: SetupPaymentSheetParameters(
          paymentIntentClientSecret: clientSecret,
          merchantDisplayName: merchant,
        ),
      );
      await Stripe.instance.presentPaymentSheet();

      return SheetResult.completed;
    } on StripeException catch (error) {
      return error.error.code == FailureCode.Canceled ? SheetResult.cancelled : SheetResult.failed;
    } catch (error) {
      debugPrint('stripe: $error');

      return SheetResult.failed;
    }
  }

  Future<bool> browser(String url) => launchUrl(Uri.parse(url), mode: LaunchMode.inAppBrowserView);
}

final paymentLauncherProvider = Provider<PaymentLauncher>((ref) => const PaymentLauncher());
