import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/providers.dart';
import '../core/theme/app_theme.dart';
import '../core/theme/app_tokens.dart';
import '../features/bootstrap/data/startup_controller.dart';
import '../features/notifications/presentation/push_listener.dart';
import 'router.dart';

/// Root widget: theme from the store branding (family + brand colour, until bootstrap
/// answers the build's defaults), language + direction, light / dark, router.
class NogrgrApp extends ConsumerWidget {
  const NogrgrApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(tenantConfigProvider);
    final branding = ref.watch(startupProvider.select((s) => s.bootstrap?.branding));
    final locale = ref.watch(localeCodeProvider);
    final themeMode = ref.watch(preferencesProvider.select((p) => p.themeMode));
    final router = ref.watch(routerProvider);
    final family = ThemeFamily.parse(branding?.family ?? config.themeFamily);
    final primary = AppColors.parseHex(branding?.primaryColor) ?? AppColors.parseHex(config.primaryColor);
    final rtl = locale == 'ar' || locale == 'he' || locale == 'fa' || locale == 'ur';

    return MaterialApp.router(
      title: config.appName,
      debugShowCheckedModeBanner: false,
      routerConfig: router,
      locale: Locale(locale),
      supportedLocales: const [Locale('en'), Locale('ar')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      localeResolutionCallback: (current, supported) =>
          supported.firstWhere((l) => l.languageCode == current?.languageCode, orElse: () => const Locale('en')),
      themeMode: themeMode,
      theme: AppTheme.build(family: family, brightness: Brightness.light, rtl: rtl, primary: primary),
      darkTheme: AppTheme.build(family: family, brightness: Brightness.dark, rtl: rtl, primary: primary),
      builder: (context, child) => Directionality(
        textDirection: rtl ? TextDirection.rtl : TextDirection.ltr,
        child: PushListener(child: child ?? const SizedBox.shrink()),
      ),
    );
  }
}
