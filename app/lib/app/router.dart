import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/providers.dart';
import '../features/account/presentation/account_screen.dart';
import '../features/account/presentation/settings_screen.dart';
import '../features/auth/data/session_controller.dart';
import '../features/auth/presentation/forgot_password_screen.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/register_screen.dart';
import '../features/auth/presentation/reset_password_screen.dart';
import '../features/auth/presentation/verify_email_screen.dart';
import '../features/bootstrap/data/startup_controller.dart';
import '../features/bootstrap/presentation/startup_screen.dart';
import '../features/onboarding/onboarding_screen.dart';
import '../features/onboarding/setup_screen.dart';
import '../features/shell/shell_screen.dart';
import '../features/wishlist/presentation/wishlist_screen.dart';

abstract final class Routes {
  static const splash = '/splash';
  static const onboarding = '/onboarding';
  static const setup = '/setup';
  static const login = '/auth/login';
  static const register = '/auth/register';
  static const forgot = '/auth/forgot';
  static const reset = '/auth/reset';
  static const verify = '/auth/verify';
  static const settings = '/settings';
  static const wishlist = '/wishlist';
  static const account = '/account';

  /// First tab after start-up.
  static const home = account;
}

/// Bridges Riverpod state changes to go_router's redirect.
class _RouterRefresh extends ChangeNotifier {
  void ping() => notifyListeners();
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _RouterRefresh();
  ref
    ..listen(startupProvider.select((s) => s.status), (_, _) => refresh.ping())
    ..listen(sessionProvider.select((s) => s.status), (_, _) => refresh.ping())
    ..listen(preferencesProvider.select((p) => (p.onboardingSeen, p.setupDone)), (_, _) => refresh.ping())
    ..onDispose(refresh.dispose);

  final router = GoRouter(
    initialLocation: Routes.splash,
    refreshListenable: refresh,
    redirect: (context, state) {
      final startup = ref.read(startupProvider);
      final session = ref.read(sessionProvider);
      final prefs = ref.read(preferencesProvider);
      final path = state.matchedLocation;
      final ready = startup.status == StartupStatus.ready && session.status != SessionStatus.unknown;

      // Start-up states (loading, offline, update, maintenance …) live on /splash.
      if (!ready) {
        return path == Routes.splash ? null : Routes.splash;
      }

      if (!prefs.onboardingSeen) {
        return path == Routes.onboarding ? null : Routes.onboarding;
      }

      if (!prefs.setupDone) {
        return path == Routes.setup ? null : Routes.setup;
      }

      if (path == Routes.splash || path == Routes.onboarding || path == Routes.setup) {
        return Routes.home;
      }

      if (path == Routes.verify && !session.signedIn) {
        return Routes.login;
      }

      return null;
    },
    routes: [
      GoRoute(path: Routes.splash, builder: (_, _) => const StartupScreen()),
      GoRoute(path: Routes.onboarding, builder: (_, _) => const OnboardingScreen()),
      GoRoute(path: Routes.setup, builder: (_, _) => const SetupScreen()),
      GoRoute(path: Routes.login, builder: (_, _) => const LoginScreen()),
      GoRoute(path: Routes.register, builder: (_, _) => const RegisterScreen()),
      GoRoute(path: Routes.forgot, builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(
        path: Routes.reset,
        // E-mail travels as route `extra` (never in the location string).
        redirect: (_, state) => state.extra is ResetArgs ? null : Routes.forgot,
        builder: (_, state) => ResetPasswordScreen(args: state.extra! as ResetArgs),
      ),
      GoRoute(path: Routes.verify, builder: (_, _) => const VerifyEmailScreen()),
      GoRoute(path: Routes.settings, builder: (_, _) => const SettingsScreen()),
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => ShellScreen(navigationShell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: Routes.wishlist, builder: (_, _) => const WishlistScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: Routes.account, builder: (_, _) => const AccountScreen())]),
        ],
      ),
    ],
  );
  ref.onDispose(router.dispose);

  return router;
});
