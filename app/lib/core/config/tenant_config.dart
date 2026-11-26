/// Build-time identity of one white-label store app.
///
/// Every value comes from `--dart-define` (written by `scripts/build_tenant.sh` from
/// `tenants/<key>/tenant.json`), so one codebase builds every store's app. Nothing here
/// is a secret: the store id is public (it is the `X-Tenant` header) and the Firebase
/// client options are only read when present.
class TenantConfig {
  const TenantConfig({
    required this.key,
    required this.tenantId,
    required this.appName,
    required this.apiBaseUrl,
    required this.themeFamily,
    required this.themeVariant,
    required this.primaryColor,
    this.defaultLocale = 'en',
    this.firebase,
  });

  /// Values of the current build (`--dart-define`), with development defaults.
  factory TenantConfig.fromEnvironment() {
    const firebaseAppId = String.fromEnvironment('FIREBASE_APP_ID');

    return TenantConfig(
      key: const String.fromEnvironment('TENANT_KEY', defaultValue: 'demo'),
      tenantId: const String.fromEnvironment('TENANT_ID', defaultValue: '1'),
      appName: const String.fromEnvironment('APP_NAME', defaultValue: 'Nogrgr'),
      apiBaseUrl: const String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000'),
      themeFamily: const String.fromEnvironment('THEME_FAMILY', defaultValue: 'elora'),
      themeVariant: const int.fromEnvironment('THEME_VARIANT', defaultValue: 1),
      primaryColor: const String.fromEnvironment('PRIMARY_COLOR', defaultValue: '#FF4D00'),
      defaultLocale: const String.fromEnvironment('DEFAULT_LOCALE', defaultValue: 'en'),
      firebase: firebaseAppId.isEmpty
          ? null
          : const FirebaseClientConfig(
              appId: firebaseAppId,
              apiKey: String.fromEnvironment('FIREBASE_API_KEY'),
              projectId: String.fromEnvironment('FIREBASE_PROJECT_ID'),
              messagingSenderId: String.fromEnvironment('FIREBASE_SENDER_ID'),
              iosBundleId: String.fromEnvironment('IOS_BUNDLE_ID'),
            ),
    );
  }

  /// Short tenant key (folder name under `tenants/`), used for storage namespaces.
  final String key;

  /// Store id sent as `X-Tenant`.
  final String tenantId;
  final String appName;

  /// Scheme + host of the platform, e.g. `https://api.nogrgr.com`.
  final String apiBaseUrl;

  /// Theme family / variant used until `GET bootstrap` answers (ecommet, elora, souqify).
  final String themeFamily;
  final int themeVariant;

  /// `#RRGGBB` brand colour until bootstrap answers.
  final String primaryColor;
  final String defaultLocale;

  /// Firebase client options; null keeps push a no-op.
  final FirebaseClientConfig? firebase;

  String get mobileApiUrl => '${_trimmed(apiBaseUrl)}/api/mobile/v1';

  String get storeApiUrl => '${_trimmed(apiBaseUrl)}/store-api/v1';

  static String _trimmed(String url) => url.endsWith('/') ? url.substring(0, url.length - 1) : url;
}

class FirebaseClientConfig {
  const FirebaseClientConfig({
    required this.appId,
    required this.apiKey,
    required this.projectId,
    required this.messagingSenderId,
    this.iosBundleId = '',
  });

  final String appId;
  final String apiKey;
  final String projectId;
  final String messagingSenderId;
  final String iosBundleId;
}
