/// `GET bootstrap` (APP-001 / 002 / 003): store, branding, languages, currencies,
/// countries, feature flags, push availability, update + maintenance, texts version.
class Bootstrap {
  const Bootstrap({
    required this.storeName,
    required this.branding,
    required this.locale,
    required this.languages,
    required this.currencies,
    required this.defaultCurrency,
    required this.countries,
    required this.features,
    required this.pushEnabled,
    required this.update,
    required this.maintenance,
    required this.textsVersion,
    this.storeLogoUrl,
    this.storeUrl,
    this.supportEmail,
    this.defaultCountryId,
    this.defaultLocale = 'en',
    this.socialProviders = const [],
  });

  factory Bootstrap.fromJson(Map<String, dynamic> json) {
    final store = _map(json['store']);
    final locale = _map(json['locale']);
    final currency = _map(json['currency']);
    final countries = _map(json['countries']);
    final app = _map(json['app']);
    final features = <String, bool>{};
    _map(json['features']).forEach((key, value) => features[key] = value == true);

    return Bootstrap(
      storeName: '${store['name'] ?? ''}',
      storeLogoUrl: store['logo_url'] as String?,
      storeUrl: store['url'] as String?,
      supportEmail: store['support_email'] as String?,
      branding: Branding.fromJson(_map(json['branding'])),
      locale: '${locale['current'] ?? 'en'}',
      defaultLocale: '${locale['default'] ?? 'en'}',
      languages: _list(locale['languages']).map(Language.fromJson).toList(),
      currencies: _list(currency['available']).map(CurrencyOption.fromJson).toList(),
      defaultCurrency: '${currency['default'] ?? 'USD'}',
      countries: _list(countries['available']).map(CountryOption.fromJson).toList(),
      defaultCountryId: countries['default_id'] as int?,
      features: features,
      pushEnabled: _map(json['push'])['enabled'] == true,
      update: AppUpdate.fromJson(_map(app['update'])),
      maintenance: Maintenance.fromJson(_map(app['maintenance'])),
      textsVersion: '${_map(json['texts'])['version'] ?? ''}',
      socialProviders: [
        for (final provider in (_map(json['auth'])['social_providers'] as List?) ?? const []) '$provider',
      ],
    );
  }

  final String storeName;
  final String? storeLogoUrl;
  final String? storeUrl;
  final String? supportEmail;
  final Branding branding;
  final String locale;
  final String defaultLocale;
  final List<Language> languages;
  final List<CurrencyOption> currencies;
  final String defaultCurrency;
  final List<CountryOption> countries;
  final int? defaultCountryId;
  final Map<String, bool> features;
  final bool pushEnabled;
  final AppUpdate update;
  final Maintenance maintenance;
  final String textsVersion;

  /// Shopper social sign-in providers the store configured (`google`, `apple`).
  final List<String> socialProviders;

  bool feature(String name) => features[name] ?? false;

  CountryOption? get defaultCountry {
    for (final country in countries) {
      if (country.id == defaultCountryId) {
        return country;
      }
    }

    return countries.isEmpty ? null : countries.first;
  }
}

class Branding {
  const Branding({
    required this.family,
    required this.variant,
    this.themeSlug,
    this.primaryColor,
    this.fontLtr,
    this.fontRtl,
    this.splashBackground,
    this.splashLogoUrl,
  });

  factory Branding.fromJson(Map<String, dynamic> json) {
    final theme = _map(json['theme']);
    final fonts = _map(json['fonts']);
    final splash = _map(json['splash']);

    return Branding(
      themeSlug: theme['slug'] as String?,
      family: '${theme['family'] ?? 'elora'}',
      variant: int.tryParse('${theme['variant'] ?? 1}') ?? 1,
      primaryColor: _map(json['colors'])['primary'] as String?,
      fontLtr: fonts['ltr'] as String?,
      fontRtl: fonts['rtl'] as String?,
      splashBackground: splash['background'] as String?,
      splashLogoUrl: splash['logo_url'] as String?,
    );
  }

  final String? themeSlug;
  final String family;
  final int variant;
  final String? primaryColor;
  final String? fontLtr;
  final String? fontRtl;
  final String? splashBackground;
  final String? splashLogoUrl;
}

class Language {
  const Language({required this.code, required this.name, this.dir = 'ltr'});

  factory Language.fromJson(Map<String, dynamic> json) => Language(
        code: '${json['code'] ?? json['locale'] ?? 'en'}',
        name: '${json['native'] ?? json['name'] ?? json['code'] ?? ''}',
        dir: '${json['dir'] ?? 'ltr'}',
      );

  final String code;
  final String name;
  final String dir;
}

class CurrencyOption {
  const CurrencyOption({required this.code, required this.name, this.symbol = ''});

  factory CurrencyOption.fromJson(Map<String, dynamic> json) =>
      CurrencyOption(code: '${json['code']}', name: '${json['name'] ?? json['code']}', symbol: '${json['symbol'] ?? ''}');

  final String code;
  final String name;
  final String symbol;
}

class CountryOption {
  const CountryOption({required this.id, required this.iso2, required this.name, this.flag = '', this.phoneCode = ''});

  factory CountryOption.fromJson(Map<String, dynamic> json) => CountryOption(
        id: json['id'] as int? ?? 0,
        iso2: '${json['iso2'] ?? ''}',
        name: '${json['name'] ?? ''}',
        flag: '${json['flag'] ?? ''}',
        phoneCode: '${json['phone_code'] ?? ''}',
      );

  final int id;
  final String iso2;
  final String name;
  final String flag;
  final String phoneCode;
}

enum UpdateState {
  none,
  optional,
  required;

  static UpdateState parse(String? value) =>
      UpdateState.values.firstWhere((state) => state.name == value, orElse: () => UpdateState.none);
}

class AppUpdate {
  const AppUpdate({required this.state, this.storeUrl, this.latestVersion});

  factory AppUpdate.fromJson(Map<String, dynamic> json) => AppUpdate(
        state: UpdateState.parse(json['state'] as String?),
        storeUrl: json['store_url'] as String?,
        latestVersion: json['latest_version'] as String?,
      );

  final UpdateState state;
  final String? storeUrl;
  final String? latestVersion;
}

class Maintenance {
  const Maintenance({required this.enabled, this.message});

  factory Maintenance.fromJson(Map<String, dynamic> json) =>
      Maintenance(enabled: json['enabled'] == true, message: json['message'] as String?);

  final bool enabled;
  final String? message;
}

Map<String, dynamic> _map(Object? value) => value is Map<String, dynamic> ? value : const {};

Iterable<Map<String, dynamic>> _list(Object? value) =>
    value is List ? value.whereType<Map<String, dynamic>>() : const <Map<String, dynamic>>[];
