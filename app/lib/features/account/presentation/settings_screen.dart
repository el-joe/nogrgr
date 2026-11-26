import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/select_field.dart';
import '../../auth/presentation/auth_form.dart';
import '../../bootstrap/data/startup_controller.dart';
import '../../onboarding/preferences_form.dart';

/// APP-029 settings (batch 1: country, language, currency, light / dark). Push switches
/// per type join with the notification preferences screen.
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
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
