import 'dart:convert';

import 'package:flutter/services.dart';

/// Every user-facing string of the app. Order: the store's static texts from the API
/// (store edits → base → English, already resolved server side) → the bundled
/// `assets/i18n/<locale>.json` → bundled English → the key. Placeholders use the
/// server style `:name`.
class AppTexts {
  const AppTexts({required this.locale, this.remote = const {}, this.bundled = const {}, this.fallback = const {}});

  final String locale;
  final Map<String, String> remote;
  final Map<String, String> bundled;
  final Map<String, String> fallback;

  static const supportedLocales = ['en', 'ar'];

  bool get isRtl => locale == 'ar';

  TextDirection get direction => isRtl ? TextDirection.rtl : TextDirection.ltr;

  String t(String key, [Map<String, Object?> params = const {}]) {
    var value = remote[key] ?? bundled[key] ?? fallback[key] ?? key;

    params.forEach((name, replacement) => value = value.replaceAll(':$name', '${replacement ?? ''}'));

    return value;
  }

  AppTexts withRemote(Map<String, String> texts) =>
      AppTexts(locale: locale, remote: texts, bundled: bundled, fallback: fallback);

  /// Bundled strings of [locale] (+ English fallback).
  static Future<AppTexts> loadBundled(String locale, {AssetBundle? bundle}) async {
    final source = bundle ?? rootBundle;
    final code = supportedLocales.contains(locale) ? locale : 'en';
    final fallback = await _read(source, 'en');

    return AppTexts(locale: locale, bundled: code == 'en' ? fallback : await _read(source, code), fallback: fallback);
  }

  static Future<Map<String, String>> _read(AssetBundle bundle, String code) async {
    final raw = await bundle.loadString('assets/i18n/$code.json', cache: false);

    return (jsonDecode(raw) as Map<String, dynamic>).map((key, value) => MapEntry(key, '$value'));
  }
}
