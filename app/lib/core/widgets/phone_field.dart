import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';
import 'app_widgets.dart';

typedef PhoneCountry = ({String iso2, String name, String flag, String phoneCode});

/// International phone input: flag + dial code prefix (country sheet) and the national
/// number. The ISO2 travels as `phone_country`; the server validates the number for it.
class IntlPhoneField extends StatelessWidget {
  const IntlPhoneField({
    super.key,
    required this.label,
    required this.controller,
    required this.countries,
    required this.iso2,
    required this.onCountry,
    this.error,
    this.countryLabel,
    this.fieldKey,
  });

  final String label;
  final TextEditingController controller;
  final List<PhoneCountry> countries;
  final String? iso2;
  final ValueChanged<String> onCountry;
  final String? error;
  final String? countryLabel;
  final Key? fieldKey;

  /// Selected country (the given ISO2, else the first).
  static PhoneCountry? pick(List<PhoneCountry> countries, String? iso2) {
    for (final country in countries) {
      if (country.iso2 == iso2) {
        return country;
      }
    }

    return countries.isEmpty ? null : countries.first;
  }

  Future<void> _choose(BuildContext context) async {
    final picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (sheetContext) => FractionallySizedBox(
        heightFactor: 0.7,
        child: ListView(
          children: [
            for (final country in countries)
              ListTile(
                leading: Text(country.flag, style: const TextStyle(fontSize: 20)),
                title: Text(country.name),
                trailing: Directionality(textDirection: TextDirection.ltr, child: Text('+${country.phoneCode}')),
                onTap: () => Navigator.of(sheetContext).pop(country.iso2),
              ),
          ],
        ),
      ),
    );

    if (picked != null) {
      onCountry(picked);
    }
  }

  @override
  Widget build(BuildContext context) {
    final country = pick(countries, iso2);

    return AppTextField(
      fieldKey: fieldKey,
      label: label,
      controller: controller,
      error: error,
      keyboardType: TextInputType.phone,
      autofillHints: const [AutofillHints.telephoneNumberNational],
      prefix: country == null
          ? null
          : Semantics(
              button: true,
              label: countryLabel,
              child: InkWell(
                onTap: countries.length > 1 ? () => _choose(context) : null,
                child: Padding(
                  padding: const EdgeInsetsDirectional.only(start: 14, end: 8),
                  child: Directionality(
                    textDirection: TextDirection.ltr,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text('${country.flag} +${country.phoneCode}', style: Theme.of(context).textTheme.bodyLarge),
                        if (countries.length > 1) Icon(Icons.keyboard_arrow_down_rounded, size: 20, color: context.colors.muted),
                      ],
                    ),
                  ),
                ),
              ),
            ),
    );
  }
}
