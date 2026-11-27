import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/router.dart';
import '../../../core/providers.dart';
import '../../../core/push/push_service.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../auth/data/session_controller.dart';
import '../../bootstrap/data/startup_controller.dart';
import '../data/notifications_repository.dart';
import '../data/push_coordinator.dart';

/// How long a foreground push banner stays.
const pushBannerDuration = Duration(seconds: 6);

/// App-wide push handling around the router: opens a tapped push's deep link once the
/// start-up gates are passed (cold start included), shows foreground pushes as a
/// banner on top of every screen, refreshes the bell badge when the app resumes.
class PushListener extends ConsumerStatefulWidget {
  const PushListener({super.key, required this.child});

  final Widget child;

  @override
  ConsumerState<PushListener> createState() => _PushListenerState();
}

class _PushListenerState extends ConsumerState<PushListener> {
  late final AppLifecycleListener _lifecycle;
  Timer? _hide;

  @override
  void initState() {
    super.initState();
    _lifecycle = AppLifecycleListener(onResume: () => ref.invalidate(unreadCountProvider));
  }

  @override
  void dispose() {
    _hide?.cancel();
    _lifecycle.dispose();
    super.dispose();
  }

  bool get _ready {
    final startup = ref.read(startupProvider);
    final session = ref.read(sessionProvider);
    final prefs = ref.read(preferencesProvider);

    return startup.status == StartupStatus.ready && session.status != SessionStatus.unknown && prefs.onboardingSeen && prefs.setupDone;
  }

  void _openPending() {
    final pending = ref.read(pushCoordinatorProvider).pending;

    if (pending == null || !_ready) {
      return;
    }

    ref.read(pushCoordinatorProvider.notifier).consumePending();
    // After the current frame: the router may still be redirecting off the splash.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        ref.read(routerProvider).push(pending);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    ref
      ..listen(pushCoordinatorProvider.select((s) => s.pending), (_, _) => _openPending())
      ..listen(startupProvider.select((s) => s.status), (_, _) => _openPending())
      ..listen(sessionProvider.select((s) => s.status), (_, _) => _openPending())
      ..listen(preferencesProvider.select((p) => (p.onboardingSeen, p.setupDone)), (_, _) => _openPending())
      ..listen(pushCoordinatorProvider.select((s) => s.banner), (_, banner) {
        _hide?.cancel();

        if (banner != null) {
          _hide = Timer(pushBannerDuration, () => ref.read(pushCoordinatorProvider.notifier).dismissBanner());
        }
      });
    final banner = ref.watch(pushCoordinatorProvider.select((s) => s.banner));

    return Stack(
      children: [
        widget.child,
        if (banner != null)
          Positioned(
            top: MediaQuery.paddingOf(context).top + 8,
            left: 12,
            right: 12,
            child: PushBanner(
              message: banner,
              onOpen: () => ref.read(pushCoordinatorProvider.notifier).openBanner(),
              onDismiss: () => ref.read(pushCoordinatorProvider.notifier).dismissBanner(),
            ),
          ),
      ],
    );
  }
}

/// In-app banner of a foreground push: bell, title, body; tap opens, swipe up / close
/// dismisses.
class PushBanner extends ConsumerWidget {
  const PushBanner({super.key, required this.message, required this.onOpen, required this.onDismiss});

  final PushMessage message;
  final VoidCallback onOpen;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final title = message.title ?? texts.t('app.inbox.title');

    return Dismissible(
      key: const ValueKey('push-banner'),
      direction: DismissDirection.up,
      onDismissed: (_) => onDismiss(),
      child: Semantics(
        liveRegion: true,
        child: Material(
          color: colors.surface,
          elevation: 6,
          shadowColor: Colors.black26,
          borderRadius: BorderRadius.circular(AppRadius.card),
          child: InkWell(
            key: const ValueKey('push-banner-open'),
            borderRadius: BorderRadius.circular(AppRadius.card),
            onTap: onOpen,
            child: Container(
              padding: const EdgeInsets.fromLTRB(12, 12, 4, 12),
              decoration: BoxDecoration(border: Border.all(color: colors.border), borderRadius: BorderRadius.circular(AppRadius.card)),
              child: Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: colors.primarySubtle, shape: BoxShape.circle),
                    child: AppIcon(message.ticketId != null ? 'lifebuoy' : (message.orderUuid != null ? 'orders' : 'bell'), size: 18, color: colors.primary),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(title, style: text.bodyLarge?.copyWith(fontWeight: FontWeight.w600), maxLines: 1, overflow: TextOverflow.ellipsis),
                        if (message.body != null && message.body!.isNotEmpty)
                          Text(message.body!, style: text.bodyMedium, maxLines: 2, overflow: TextOverflow.ellipsis),
                      ],
                    ),
                  ),
                  // The banner sits above the Navigator (no Overlay): no tooltip.
                  Semantics(
                    button: true,
                    label: texts.t('app.push.dismiss'),
                    excludeSemantics: true,
                    child: IconButton(
                      key: const ValueKey('push-banner-close'),
                      icon: Icon(Icons.close, size: 18, color: colors.muted),
                      onPressed: onDismiss,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
