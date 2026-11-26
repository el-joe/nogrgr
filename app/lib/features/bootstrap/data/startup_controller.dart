import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/push/push_service.dart';
import 'bootstrap.dart';

/// Where app start-up ended (APP-001 / APP-002).
enum StartupStatus { loading, offline, unreachable, unavailable, maintenance, updateRequired, ready }

class StartupState {
  const StartupState({required this.status, this.bootstrap, this.errorCode, this.updateDismissed = false});

  final StartupStatus status;
  final Bootstrap? bootstrap;

  /// Shown on the "can't reach this store" state (e.g. TENANT_404, HTTP_500).
  final String? errorCode;
  final bool updateDismissed;

  bool get optionalUpdate =>
      status == StartupStatus.ready && !updateDismissed && bootstrap?.update.state == UpdateState.optional;

  StartupState copyWith({bool? updateDismissed}) => StartupState(
        status: status,
        bootstrap: bootstrap,
        errorCode: errorCode,
        updateDismissed: updateDismissed ?? this.updateDismissed,
      );
}

/// Runs `GET bootstrap`, applies the store language / currency / country defaults,
/// loads the static texts and decides the update / maintenance gate.
class StartupController extends Notifier<StartupState> {
  @override
  StartupState build() => const StartupState(status: StartupStatus.loading);

  /// [silent] keeps the current screen while re-reading (settings changes); a failed
  /// silent reload keeps the previous data.
  Future<void> start({bool silent = false}) async {
    if (!silent || state.bootstrap == null) {
      state = const StartupState(status: StartupStatus.loading);
    }

    final api = ref.read(apiClientProvider);

    try {
      final response = await api.get('bootstrap', auth: false);
      final bootstrap = Bootstrap.fromJson(response.map);
      await _applyDefaults(bootstrap);
      await ref.read(textsProvider.notifier).load(ref.read(localeCodeProvider), version: bootstrap.textsVersion);
      state = StartupState(status: _gate(bootstrap), bootstrap: bootstrap);

      if (bootstrap.pushEnabled) {
        // Fire and forget: a push failure must never block the store.
        ref.read(pushServiceProvider).register().ignore();
      }
    } on ApiException catch (error) {
      if (silent && state.bootstrap != null) {
        return;
      }

      state = StartupState(status: _failure(error), errorCode: _code(error));
    }
  }

  void dismissUpdate() => state = state.copyWith(updateDismissed: true);

  /// Re-reads bootstrap after a language / currency / country change.
  Future<void> reload() => start(silent: true);

  StartupStatus _gate(Bootstrap bootstrap) {
    if (bootstrap.update.state == UpdateState.required) {
      return StartupStatus.updateRequired;
    }

    if (bootstrap.maintenance.enabled) {
      return StartupStatus.maintenance;
    }

    return StartupStatus.ready;
  }

  StartupStatus _failure(ApiException error) => switch (error.kind) {
        ApiErrorKind.network => StartupStatus.offline,
        // A suspended / closed store answers 403 (or 410 / 423): "Store unavailable".
        ApiErrorKind.forbidden => StartupStatus.unavailable,
        _ when error.statusCode == 410 || error.statusCode == 423 => StartupStatus.unavailable,
        _ => StartupStatus.unreachable,
      };

  String _code(ApiException error) => switch (error.kind) {
        ApiErrorKind.notFound => 'TENANT_404',
        ApiErrorKind.network => 'NETWORK',
        _ => 'HTTP_${error.statusCode ?? 0}',
      };

  /// First launch: adopt the store default language (if the device has none saved),
  /// keep a saved language only while the store still offers it.
  Future<void> _applyDefaults(Bootstrap bootstrap) async {
    final prefs = ref.read(preferencesProvider);
    final codes = bootstrap.languages.map((language) => language.code).toSet();
    String? locale = prefs.locale;

    if (locale != null && codes.isNotEmpty && !codes.contains(locale)) {
      locale = bootstrap.defaultLocale;
    }

    await ref.read(preferencesProvider.notifier).update(
          (current) => current.copyWith(
            locale: locale ?? (codes.contains(bootstrap.defaultLocale) ? bootstrap.defaultLocale : null),
            currency: current.currency ?? bootstrap.defaultCurrency,
            country: current.country ?? bootstrap.defaultCountry?.iso2,
          ),
        );
  }
}

final startupProvider = NotifierProvider<StartupController, StartupState>(StartupController.new);
