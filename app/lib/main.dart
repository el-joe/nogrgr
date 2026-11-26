import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app/app.dart';
import 'core/config/tenant_config.dart';
import 'core/i18n/app_texts.dart';
import 'core/providers.dart';
import 'core/storage/app_preferences.dart';

/// One store app per build: `scripts/build_tenant.sh <tenant>` passes the tenant's
/// identity as `--dart-define`s (see `tenants/README.md`).
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final config = TenantConfig.fromEnvironment();
  final prefs = await SharedPreferences.getInstance();
  final info = await PackageInfo.fromPlatform();
  final saved = PreferencesStore(prefs, config.key).load();
  final texts = await AppTexts.loadBundled(saved.locale ?? config.defaultLocale);

  runApp(
    ProviderScope(
      overrides: [
        tenantConfigProvider.overrideWithValue(config),
        sharedPreferencesProvider.overrideWithValue(prefs),
        appVersionProvider.overrideWithValue(info.version),
        initialTextsProvider.overrideWithValue(texts),
      ],
      child: const NogrgrApp(),
    ),
  );
}
