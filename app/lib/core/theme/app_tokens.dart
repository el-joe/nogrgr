import 'package:flutter/material.dart';

/// Theme family of the store's storefront theme (bootstrap `branding.theme.family`).
enum ThemeFamily {
  ecommet,
  elora,
  souqify;

  static ThemeFamily parse(String? value) =>
      ThemeFamily.values.firstWhere((family) => family.name == value, orElse: () => ThemeFamily.elora);
}

/// Theme family + brand colour the app paints with (decision #107 c). Once bootstrap
/// answers, the store's theme family wins and sets ink + the default primary; the
/// store's `branding.colors.primary` overrides that primary. The build's
/// THEME_FAMILY / PRIMARY_COLOR only paint until bootstrap answers (splash, offline
/// first start), so a store that switches theme never keeps the old family's colour.
@immutable
class AppBrand {
  const AppBrand({required this.family, this.primary});

  factory AppBrand.resolve({
    required String buildFamily,
    required String? buildPrimary,
    String? bootstrapFamily,
    String? bootstrapPrimary,
    bool bootstrapped = false,
  }) {
    if (!bootstrapped) {
      return AppBrand(family: ThemeFamily.parse(buildFamily), primary: AppColors.parseHex(buildPrimary));
    }

    // null primary = the family default (AppColors.familyPrimary).
    return AppBrand(family: ThemeFamily.parse(bootstrapFamily ?? buildFamily), primary: AppColors.parseHex(bootstrapPrimary));
  }

  final ThemeFamily family;

  /// Explicit brand colour, null = [AppColors.familyPrimary] of [family].
  final Color? primary;

  Color get effectivePrimary => primary ?? AppColors.familyPrimary(family);
}

/// Colour tokens of the Figma "Mobile App" + "Mobile App · Family" collections
/// (`--app-*`, page "Mobile App" 139:10147). The family sets ink + default primary, the
/// store's brand colour (bootstrap) overrides primary. Dark mode keeps the same roles.
@immutable
class AppColors extends ThemeExtension<AppColors> {
  const AppColors({
    required this.primary,
    required this.onPrimary,
    required this.primarySubtle,
    required this.ink,
    required this.bg,
    required this.surface,
    required this.muted,
    required this.border,
    required this.tabbar,
    required this.danger,
    required this.warningBg,
    required this.infoBg,
    required this.dangerBg,
    required this.info,
    required this.warning,
  });

  /// Family defaults (Figma splash frames 149:103879 / 149:103898 / 149:103917).
  static Color familyPrimary(ThemeFamily family) => switch (family) {
        ThemeFamily.elora => const Color(0xFFFF4D00),
        ThemeFamily.souqify => const Color(0xFF0159ED),
        ThemeFamily.ecommet => const Color(0xFFDE1709),
      };

  static Color familyInk(ThemeFamily family) => switch (family) {
        ThemeFamily.elora => const Color(0xFF121212),
        ThemeFamily.souqify => const Color(0xFF001537),
        ThemeFamily.ecommet => const Color(0xFF242424),
      };

  factory AppColors.light({required ThemeFamily family, Color? primary}) {
    final brand = primary ?? familyPrimary(family);

    return AppColors(
      primary: brand,
      onPrimary: onColor(brand),
      primarySubtle: Color.alphaBlend(brand.withValues(alpha: 0.12), Colors.white),
      ink: familyInk(family),
      bg: const Color(0xFFFDFDFD),
      surface: Colors.white,
      muted: const Color(0xFF888888),
      border: const Color(0xFFE3E3E3),
      tabbar: Colors.white,
      danger: const Color(0xFFDC2626),
      warningBg: const Color(0xFFFEF3E2),
      infoBg: const Color(0xFFEAF1FE),
      dangerBg: const Color(0xFFFDECEC),
      info: const Color(0xFF2563EB),
      warning: const Color(0xFFD97706),
    );
  }

  factory AppColors.dark({required ThemeFamily family, Color? primary}) {
    final brand = primary ?? familyPrimary(family);

    return AppColors(
      primary: brand,
      onPrimary: onColor(brand),
      primarySubtle: Color.alphaBlend(brand.withValues(alpha: 0.22), const Color(0xFF161616)),
      ink: const Color(0xFFF2F2F2),
      bg: const Color(0xFF0F0F10),
      surface: const Color(0xFF1A1A1C),
      muted: const Color(0xFF9A9A9E),
      border: const Color(0xFF2E2E32),
      tabbar: const Color(0xFF161618),
      danger: const Color(0xFFF87171),
      warningBg: const Color(0xFF3A2A10),
      infoBg: const Color(0xFF14243F),
      dangerBg: const Color(0xFF3A1414),
      info: const Color(0xFF7AA7FF),
      warning: const Color(0xFFFFB547),
    );
  }

  /// White or ink text on a brand colour, whichever reads better.
  static Color onColor(Color color) => color.computeLuminance() > 0.6 ? const Color(0xFF121212) : Colors.white;

  /// `#RRGGBB` / `RRGGBB` → colour, null when invalid.
  static Color? parseHex(String? hex) {
    if (hex == null) {
      return null;
    }

    final clean = hex.replaceFirst('#', '').trim();

    if (clean.length != 6) {
      return null;
    }

    final value = int.tryParse(clean, radix: 16);

    return value == null ? null : Color(0xFF000000 | value);
  }

  final Color primary;
  final Color onPrimary;
  final Color primarySubtle;
  final Color ink;
  final Color bg;
  final Color surface;
  final Color muted;
  final Color border;
  final Color tabbar;
  final Color danger;
  final Color warningBg;
  final Color infoBg;
  final Color dangerBg;
  final Color info;
  final Color warning;

  @override
  AppColors copyWith({Color? primary}) => AppColors(
        primary: primary ?? this.primary,
        onPrimary: primary == null ? onPrimary : onColor(primary),
        primarySubtle: primarySubtle,
        ink: ink,
        bg: bg,
        surface: surface,
        muted: muted,
        border: border,
        tabbar: tabbar,
        danger: danger,
        warningBg: warningBg,
        infoBg: infoBg,
        dangerBg: dangerBg,
        info: info,
        warning: warning,
      );

  @override
  AppColors lerp(AppColors? other, double t) {
    if (other == null) {
      return this;
    }

    Color mix(Color a, Color b) => Color.lerp(a, b, t)!;

    return AppColors(
      primary: mix(primary, other.primary),
      onPrimary: mix(onPrimary, other.onPrimary),
      primarySubtle: mix(primarySubtle, other.primarySubtle),
      ink: mix(ink, other.ink),
      bg: mix(bg, other.bg),
      surface: mix(surface, other.surface),
      muted: mix(muted, other.muted),
      border: mix(border, other.border),
      tabbar: mix(tabbar, other.tabbar),
      danger: mix(danger, other.danger),
      warningBg: mix(warningBg, other.warningBg),
      infoBg: mix(infoBg, other.infoBg),
      dangerBg: mix(dangerBg, other.dangerBg),
      info: mix(info, other.info),
      warning: mix(warning, other.warning),
    );
  }
}

/// Radii of the "Mobile App" collection.
abstract final class AppRadius {
  static const input = 6.0;
  static const button = 999.0;
  static const card = 10.0;
}

/// Font families bundled in pubspec (decision #89): Outfit (Latin), Noto Sans Arabic.
abstract final class AppFonts {
  static const latin = 'Outfit';
  static const arabic = 'NotoSansArabic';
}

extension AppColorsContext on BuildContext {
  AppColors get colors => Theme.of(this).extension<AppColors>()!;
}
