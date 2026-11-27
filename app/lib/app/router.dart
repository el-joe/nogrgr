import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/providers.dart';
import '../features/account/presentation/account_screen.dart';
import '../features/account/presentation/profile_screens.dart';
import '../features/addresses/data/address_repository.dart';
import '../features/addresses/presentation/addresses_screen.dart';
import '../features/cart/presentation/cart_screen.dart';
import '../features/catalog/data/catalog_models.dart';
import '../features/catalog/presentation/categories_screen.dart';
import '../features/catalog/presentation/home_screen.dart';
import '../features/catalog/presentation/image_search_screen.dart';
import '../features/catalog/presentation/product_list_screen.dart';
import '../features/catalog/presentation/product_screen.dart';
import '../features/catalog/presentation/reviews_screen.dart';
import '../features/catalog/presentation/search_screen.dart';
import '../features/checkout/presentation/checkout_screen.dart';
import '../features/checkout/presentation/payment_flow.dart';
import '../features/notifications/presentation/inbox_screen.dart';
import '../features/orders/presentation/order_detail_screen.dart';
import '../features/orders/presentation/orders_screen.dart';
import '../features/pages/presentation/pages_screens.dart';
import '../features/returns/presentation/returns_screens.dart';
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
  static const home = '/home';
  static const categories = '/categories';
  static const cart = '/cart';
  static const search = '/search';
  static const imageSearch = '/search/image';
  static const checkout = '/checkout';
  static const paymentResult = '/checkout/result';
  static const orders = '/orders';
  static const returns = '/returns';
  static const addresses = '/account/addresses';
  static const addressEdit = '/account/addresses/edit';
  static const profile = '/account/profile';
  static const password = '/account/password';
  static const deleteAccount = '/account/delete';
  static const notifications = '/notifications';
  static const pages = '/pages';

  static String product(int id) => '/products/$id';
  static String reviews(int id) => '/products/$id/reviews';
  static String order(String uuid) => '/orders/$uuid';
  static String orderTracking(String uuid) => '/orders/$uuid/tracking';
  static String returnRequest(String uuid) => '/orders/$uuid/return';
  static String returnDetail(int id) => '/returns/$id';
  static String page(String slug) => '/pages/$slug';

  /// Product list: a kind (new_in, best_selling, top_rated, offers) or a category slug.
  static String listing({String? kind, String? category, String? title}) =>
      Uri(path: '/list', queryParameters: {'kind': ?kind, 'category': ?category, 'title': ?title}).toString();
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

  // Account-only pages send guests to sign in.
  String? signedInOnly(BuildContext context, GoRouterState state) => ref.read(sessionProvider).signedIn ? null : Routes.login;

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
      GoRoute(path: Routes.search, builder: (_, state) => SearchScreen(initial: state.uri.queryParameters['q'])),
      GoRoute(path: Routes.imageSearch, builder: (_, _) => const ImageSearchScreen()),
      GoRoute(
        path: '/list',
        builder: (_, state) {
          final q = state.uri.queryParameters;

          return ProductListScreen(source: ListingSource(kind: q['kind'], category: q['category'], title: q['title']));
        },
      ),
      GoRoute(
        path: '/products/:id',
        builder: (_, state) => ProductScreen(id: int.tryParse(state.pathParameters['id'] ?? '') ?? 0),
        routes: [GoRoute(path: 'reviews', builder: (_, state) => ReviewsScreen(productId: int.tryParse(state.pathParameters['id'] ?? '') ?? 0))],
      ),
      GoRoute(path: Routes.checkout, redirect: signedInOnly, builder: (_, _) => const CheckoutScreen()),
      GoRoute(
        path: Routes.paymentResult,
        redirect: (_, state) => state.extra is PaymentOutcome ? null : Routes.orders,
        builder: (_, state) => PaymentResultScreen(outcome: state.extra! as PaymentOutcome),
      ),
      GoRoute(
        path: Routes.orders,
        redirect: signedInOnly,
        builder: (_, _) => const OrdersScreen(),
        routes: [
          GoRoute(
            path: ':uuid',
            builder: (_, state) => OrderDetailScreen(uuid: state.pathParameters['uuid']!),
            routes: [
              GoRoute(path: 'tracking', builder: (_, state) => OrderTrackingScreen(uuid: state.pathParameters['uuid']!)),
              GoRoute(path: 'return', builder: (_, state) => ReturnRequestScreen(orderUuid: state.pathParameters['uuid']!)),
            ],
          ),
        ],
      ),
      GoRoute(
        path: Routes.returns,
        redirect: signedInOnly,
        builder: (_, _) => const ReturnsScreen(),
        routes: [GoRoute(path: ':id', builder: (_, state) => ReturnDetailScreen(id: int.tryParse(state.pathParameters['id'] ?? '') ?? 0))],
      ),
      GoRoute(path: Routes.addresses, redirect: signedInOnly, builder: (_, _) => const AddressesScreen()),
      GoRoute(path: Routes.addressEdit, redirect: signedInOnly, builder: (_, state) => AddressEditScreen(address: state.extra is Address ? state.extra! as Address : null)),
      GoRoute(path: Routes.profile, redirect: signedInOnly, builder: (_, _) => const EditProfileScreen()),
      GoRoute(path: Routes.password, redirect: signedInOnly, builder: (_, _) => const ChangePasswordScreen()),
      GoRoute(path: Routes.deleteAccount, redirect: signedInOnly, builder: (_, _) => const DeleteAccountScreen()),
      GoRoute(path: Routes.notifications, builder: (_, _) => const InboxScreen()),
      GoRoute(
        path: Routes.pages,
        builder: (_, _) => const PagesScreen(),
        routes: [GoRoute(path: ':slug', builder: (_, state) => StorePageScreen(slug: state.pathParameters['slug']!))],
      ),
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => ShellScreen(navigationShell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: Routes.home, builder: (_, _) => const HomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: Routes.categories, builder: (_, _) => const CategoriesScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: Routes.cart, builder: (_, _) => const CartScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: Routes.wishlist, builder: (_, _) => const WishlistScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: Routes.account, builder: (_, _) => const AccountScreen())]),
        ],
      ),
    ],
  );
  ref.onDispose(router.dispose);

  return router;
});
