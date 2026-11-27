import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/providers.dart';
import '../bootstrap/data/startup_controller.dart';
import '../bootstrap/presentation/startup_screen.dart';
import '../cart/data/cart_repository.dart';
import 'app_tab_bar.dart';

/// One bottom-nav destination. Tabs are listed here as their screens ship; the bar
/// never shows a tab without a working screen.
class ShellTab {
  const ShellTab({required this.path, required this.icon, required this.textKey});

  final String path;
  final String icon;
  final String textKey;
}

/// Order of the Figma tab bar (Home, Categories, Cart, Wishlist, Account).
const shellTabs = [
  ShellTab(path: '/home', icon: 'home', textKey: 'mobile.tabs.home'),
  ShellTab(path: '/categories', icon: 'grid', textKey: 'mobile.tabs.categories'),
  ShellTab(path: '/cart', icon: 'cart', textKey: 'mobile.tabs.cart'),
  ShellTab(path: '/wishlist', icon: 'star', textKey: 'mobile.tabs.favorites'),
  ShellTab(path: '/account', icon: 'users', textKey: 'mobile.tabs.account'),
];

/// Bottom-nav shell (StatefulShellRoute): each tab keeps its own stack. Shows the
/// optional-update prompt once per start (APP-002).
class ShellScreen extends ConsumerStatefulWidget {
  const ShellScreen({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  ConsumerState<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends ConsumerState<ShellScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final startup = ref.read(startupProvider);

      if (mounted && startup.optionalUpdate && startup.bootstrap != null) {
        showOptionalUpdate(context, ref, startup.bootstrap!);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final shell = widget.navigationShell;

    return Scaffold(
      body: shell,
      bottomNavigationBar: AppTabBar(
        currentIndex: shell.currentIndex,
        items: [
          for (final tab in shellTabs)
            AppTabItem(icon: tab.icon, label: ref.t(tab.textKey), badge: tab.path == '/cart' ? ref.watch(cartCountProvider) : 0),
        ],
        onTap: (index) => shell.goBranch(index, initialLocation: index == shell.currentIndex),
      ),
    );
  }
}
