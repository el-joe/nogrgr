import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../../core/widgets/paged_list.dart';
import '../../auth/data/session_controller.dart';
import '../../auth/presentation/auth_form.dart';
import '../data/notifications_repository.dart';
import '../data/push_coordinator.dart';

/// Notifications inbox (APP-030): push history, unread dots, mark all read, swipe to
/// delete, tap opens the order or the support ticket.
class InboxScreen extends ConsumerWidget {
  const InboxScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final signedIn = ref.watch(sessionProvider.select((s) => s.signedIn));
    final inbox = ref.watch(inboxProvider);
    final unread = inbox.value?.unread ?? 0;

    final Widget body = !signedIn
        ? StateView(
            icon: 'bell',
            tone: StateTone.primary,
            title: texts.t('app.inbox.title'),
            body: texts.t('app.inbox.sign_in'),
            actionLabel: texts.t('app.account.sign_in_register'),
            onAction: () => context.push(Routes.login),
          )
        : switch (inbox) {
            AsyncData(:final value) when value.items.isEmpty => AsyncStateView.empty(
                icon: 'bell',
                title: texts.t('app.inbox.empty_title'),
                body: texts.t('app.inbox.empty_body'),
              ),
            AsyncData(:final value) => PagedScroll(
                onRefresh: () async => ref.invalidate(inboxProvider),
                onLoadMore: () => ref.read(inboxProvider.notifier).loadMore(),
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(16),
                  itemCount: value.items.length + (value.hasMore ? 1 : 0),
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    if (index >= value.items.length) {
                      return const LoadingMore();
                    }

                    final item = value.items[index];

                    return Dismissible(
                      key: ValueKey('inbox-${item.id}'),
                      direction: DismissDirection.endToStart,
                      background: Container(
                        alignment: AlignmentDirectional.centerEnd,
                        padding: const EdgeInsets.symmetric(horizontal: 20),
                        decoration: BoxDecoration(color: context.colors.dangerBg, borderRadius: BorderRadius.circular(AppRadius.card)),
                        child: AppIcon('trash', color: context.colors.danger),
                      ),
                      onDismissed: (_) async {
                        try {
                          await ref.read(inboxProvider.notifier).remove(item);
                        } on ApiException catch (error) {
                          if (context.mounted) {
                            showAppMessage(context, apiErrorMessage(texts, error));
                          }
                        }
                      },
                      child: _InboxTile(item: item),
                    );
                  },
                ),
              ),
            AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(inboxProvider)),
            _ => const ListSkeleton(height: 72),
          };

    return PageScaffold(
      title: texts.t('app.inbox.title'),
      trailing: unread > 0
          ? TextButton(onPressed: () => ref.read(inboxProvider.notifier).readAll(), child: Text(texts.t('app.inbox.read_all')))
          : null,
      body: body,
    );
  }
}

class _InboxTile extends ConsumerWidget {
  const _InboxTile({required this.item});

  final InboxItem item;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final locale = ref.watch(localeCodeProvider);

    return SurfaceCard(
      onTap: () {
        ref.read(inboxProvider.notifier).open(item).ignore();

        if (item.orderUuid != null || item.ticketId != null) {
          context.push(pushLocation(orderUuid: item.orderUuid, ticketId: item.ticketId));
        }
      },
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(color: colors.primarySubtle, shape: BoxShape.circle),
            alignment: Alignment.center,
            child: AppIcon(item.orderUuid != null ? 'orders' : (item.ticketId != null ? 'lifebuoy' : 'bell'), size: 20, color: colors.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.title, style: text.bodyLarge?.copyWith(fontWeight: item.read ? FontWeight.w400 : FontWeight.w600)),
                if (item.body.isNotEmpty) ...[const SizedBox(height: 4), Text(item.body, style: text.bodyMedium)],
                const SizedBox(height: 4),
                Text(formatDate(item.createdAt, locale), style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400)),
              ],
            ),
          ),
          if (!item.read) Container(width: 8, height: 8, margin: const EdgeInsets.only(top: 6), decoration: BoxDecoration(color: colors.primary, shape: BoxShape.circle)),
        ],
      ),
    );
  }
}
