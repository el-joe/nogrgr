import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/router.dart';
import '../../../core/push/push_service.dart';
import '../../auth/data/session_controller.dart';
import '../../support/data/support_repository.dart';
import 'notifications_repository.dart';

/// Where a push (or an inbox row) leads: the order, else the support ticket, else the inbox.
String pushLocation({String? orderUuid, int? ticketId}) {
  if (orderUuid != null && orderUuid.isNotEmpty) {
    return Routes.order(orderUuid);
  }

  if (ticketId != null && ticketId > 0) {
    return Routes.ticket(ticketId);
  }

  return Routes.notifications;
}

class PushState {
  const PushState({this.pending, this.banner});

  /// Location to open once the app is past the start-up gates (tap / cold start).
  final String? pending;

  /// Foreground push shown as the in-app banner.
  final PushMessage? banner;
}

/// Reacts to pushes: a tap queues its deep link (and marks the inbox row read), a
/// foreground push shows the banner; both refresh the bell badge, the inbox and the
/// ticket it is about.
class PushCoordinator extends Notifier<PushState> {
  @override
  PushState build() {
    final push = ref.watch(pushServiceProvider);
    final subscriptions = [
      push.opened.listen(_opened),
      push.foreground.listen(_foreground),
    ];
    ref.onDispose(() {
      for (final subscription in subscriptions) {
        subscription.cancel();
      }
    });

    return const PushState();
  }

  void _refresh(PushMessage message) {
    ref
      ..invalidate(unreadCountProvider)
      ..invalidate(inboxProvider);

    if (message.ticketId != null) {
      ref
        ..invalidate(ticketProvider(message.ticketId!))
        ..invalidate(ticketsProvider);
    }
  }

  void _opened(PushMessage message) {
    final id = message.notificationId;

    if (id != null && ref.read(sessionProvider).signedIn) {
      unawaited(ref.read(notificationsRepositoryProvider).markRead(id).then((_) => ref.invalidate(unreadCountProvider), onError: (_) {}));
    }

    _refresh(message);
    state = PushState(pending: pushLocation(orderUuid: message.orderUuid, ticketId: message.ticketId), banner: state.banner);
  }

  void _foreground(PushMessage message) {
    _refresh(message);
    state = PushState(pending: state.pending, banner: message);
  }

  /// The listener navigated to [PushState.pending].
  void consumePending() => state = PushState(banner: state.banner);

  void dismissBanner() => state = PushState(pending: state.pending);

  /// Banner tapped: same as tapping the system notification.
  void openBanner() {
    final banner = state.banner;

    if (banner != null) {
      state = PushState(pending: state.pending);
      _opened(banner);
    }
  }
}

final pushCoordinatorProvider = NotifierProvider<PushCoordinator, PushState>(PushCoordinator.new);
