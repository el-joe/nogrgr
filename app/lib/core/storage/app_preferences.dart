import 'dart:math';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Device-level choices of this store app (APP-003 / APP-029): language, display currency,
/// shopping country, light / dark, onboarding + first-run flags and the install id.
@immutable
class AppPreferences {
  const AppPreferences({
    required this.deviceId,
    this.locale,
    this.currency,
    this.country,
    this.themeMode = ThemeMode.system,
    this.onboardingSeen = false,
    this.setupDone = false,
  });

  final String deviceId;
  final String? locale;
  final String? currency;
  final String? country;
  final ThemeMode themeMode;
  final bool onboardingSeen;
  final bool setupDone;

  AppPreferences copyWith({
    String? locale,
    String? currency,
    String? country,
    ThemeMode? themeMode,
    bool? onboardingSeen,
    bool? setupDone,
  }) =>
      AppPreferences(
        deviceId: deviceId,
        locale: locale ?? this.locale,
        currency: currency ?? this.currency,
        country: country ?? this.country,
        themeMode: themeMode ?? this.themeMode,
        onboardingSeen: onboardingSeen ?? this.onboardingSeen,
        setupDone: setupDone ?? this.setupDone,
      );
}

/// SharedPreferences persistence, namespaced per tenant.
class PreferencesStore {
  PreferencesStore(this._prefs, this._namespace);

  final SharedPreferences _prefs;
  final String _namespace;

  String _k(String name) => 'nogrgr.$_namespace.$name';

  AppPreferences load() {
    var deviceId = _prefs.getString(_k('device_id'));

    if (deviceId == null || deviceId.length < 8) {
      deviceId = newDeviceId();
      _prefs.setString(_k('device_id'), deviceId);
    }

    return AppPreferences(
      deviceId: deviceId,
      locale: _prefs.getString(_k('locale')),
      currency: _prefs.getString(_k('currency')),
      country: _prefs.getString(_k('country')),
      themeMode: ThemeMode.values.firstWhere(
        (mode) => mode.name == _prefs.getString(_k('theme_mode')),
        orElse: () => ThemeMode.system,
      ),
      onboardingSeen: _prefs.getBool(_k('onboarding_seen')) ?? false,
      setupDone: _prefs.getBool(_k('setup_done')) ?? false,
    );
  }

  Future<void> save(AppPreferences value) async {
    Future<void> setOrRemove(String name, String? v) =>
        v == null ? _prefs.remove(_k(name)) : _prefs.setString(_k(name), v);

    await setOrRemove('locale', value.locale);
    await setOrRemove('currency', value.currency);
    await setOrRemove('country', value.country);
    await _prefs.setString(_k('theme_mode'), value.themeMode.name);
    await _prefs.setBool(_k('onboarding_seen'), value.onboardingSeen);
    await _prefs.setBool(_k('setup_done'), value.setupDone);
  }

  /// 32 hex chars; matches the API rule `[A-Za-z0-9._:-]{8,100}`.
  static String newDeviceId() {
    final random = Random.secure();

    return List.generate(16, (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
  }
}
