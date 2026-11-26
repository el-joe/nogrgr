import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../network/api_client.dart';
import '../network/api_exception.dart';

/// Store static texts of one language (`GET texts`), cached on the device with the
/// server `ETag`: a version equal to `bootstrap.texts.version` skips the call, otherwise
/// `If-None-Match` turns an unchanged set into a 304.
class TextsRepository {
  TextsRepository(this._api, this._prefs, this._namespace);

  final ApiClient _api;
  final SharedPreferences _prefs;
  final String _namespace;

  String _key(String locale) => 'nogrgr.$_namespace.texts.$locale';

  /// Cached texts of [locale] (empty when never loaded).
  CachedTexts? cached(String locale) {
    final raw = _prefs.getString(_key(locale));

    if (raw == null) {
      return null;
    }

    try {
      return CachedTexts.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } on FormatException {
      return null;
    }
  }

  /// Texts of [locale]; [version] is `bootstrap.texts.version` when known. Offline or on a
  /// server error the cached copy is returned (or an empty map).
  Future<Map<String, String>> load(String locale, {String? version}) async {
    final cache = cached(locale);

    if (cache != null && version != null && cache.version == version) {
      return cache.texts;
    }

    try {
      final response = await _api.get(
        'texts',
        headers: {if (cache?.etag != null) 'If-None-Match': cache!.etag!},
        auth: false,
      );

      if (response.notModified && cache != null) {
        return cache.texts;
      }

      final data = response.map;
      final texts = <String, String>{};
      (data['texts'] as Map<String, dynamic>? ?? const {}).forEach((key, value) => texts[key] = '$value');
      final fresh = CachedTexts(
        version: '${data['version'] ?? ''}',
        etag: response.headers['etag'],
        texts: texts,
      );
      await _prefs.setString(_key(locale), jsonEncode(fresh.toJson()));

      return texts;
    } on ApiException {
      return cache?.texts ?? const {};
    }
  }
}

class CachedTexts {
  const CachedTexts({required this.version, required this.texts, this.etag});

  factory CachedTexts.fromJson(Map<String, dynamic> json) => CachedTexts(
        version: '${json['version'] ?? ''}',
        etag: json['etag'] as String?,
        texts: (json['texts'] as Map<String, dynamic>? ?? const {}).map((key, value) => MapEntry(key, '$value')),
      );

  final String version;
  final String? etag;
  final Map<String, String> texts;

  Map<String, dynamic> toJson() => {'version': version, 'etag': etag, 'texts': texts};
}
