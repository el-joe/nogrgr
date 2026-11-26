import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'auth/token_storage.dart';
import 'config/tenant_config.dart';
import 'i18n/app_texts.dart';
import 'i18n/texts_repository.dart';
import 'network/api_client.dart';
import 'storage/app_preferences.dart';

/// Overridden in `main.dart` (and in tests).
final tenantConfigProvider = Provider<TenantConfig>((ref) => TenantConfig.fromEnvironment());

final sharedPreferencesProvider = Provider<SharedPreferences>((ref) => throw UnimplementedError('override in main'));

final tokenStorageProvider = Provider<TokenStorage>((ref) => SecureTokenStorage(ref.watch(tenantConfigProvider).key));

/// App version (package_info) for `X-App-Version` / the update decision.
final appVersionProvider = Provider<String>((ref) => '1.0.0');

/// `ios` / `android` (null elsewhere: web, desktop previews).
final appPlatformProvider = Provider<String?>((ref) => switch (defaultTargetPlatform) {
      TargetPlatform.iOS => 'ios',
      TargetPlatform.android => 'android',
      _ => null,
    });

final preferencesStoreProvider = Provider<PreferencesStore>(
  (ref) => PreferencesStore(ref.watch(sharedPreferencesProvider), ref.watch(tenantConfigProvider).key),
);

class PreferencesController extends Notifier<AppPreferences> {
  @override
  AppPreferences build() => ref.watch(preferencesStoreProvider).load();

  Future<void> update(AppPreferences Function(AppPreferences current) change) async {
    state = change(state);
    await ref.read(preferencesStoreProvider).save(state);
  }

  Future<void> setThemeMode(ThemeMode mode) => update((p) => p.copyWith(themeMode: mode));
}

final preferencesProvider = NotifierProvider<PreferencesController, AppPreferences>(PreferencesController.new);

/// Current language: the saved choice, else the build default.
final localeCodeProvider = Provider<String>((ref) {
  final saved = ref.watch(preferencesProvider.select((p) => p.locale));

  return saved ?? ref.watch(tenantConfigProvider).defaultLocale;
});

/// Fires when the refresh token is rejected (the session controller listens).
final sessionExpiryProvider = Provider<ValueNotifier<int>>((ref) {
  final notifier = ValueNotifier<int>(0);
  ref.onDispose(notifier.dispose);

  return notifier;
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final config = ref.watch(tenantConfigProvider);
  final expiry = ref.watch(sessionExpiryProvider);

  return ApiClient(
    mobileBaseUrl: config.mobileApiUrl,
    storeBaseUrl: config.storeApiUrl,
    tokens: ref.watch(tokenStorageProvider),
    onSessionExpired: () => expiry.value++,
    context: () {
      final prefs = ref.read(preferencesProvider);

      return RequestContext(
        tenantId: config.tenantId,
        locale: ref.read(localeCodeProvider),
        currency: prefs.currency,
        country: prefs.country,
        platform: ref.read(appPlatformProvider),
        appVersion: ref.read(appVersionProvider),
      );
    },
  );
});

final textsRepositoryProvider = Provider<TextsRepository>(
  (ref) => TextsRepository(ref.watch(apiClientProvider), ref.watch(sharedPreferencesProvider), ref.watch(tenantConfigProvider).key),
);

/// Bundled strings of the start language, loaded before the first frame (main.dart).
final initialTextsProvider = Provider<AppTexts>((ref) => const AppTexts(locale: 'en'));

/// The strings the UI reads (`ref.watch(textsProvider).t('key')`).
class TextsController extends Notifier<AppTexts> {
  @override
  AppTexts build() => ref.watch(initialTextsProvider);

  /// Bundled strings of [locale] + the store's texts (cached / ETag).
  Future<void> load(String locale, {String? version}) async {
    var texts = state.locale == locale ? state : await AppTexts.loadBundled(locale);
    state = texts;
    final remote = await ref.read(textsRepositoryProvider).load(locale, version: version);
    texts = texts.withRemote(remote);
    state = texts;
  }
}

final textsProvider = NotifierProvider<TextsController, AppTexts>(TextsController.new);

extension TextsRef on WidgetRef {
  String t(String key, [Map<String, Object?> params = const {}]) => watch(textsProvider).t(key, params);
}
