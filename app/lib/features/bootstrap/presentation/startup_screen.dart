import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../auth/data/session_controller.dart';
import '../data/bootstrap.dart';
import '../data/startup_controller.dart';

/// APP-001 splash + APP-002 gates (Figma 149:103879 … 149:104014): branded splash while
/// bootstrap loads, then offline / can't reach / unavailable / update required /
/// maintenance. The router leaves this screen once the store is ready.
class StartupScreen extends ConsumerStatefulWidget {
  const StartupScreen({super.key});

  @override
  ConsumerState<StartupScreen> createState() => _StartupScreenState();
}

class _StartupScreenState extends ConsumerState<StartupScreen> {
  Timer? _autoRetry;

  @override
  void initState() {
    super.initState();
    Future.microtask(_start);
  }

  @override
  void dispose() {
    _autoRetry?.cancel();
    super.dispose();
  }

  Future<void> _start() async {
    final session = ref.read(sessionProvider.notifier);
    await Future.wait([ref.read(startupProvider.notifier).start(), session.restore()]);
  }

  void _scheduleRetry(StartupStatus status) {
    _autoRetry?.cancel();

    // "We'll retry automatically" while offline.
    if (status == StartupStatus.offline) {
      _autoRetry = Timer.periodic(const Duration(seconds: 10), (_) => _start());
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(startupProvider.select((s) => s.status), (_, status) => _scheduleRetry(status));
    final state = ref.watch(startupProvider);
    final texts = ref.watch(textsProvider);

    Widget gate(StateView view) => Scaffold(body: SafeArea(child: view));

    return switch (state.status) {
      StartupStatus.loading || StartupStatus.ready => const SplashView(),
      StartupStatus.offline => gate(StateView(
          icon: 'globe',
          title: texts.t('app.state.offline_title'),
          body: texts.t('app.state.offline_body'),
          actionLabel: texts.t('mobile.splash.retry'),
          onAction: _start,
        )),
      StartupStatus.unreachable => gate(StateView(
          icon: 'alert_triangle',
          tone: StateTone.danger,
          title: texts.t('app.state.unreachable_title'),
          body: texts.t('app.state.unreachable_body', {'code': state.errorCode}),
          actionLabel: texts.t('mobile.splash.retry'),
          onAction: _start,
        )),
      StartupStatus.unavailable => gate(StateView(
          icon: 'alert_triangle',
          tone: StateTone.danger,
          title: texts.t('app.state.unavailable_title'),
          body: texts.t('app.state.unavailable_body'),
          actionLabel: texts.t('app.state.close'),
          onAction: SystemNavigator.pop,
        )),
      StartupStatus.updateRequired => gate(StateView(
          icon: 'download',
          tone: StateTone.info,
          title: texts.t('mobile.update.required_title'),
          body: texts.t('mobile.update.required_body'),
          actionLabel: texts.t('mobile.update.update_now'),
          onAction: () => openStoreListing(state.bootstrap?.update.storeUrl),
        )),
      StartupStatus.maintenance => gate(StateView(
          icon: 'settings',
          title: texts.t('mobile.maintenance.title'),
          body: (state.bootstrap?.maintenance.message?.isNotEmpty ?? false)
              ? state.bootstrap!.maintenance.message
              : texts.t('mobile.maintenance.body'),
          actionLabel: texts.t('app.state.retry'),
          onAction: _start,
        )),
    };
  }
}

/// App Store / Play listing from `app.update.store_url`.
Future<void> openStoreListing(String? url) async {
  final uri = url == null ? null : Uri.tryParse(url);

  if (uri != null) {
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}

/// Brand colour background, 96 white tile (radius 24) with the store logo (contain),
/// 28 bold store name, 28 loader (static when the OS asks for reduced motion).
class SplashView extends ConsumerWidget {
  const SplashView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(tenantConfigProvider);
    final bootstrap = ref.watch(startupProvider.select((s) => s.bootstrap));
    final branding = bootstrap?.branding;
    final background = AppColors.parseHex(branding?.splashBackground) ?? context.colors.primary;
    final onBackground = AppColors.onColor(background);
    final logo = branding?.splashLogoUrl ?? bootstrap?.storeLogoUrl;
    final reduceMotion = MediaQuery.maybeDisableAnimationsOf(context) ?? false;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: onBackground == Colors.white ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: background,
        body: Center(
          child: Semantics(
            label: ref.t('mobile.splash.loading'),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 96,
                  height: 96,
                  decoration: BoxDecoration(color: onBackground, borderRadius: BorderRadius.circular(24)),
                  padding: EdgeInsets.all(logo == null ? 24 : 14),
                  child: logo == null
                      ? AppIcon('store', size: 48, color: background)
                      : Image.network(
                          logo,
                          fit: BoxFit.contain,
                          errorBuilder: (_, _, _) => AppIcon('store', size: 48, color: background),
                        ),
                ),
                const SizedBox(height: 16),
                Text(
                  bootstrap?.storeName.isNotEmpty == true ? bootstrap!.storeName : config.appName,
                  style: Theme.of(context).textTheme.displaySmall?.copyWith(color: onBackground),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: 28,
                  height: 28,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.8,
                    color: onBackground,
                    value: reduceMotion ? 0.85 : null,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// APP-002 optional update prompt, shown once per start when the store says a newer
/// version exists.
Future<void> showOptionalUpdate(BuildContext context, WidgetRef ref, Bootstrap bootstrap) {
  final texts = ref.read(textsProvider);

  return showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(texts.t('mobile.update.optional_title')),
      content: Text(texts.t('mobile.update.optional_body')),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: Text(texts.t('mobile.update.later')),
        ),
        TextButton(
          onPressed: () {
            Navigator.of(dialogContext).pop();
            openStoreListing(bootstrap.update.storeUrl);
          },
          child: Text(texts.t('mobile.update.update_now')),
        ),
      ],
    ),
  ).whenComplete(() => ref.read(startupProvider.notifier).dismissUpdate());
}
