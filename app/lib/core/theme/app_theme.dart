import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app_tokens.dart';

/// Material theme built from the "Mobile App" tokens. Type scale from the Figma frames:
/// display 28/34 bold, title 22/28 semibold, top bar 17/22 semibold, body 15/20,
/// label 13/18 medium, caption 11/13 medium.
class AppTheme {
  /// Status / navigation bar style for a surface: dark icons on light surfaces, light
  /// icons on dark surfaces (dark mode, the Ecommet brand header). Transparent status bar.
  static SystemUiOverlayStyle overlayFor(Color background, {Color? navigationBar}) {
    final light = ThemeData.estimateBrightnessForColor(background) == Brightness.light;

    return SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: light ? Brightness.dark : Brightness.light,
      statusBarBrightness: light ? Brightness.light : Brightness.dark,
      systemNavigationBarColor: navigationBar,
      systemNavigationBarIconBrightness: navigationBar == null
          ? null
          : (ThemeData.estimateBrightnessForColor(navigationBar) == Brightness.light ? Brightness.dark : Brightness.light),
    );
  }

  static ThemeData build({
    required ThemeFamily family,
    required Brightness brightness,
    required bool rtl,
    Color? primary,
  }) {
    final colors = brightness == Brightness.dark
        ? AppColors.dark(family: family, primary: primary)
        : AppColors.light(family: family, primary: primary);
    final font = rtl ? AppFonts.arabic : AppFonts.latin;
    final fallback = rtl ? const [AppFonts.latin] : const [AppFonts.arabic];

    TextStyle style(double size, double height, FontWeight weight, [Color? color]) => TextStyle(
          fontFamily: font,
          fontFamilyFallback: fallback,
          fontSize: size,
          height: height / size,
          fontWeight: weight,
          color: color ?? colors.ink,
        );

    final textTheme = TextTheme(
      displaySmall: style(28, 34, FontWeight.w700),
      headlineSmall: style(22, 28, FontWeight.w600),
      titleLarge: style(17, 22, FontWeight.w600),
      titleMedium: style(15, 20, FontWeight.w500),
      bodyLarge: style(15, 20, FontWeight.w400),
      bodyMedium: style(13, 18, FontWeight.w400, colors.muted),
      labelLarge: style(15, 20, FontWeight.w500),
      labelMedium: style(13, 18, FontWeight.w500),
      labelSmall: style(11, 13, FontWeight.w500),
    );

    final scheme = ColorScheme.fromSeed(
      seedColor: colors.primary,
      brightness: brightness,
    ).copyWith(
      primary: colors.primary,
      onPrimary: colors.onPrimary,
      surface: colors.bg,
      onSurface: colors.ink,
      error: colors.danger,
      outline: colors.border,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: colors.bg,
      fontFamily: font,
      fontFamilyFallback: fallback,
      textTheme: textTheme,
      extensions: [colors],
      dividerColor: colors.border,
      splashFactory: InkSparkle.splashFactory,
      appBarTheme: AppBarTheme(
        backgroundColor: colors.bg,
        foregroundColor: colors.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        titleTextStyle: textTheme.titleLarge,
        systemOverlayStyle: overlayFor(colors.bg, navigationBar: colors.tabbar),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: colors.ink,
        contentTextStyle: style(13, 18, FontWeight.w500, colors.bg),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: colors.primary),
      textSelectionTheme: TextSelectionThemeData(cursorColor: colors.primary),
    );
  }
}
