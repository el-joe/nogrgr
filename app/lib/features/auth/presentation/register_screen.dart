import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../bootstrap/data/bootstrap.dart';
import '../../bootstrap/data/startup_controller.dart';
import '../data/session_controller.dart';
import 'auth_form.dart';

/// APP-021 create account (Figma 149:104186): full name, e-mail, phone (with the
/// shopping country's dial code; ISO2 sent as `phone_country`), password. Then the
/// 6-digit e-mail code screen.
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  Map<String, String?> _errors = const {};
  String? _formError;
  bool _busy = false;
  String? _phoneCountry;

  @override
  void dispose() {
    for (final controller in [_name, _email, _phone, _password]) {
      controller.dispose();
    }

    super.dispose();
  }

  CountryOption? _country(List<CountryOption> countries) {
    final iso2 = _phoneCountry ?? ref.read(preferencesProvider).country;

    for (final country in countries) {
      if (country.iso2 == iso2) {
        return country;
      }
    }

    return countries.isEmpty ? null : countries.first;
  }

  Future<void> _pickCountry(List<CountryOption> countries) async {
    final picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            for (final country in countries)
              ListTile(
                leading: Text(country.flag, style: const TextStyle(fontSize: 20)),
                title: Text(country.name),
                trailing: Text('+${country.phoneCode}'),
                onTap: () => Navigator.of(sheetContext).pop(country.iso2),
              ),
          ],
        ),
      ),
    );

    if (picked != null) {
      setState(() => _phoneCountry = picked);
    }
  }

  Future<void> _submit(CountryOption? country) async {
    final texts = ref.read(textsProvider);
    final errors = {
      'name': Validators.required(texts, _name.text),
      'email': Validators.email(texts, _email.text),
      'password': Validators.password(texts, _password.text),
    };
    setState(() {
      _errors = errors;
      _formError = null;
    });

    if (errors.values.any((error) => error != null)) {
      return;
    }

    setState(() => _busy = true);

    try {
      await ref.read(sessionProvider.notifier).register(
            name: _name.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            phone: _phone.text.trim(),
            phoneCountry: country?.iso2,
          );

      if (mounted) {
        context.go(Routes.verify);
      }
    } on ApiException catch (error) {
      setState(() {
        _errors = {
          'name': error.fieldError('name'),
          'email': error.fieldError('email'),
          'phone': error.fieldError('phone') ?? error.fieldError('phone_country'),
          'password': error.fieldError('password'),
        };
        _formError = _errors.values.any((e) => e != null) ? null : apiErrorMessage(texts, error);
      });
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final countries = ref.watch(startupProvider.select((s) => s.bootstrap?.countries)) ?? const <CountryOption>[];
    final country = _country(countries);
    final text = Theme.of(context).textTheme;

    return AuthScaffold(
      title: texts.t('mobile.auth.register_title'),
      bottom: AppButton(
        key: const ValueKey('register-submit'),
        label: texts.t('mobile.auth.register_title'),
        loading: _busy,
        onPressed: () => _submit(country),
      ),
      children: [
        if (_formError != null) FormBanner(message: _formError!),
        AppTextField(
          fieldKey: const ValueKey('register-name'),
          label: texts.t('app.auth.name'),
          controller: _name,
          error: _errors['name'],
          keyboardType: TextInputType.name,
          autofillHints: const [AutofillHints.name],
        ),
        AppTextField(
          fieldKey: const ValueKey('register-email'),
          label: texts.t('app.auth.email'),
          controller: _email,
          error: _errors['email'],
          keyboardType: TextInputType.emailAddress,
          autofillHints: const [AutofillHints.email],
        ),
        AppTextField(
          fieldKey: const ValueKey('register-phone'),
          label: texts.t('app.auth.phone'),
          controller: _phone,
          error: _errors['phone'],
          keyboardType: TextInputType.phone,
          autofillHints: const [AutofillHints.telephoneNumberNational],
          prefix: country == null
              ? null
              : Semantics(
                  button: true,
                  label: texts.t('app.auth.phone_country'),
                  child: InkWell(
                    onTap: countries.length > 1 ? () => _pickCountry(countries) : null,
                    child: Padding(
                      padding: const EdgeInsetsDirectional.only(start: 14, end: 8),
                      child: Directionality(
                        textDirection: TextDirection.ltr,
                        child: Text('${country.flag} +${country.phoneCode}', style: text.bodyLarge),
                      ),
                    ),
                  ),
                ),
        ),
        AppTextField(
          fieldKey: const ValueKey('register-password'),
          label: texts.t('app.auth.password'),
          controller: _password,
          error: _errors['password'],
          obscure: true,
          showPasswordLabel: texts.t('app.auth.show_password'),
          hidePasswordLabel: texts.t('app.auth.hide_password'),
          textInputAction: TextInputAction.done,
          autofillHints: const [AutofillHints.newPassword],
        ),
        Text(texts.t('app.auth.terms'), style: text.bodyMedium?.copyWith(color: context.colors.muted)),
      ],
    );
  }
}
