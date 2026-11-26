import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../core/widgets/app_widgets.dart';
import '../bootstrap/data/startup_controller.dart';
import 'preferences_form.dart';

/// APP-003 first run (Figma 149:104095): country, language, currency, Continue.
class SetupScreen extends ConsumerWidget {
  const SetupScreen({super.key});

  Future<void> _continue(WidgetRef ref) async {
    await ref.read(preferencesProvider.notifier).update((p) => p.copyWith(setupDone: true));
    // Country / language decide theme, texts and prices: refresh the store data.
    ref.read(startupProvider.notifier).reload().ignore();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final text = Theme.of(context).textTheme;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppTopBar(title: texts.t('app.setup.welcome')),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                children: [
                  Text(texts.t('mobile.setup.title'), style: text.headlineSmall),
                  const SizedBox(height: 16),
                  Text(texts.t('app.setup.intro'), style: text.bodyMedium),
                  const SizedBox(height: 16),
                  const PreferencesForm(),
                ],
              ),
            ),
            BottomActionBar(child: AppButton(label: texts.t('mobile.setup.continue'), onPressed: () => _continue(ref))),
          ],
        ),
      ),
    );
  }
}
