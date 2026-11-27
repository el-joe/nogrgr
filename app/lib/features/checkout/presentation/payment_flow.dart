import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart' show PageScaffold;
import '../../auth/presentation/auth_form.dart';
import '../../bootstrap/data/startup_controller.dart';
import '../../cart/data/cart_repository.dart';
import '../../orders/data/orders_repository.dart';
import '../data/checkout_repository.dart';

enum PaymentResult { success, pending, failed }

/// Hand-over to the result screen (APP-012b).
class PaymentOutcome {
  const PaymentOutcome({required this.result, required this.orderUuid, required this.gateway, this.numbers = const [], this.reference, this.message, this.cod = false});

  final PaymentResult result;
  final String orderUuid;
  final String gateway;
  final List<String> numbers;
  final String? reference;
  final String? message;
  final bool cod;
}

/// Pays an order with [method]: COD needs no payment; Stripe opens the PaymentSheet with
/// the `client_secret` + `publishable_key` from `payments/{order}/start`, then confirms;
/// redirect gateways (PayPal …) open the in-app browser and confirm when the app is back.
Future<PaymentOutcome> payOrder(WidgetRef ref, {required String orderUuid, required PaymentMethod method, List<String> numbers = const []}) async {
  if (method.isCod) {
    return PaymentOutcome(result: PaymentResult.success, orderUuid: orderUuid, gateway: method.code, numbers: numbers, cod: true);
  }

  final repo = ref.read(checkoutRepositoryProvider);
  final launcher = ref.read(paymentLauncherProvider);
  final started = await repo.start(orderUuid, method.code);
  PaymentOutcome outcome(PaymentConfirmation c) => PaymentOutcome(
        result: c.paid ? PaymentResult.success : (c.failed ? PaymentResult.failed : PaymentResult.pending),
        orderUuid: orderUuid,
        gateway: method.code,
        numbers: numbers.isEmpty && c.orderNumber != null ? [c.orderNumber!] : numbers,
        reference: started.reference,
        message: c.failureReason ?? c.message,
      );

  if (started.clientSecret != null) {
    final sheet = await launcher.stripe(
      publishableKey: started.publishableKey ?? method.publishableKey ?? '',
      clientSecret: started.clientSecret!,
      merchant: ref.read(startupProvider).bootstrap?.storeName ?? '',
      dark: ref.read(preferencesProvider).themeMode == ThemeMode.dark,
    );

    if (sheet == SheetResult.cancelled) {
      return PaymentOutcome(result: PaymentResult.failed, orderUuid: orderUuid, gateway: method.code, numbers: numbers, reference: started.reference, message: ref.read(textsProvider).t('checkout.return.cancelled'));
    }

    return outcome(await repo.confirm(orderUuid, started.reference));
  }

  if (started.redirectUrl != null) {
    final resumed = Completer<void>();
    final listener = AppLifecycleListener(onResume: () {
      if (!resumed.isCompleted) {
        resumed.complete();
      }
    });

    try {
      final opened = await launcher.browser(started.redirectUrl!);

      if (opened) {
        await resumed.future.timeout(const Duration(minutes: 30), onTimeout: () {});
      }
    } finally {
      listener.dispose();
    }

    return outcome(await repo.confirm(orderUuid, started.reference));
  }

  return outcome(await repo.confirm(orderUuid, started.reference));
}

/// Payment result (APP-012b): success / waiting for the bank / failed with retry.
class PaymentResultScreen extends ConsumerStatefulWidget {
  const PaymentResultScreen({super.key, required this.outcome});

  final PaymentOutcome outcome;

  @override
  ConsumerState<PaymentResultScreen> createState() => _PaymentResultScreenState();
}

class _PaymentResultScreenState extends ConsumerState<PaymentResultScreen> {
  late PaymentOutcome _outcome = widget.outcome;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    // The cart is emptied by the order; orders list gains a row.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.invalidate(cartProvider);
      ref.invalidate(ordersProvider);
    });
  }

  Future<void> _check() async {
    final reference = _outcome.reference;

    if (reference == null) {
      return;
    }

    setState(() => _busy = true);

    try {
      final c = await ref.read(checkoutRepositoryProvider).confirm(_outcome.orderUuid, reference);
      setState(() => _outcome = PaymentOutcome(
            result: c.paid ? PaymentResult.success : (c.failed ? PaymentResult.failed : PaymentResult.pending),
            orderUuid: _outcome.orderUuid,
            gateway: _outcome.gateway,
            numbers: _outcome.numbers,
            reference: reference,
            message: c.failureReason ?? c.message,
          ));
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

  Future<void> _retry() async {
    setState(() => _busy = true);

    try {
      final next = await payOrder(ref, orderUuid: _outcome.orderUuid, method: PaymentMethod(code: _outcome.gateway, name: _outcome.gateway, inline: false), numbers: _outcome.numbers);

      if (mounted) {
        setState(() => _outcome = next);
      }
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

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final number = _outcome.numbers.isEmpty ? '' : _outcome.numbers.join(', ');

    final view = switch (_outcome.result) {
      PaymentResult.success => StateView(
          key: const ValueKey('payment-success'),
          icon: 'check_circle',
          tone: StateTone.primary,
          title: texts.t('app.checkout.success_title'),
          body: [
            if (number.isNotEmpty) texts.t('checkout.order_number', {'number': number}),
            texts.t(_outcome.cod ? 'app.checkout.cod_body' : 'checkout.return.paid'),
          ].join('\n'),
          actionLabel: texts.t('orders.view_details'),
          onAction: () => context.go(Routes.order(_outcome.orderUuid)),
          secondaryLabel: texts.t('orders.continue_shopping'),
          onSecondary: () => context.go(Routes.home),
        ),
      PaymentResult.pending => StateView(
          key: const ValueKey('payment-pending'),
          icon: 'loader',
          tone: StateTone.info,
          title: texts.t('orders.awaiting_payment'),
          body: texts.t('checkout.return.pending'),
          actionLabel: texts.t('app.checkout.check_status'),
          onAction: _busy ? null : _check,
          secondaryLabel: texts.t('orders.view_details'),
          onSecondary: () => context.go(Routes.order(_outcome.orderUuid)),
        ),
      PaymentResult.failed => StateView(
          key: const ValueKey('payment-failed'),
          icon: 'alert_triangle',
          tone: StateTone.danger,
          title: texts.t('checkout.failed.title'),
          body: [
            if (_outcome.message != null && _outcome.message!.isNotEmpty) _outcome.message!,
            texts.t('checkout.failed.body'),
          ].join('\n'),
          actionLabel: texts.t('checkout.failed.retry'),
          onAction: _busy ? null : _retry,
          secondaryLabel: texts.t('orders.view_details'),
          onSecondary: () => context.go(Routes.order(_outcome.orderUuid)),
        ),
    };

    return PageScaffold(
      title: texts.t('checkout.title'),
      showBack: false,
      body: Stack(children: [view, if (_busy) const Positioned(top: 0, left: 0, right: 0, child: LinearProgressIndicator(minHeight: 2))]),
    );
  }
}
