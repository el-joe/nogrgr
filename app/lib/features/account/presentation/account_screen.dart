import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../auth/data/session_controller.dart';
import '../../bootstrap/data/startup_controller.dart';
import '../../notifications/data/notifications_repository.dart';
import '../../orders/data/orders_repository.dart';
import '../../support/data/support_repository.dart';
import '../../wishlist/data/wishlist_repository.dart';

/// APP-024 account menu (Figma 149:104308) and guest state (149:104483). Rows show
/// their counts when known (orders, open tickets, unread notifications, wishlist).
class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final texts = ref.read(textsProvider);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        content: Text(texts.t('app.account.logout_confirm')),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: Text(texts.t('app.account.cancel'))),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(texts.t('app.account.logout'), style: TextStyle(color: context.colors.danger)),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await ref.read(sessionProvider.notifier).logout();
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final session = ref.watch(sessionProvider);
    final prefs = ref.watch(preferencesProvider);
    final bootstrap = ref.watch(startupProvider.select((s) => s.bootstrap));
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final customer = session.customer;
    final language = bootstrap?.languages.where((l) => l.code == ref.watch(localeCodeProvider)).firstOrNull?.name;
    final settingsValue = [?language, ?prefs.currency].join(' · ');
    final wishlistCount = session.signedIn ? ref.watch(wishlistProvider).value?.ids.length : null;
    final orderCount = session.signedIn ? ref.watch(ordersProvider(allOrders)).value?.total : null;
    final ticketCount = session.signedIn ? ref.watch(ticketsProvider('active')).value?.total : null;
    final unread = session.signedIn ? ref.watch(unreadCountProvider).value : null;
    String? count(int? value) => value == null || value == 0 ? null : '$value';

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppTopBar(title: texts.t('app.account.title')),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                children: [
                  if (customer != null) ...[
                    Row(
                      children: [
                        Container(
                          width: 56,
                          height: 56,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(color: colors.primarySubtle, shape: BoxShape.circle),
                          child: Text(customer.initials, style: text.titleLarge?.copyWith(color: colors.primary)),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(customer.name, style: text.titleLarge, maxLines: 1, overflow: TextOverflow.ellipsis),
                              Text(customer.email, style: text.bodyMedium, maxLines: 1, overflow: TextOverflow.ellipsis),
                            ],
                          ),
                        ),
                      ],
                    ),
                    if (!customer.emailVerified) ...[
                      const SizedBox(height: 16),
                      _VerifyBanner(
                        message: texts.t('auth.verify_banner'),
                        action: texts.t('app.account.verify_banner_action'),
                        onTap: () => context.push(Routes.verify),
                      ),
                    ],
                  ] else
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(color: colors.primarySubtle, borderRadius: BorderRadius.circular(AppRadius.card)),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(texts.t('app.account.guest_title'), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
                          const SizedBox(height: 8),
                          Text(texts.t('app.account.guest_body'), style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400, height: 1.4)),
                          const SizedBox(height: 12),
                          AppButton(
                            key: const ValueKey('account-sign-in'),
                            label: texts.t('app.account.sign_in_register'),
                            onPressed: () => context.push(Routes.login),
                          ),
                        ],
                      ),
                    ),
                  const SizedBox(height: 16),
                  if (customer != null) ...[
                    AccountRow(key: const ValueKey('account-orders'), icon: 'orders', label: texts.t('orders.title'), value: count(orderCount), onTap: () => context.push(Routes.orders)),
                    AccountRow(key: const ValueKey('account-returns'), icon: 'return', label: texts.t('returns.title'), onTap: () => context.push(Routes.returns)),
                    AccountRow(key: const ValueKey('account-addresses'), icon: 'map', label: texts.t('app.addresses.title'), onTap: () => context.push(Routes.addresses)),
                    AccountRow(key: const ValueKey('account-support'), icon: 'lifebuoy', label: texts.t('support.tickets.title'), value: count(ticketCount), onTap: () => context.push(Routes.support)),
                    AccountRow(key: const ValueKey('account-inbox'), icon: 'bell', label: texts.t('app.inbox.title'), value: count(unread), onTap: () => context.push(Routes.notifications)),
                    AccountRow(key: const ValueKey('account-profile'), icon: 'user', label: texts.t('app.account.edit_profile'), onTap: () => context.push(Routes.profile)),
                    AccountRow(key: const ValueKey('account-password'), icon: 'lock', label: texts.t('app.account.change_password'), onTap: () => context.push(Routes.password)),
                  ],
                  if (customer != null)
                    AccountRow(
                      icon: 'star',
                      label: texts.t('app.account.wishlist'),
                      value: count(wishlistCount),
                      onTap: () => context.go(Routes.wishlist),
                    ),
                  AccountRow(
                    icon: 'settings',
                    label: texts.t('app.account.settings'),
                    value: settingsValue.isEmpty ? null : settingsValue,
                    onTap: () => context.push(Routes.settings),
                  ),
                  AccountRow(key: const ValueKey('account-pages'), icon: 'lifebuoy', label: texts.t('app.account.help'), onTap: () => context.push(Routes.pages)),
                  if (customer != null)
                    AccountRow(
                      key: const ValueKey('account-delete'),
                      icon: 'trash',
                      label: texts.t('app.account.delete_account'),
                      onTap: () => context.push(Routes.deleteAccount),
                    ),
                  if (customer != null)
                    AccountRow(
                      key: const ValueKey('account-logout'),
                      icon: 'logout',
                      label: texts.t('app.account.logout'),
                      danger: true,
                      onTap: () => _logout(context, ref),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// "App/List row": 52 high, 22 icon, 15 label, 13 muted value, 18 chevron, bottom border.
class AccountRow extends StatelessWidget {
  const AccountRow({super.key, required this.icon, required this.label, required this.onTap, this.value, this.danger = false});

  final String icon;
  final String label;
  final String? value;
  final bool danger;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return InkWell(
      onTap: onTap,
      child: Container(
        height: 52,
        decoration: BoxDecoration(border: Border(bottom: BorderSide(color: colors.border))),
        child: Row(
          children: [
            AppIcon(icon, size: 22, color: danger ? colors.danger : colors.ink),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: text.bodyLarge?.copyWith(color: danger ? colors.danger : colors.ink))),
            if (value != null) ...[
              Text(value!, style: text.bodyMedium),
              const SizedBox(width: 12),
            ],
            AppIcon('chevron_right', size: 18, color: colors.muted, mirrorInRtl: true),
          ],
        ),
      ),
    );
  }
}

class _VerifyBanner extends StatelessWidget {
  const _VerifyBanner({required this.message, required this.action, required this.onTap});

  final String message;
  final String action;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(color: colors.warningBg, borderRadius: BorderRadius.circular(AppRadius.card)),
      child: Row(
        children: [
          Expanded(child: Text(message, style: text.labelMedium)),
          AppLink(label: action, onTap: onTap),
        ],
      ),
    );
  }
}
