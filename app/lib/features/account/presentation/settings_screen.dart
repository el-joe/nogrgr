import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/push/push_service.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/select_field.dart';
import '../../auth/presentation/auth_form.dart';
import '../../bootstrap/data/startup_controller.dart';
import '../../auth/data/session_controller.dart';
import '../../onboarding/preferences_form.dart';
import '../data/notification_preferences.dart';

/// APP-029 settings: country, language, currency, light / dark and — signed in, when
/// the store sends pushes — one push switch per notification type
/// (`account/notification-preferences`).
class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Language / currency / country change prices and texts: re-read the store data.
    ref.listen(
      preferencesProvider.select((p) => (p.locale, p.currency, p.country)),
      (_, _) => ref.read(startupProvider.notifier).reload(),
    );
    final texts = ref.watch(textsProvider);
    final mode = ref.watch(preferencesProvider.select((p) => p.themeMode));
    final notifier = ref.read(preferencesProvider.notifier);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            AppTopBar(title: texts.t('app.settings.title'), backLabel: texts.t('app.auth.back'), onBack: () => leaveAuth(context)),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                children: [
                  const PreferencesForm(),
                  const SizedBox(height: 24),
                  Text(texts.t('app.settings.appearance'), style: Theme.of(context).textTheme.labelMedium),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    children: [
                      for (final (value, key) in const [
                        (ThemeMode.system, 'app.settings.theme_system'),
                        (ThemeMode.light, 'app.settings.theme_light'),
                        (ThemeMode.dark, 'app.settings.theme_dark'),
                      ])
                        AppChip(label: texts.t(key), selected: mode == value, onTap: () => notifier.setThemeMode(value)),
                    ],
                  ),
                  const PushPreferencesSection(),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Push switch per notification type; shown only to a signed-in customer of a store
/// with push on (bootstrap `push.enabled`).
class PushPreferencesSection extends ConsumerWidget {
  const PushPreferencesSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final signedIn = ref.watch(sessionProvider.select((s) => s.signedIn));
    final pushEnabled = ref.watch(startupProvider.select((s) => s.bootstrap?.pushEnabled ?? false));

    if (!signedIn || !pushEnabled) {
      return const SizedBox.shrink();
    }

    final texts = ref.watch(textsProvider);
    final prefs = ref.watch(notificationPreferencesProvider);
    final text = Theme.of(context).textTheme;
    final colors = context.colors;
    final osDenied = ref.watch(pushServiceProvider).permissionGranted == false;

    Future<void> toggle(String type, bool value) async {
      try {
        await ref.read(notificationPreferencesProvider.notifier).toggle(type, 'push', value);
      } on ApiException catch (error) {
        if (context.mounted) {
          showAppMessage(context, apiErrorMessage(texts, error));
        }
      }
    }

    return Column(
      key: const ValueKey('settings-push'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 24),
        Text(texts.t('mobile.notifications.push_title'), style: text.labelMedium),
        const SizedBox(height: 4),
        Text(texts.t('app.push.intro'), style: text.bodyMedium),
        if (osDenied) ...[const SizedBox(height: 8), FormBanner(message: texts.t('mobile.notifications.push_off'))],
        const SizedBox(height: 4),
        switch (prefs) {
          AsyncData(:final value) => Column(
              children: [
                for (final type in notificationTypes)
                  if (value[type] case final channels?)
                    SwitchListTile.adaptive(
                      key: ValueKey('push-$type'),
                      contentPadding: EdgeInsets.zero,
                      activeTrackColor: colors.primary,
                      title: Text(texts.t('app.push.types.$type'), style: text.bodyLarge),
                      subtitle: Text(texts.t('app.push.hints.$type'), style: text.bodyMedium),
                      value: channels['push'] ?? false,
                      onChanged: (value) => toggle(type, value),
                    ),
              ],
            ),
          AsyncError() => Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton(onPressed: () => ref.invalidate(notificationPreferencesProvider), child: Text(texts.t('app.state.retry'))),
            ),
          _ => const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator())),
        },
      ],
    );
  }
}
