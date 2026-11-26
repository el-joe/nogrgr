import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../core/widgets/select_field.dart';
import '../bootstrap/data/bootstrap.dart';
import '../bootstrap/data/startup_controller.dart';

/// Country, language and display currency of the store (APP-003 / APP-029). Options come
/// from `GET bootstrap`; prices are charged in USD (`mobile.setup.currency_note`).
class PreferencesForm extends ConsumerWidget {
  const PreferencesForm({super.key});

  Future<void> _setLocale(WidgetRef ref, String code) async {
    await ref.read(preferencesProvider.notifier).update((p) => p.copyWith(locale: code));
    await ref.read(textsProvider.notifier).load(code);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final prefs = ref.watch(preferencesProvider);
    final locale = ref.watch(localeCodeProvider);
    final bootstrap = ref.watch(startupProvider.select((s) => s.bootstrap));
    final languages = bootstrap?.languages ?? const <Language>[];
    final notifier = ref.read(preferencesProvider.notifier);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AppSelectField<String>(
          key: const ValueKey('setup-country'),
          label: texts.t('mobile.setup.country'),
          value: prefs.country,
          options: [
            for (final country in bootstrap?.countries ?? const <CountryOption>[])
              SelectOption(value: country.iso2, label: country.name, leading: country.flag),
          ],
          onChanged: (iso2) => notifier.update((p) => p.copyWith(country: iso2)),
        ),
        const SizedBox(height: 8),
        AppSelectField<String>(
          key: const ValueKey('setup-language'),
          label: texts.t('mobile.setup.language'),
          value: locale,
          options: [for (final language in languages) SelectOption(value: language.code, label: language.name)],
          onChanged: (code) => _setLocale(ref, code),
        ),
        const SizedBox(height: 8),
        AppSelectField<String>(
          key: const ValueKey('setup-currency'),
          label: texts.t('mobile.setup.currency'),
          value: prefs.currency,
          helper: texts.t('mobile.setup.currency_note'),
          options: [
            for (final currency in bootstrap?.currencies ?? const <CurrencyOption>[])
              SelectOption(value: currency.code, label: '${currency.code} — ${currency.name}'),
          ],
          onChanged: (code) => notifier.update((p) => p.copyWith(currency: code)),
        ),
        if (languages.length > 1) ...[
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final language in languages)
                AppChip(
                  label: language.name,
                  selected: language.code == locale,
                  onTap: () => _setLocale(ref, language.code),
                ),
            ],
          ),
        ],
      ],
    );
  }
}
