import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/providers.dart';
import '../core/theme/app_theme.dart';
import '../core/theme/app_tokens.dart';
import '../features/bootstrap/data/startup_controller.dart';
import '../features/notifications/presentation/push_listener.dart';
import 'router.dart';

/// Root widget: theme from the store branding ([AppBrand.resolve]: bootstrap colour →
/// bootstrap family default → build colour before bootstrap answers), language + direction, light / dark, router.
class NogrgrApp extends ConsumerWidget {
  const NogrgrApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(tenantConfigProvider);
    final branding = ref.watch(startupProvider.select((s) => s.bootstrap?.branding));
    final locale = ref.watch(localeCodeProvider);
    final themeMode = ref.watch(preferencesProvider.select((p) => p.themeMode));
    final router = ref.watch(routerProvider);
    final brand = AppBrand.resolve(
      buildFamily: config.themeFamily,
      buildPrimary: config.primaryColor,
      bootstrapped: branding != null,
      bootstrapFamily: branding?.family,
      bootstrapPrimary: branding?.primaryColor,
    );
    final family = brand.family;
    final primary = brand.primary;
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
      // Default status-bar style follows the active light / dark surface on every screen;
      // screens with a coloured header (splash, Ecommet home) set their own region.
      builder: (context, child) {
        final colors = context.colors;

        return AnnotatedRegion<SystemUiOverlayStyle>(
          value: AppTheme.overlayFor(colors.bg, navigationBar: colors.tabbar),
          child: Directionality(
            textDirection: rtl ? TextDirection.rtl : TextDirection.ltr,
            child: PushListener(child: child ?? const SizedBox.shrink()),
          ),
        );
      },
    );
  }
}
