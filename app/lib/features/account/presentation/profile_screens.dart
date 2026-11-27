import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/models/json.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../../core/widgets/phone_field.dart';
import '../../auth/data/auth_repository.dart';
import '../../auth/data/session_controller.dart';
import '../../auth/presentation/auth_form.dart';
import '../../bootstrap/data/startup_controller.dart';

/// Edit profile (APP-025, Figma 149:104581): name, e-mail (re-verification), intl phone.
class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  late final Customer? _customer = ref.read(sessionProvider).customer;
  late final _name = TextEditingController(text: _customer?.name);
  late final _email = TextEditingController(text: _customer?.email);
  late final _phone = TextEditingController(text: _customer?.phone);
  late String? _phoneCountry = _customer?.phoneCountry;
  Map<String, String?> _errors = const {};
  String? _formError;
  bool _busy = false;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  List<PhoneCountry> get _countries => [
        for (final c in ref.read(startupProvider).bootstrap?.countries ?? const []) (iso2: c.iso2, name: c.name, flag: c.flag, phoneCode: c.phoneCode),
      ];

  Future<void> _save() async {
    final texts = ref.read(textsProvider);
    final errors = {'name': Validators.required(texts, _name.text), 'email': Validators.email(texts, _email.text)};
    setState(() {
      _errors = errors;
      _formError = null;
    });

    if (errors.values.any((e) => e != null)) {
      return;
    }

    setState(() => _busy = true);

    try {
      final phone = _phone.text.trim();
      final response = await ref.read(apiClientProvider).put('account/profile', body: {
        'name': _name.text.trim(),
        'email': _email.text.trim(),
        'phone': phone.isEmpty ? null : phone,
        'phone_country': phone.isEmpty ? null : IntlPhoneField.pick(_countries, _phoneCountry ?? ref.read(preferencesProvider).country)?.iso2,
        'locale': ref.read(localeCodeProvider),
      });
      ref.read(sessionProvider.notifier).updateCustomer(Customer.fromJson(jsonMap(response.map['customer'])));

      if (mounted) {
        showAppMessage(context, response.message.isEmpty ? texts.t('app.settings.saved') : response.message);
        context.pop();
      }
    } on ApiException catch (error) {
      setState(() {
        _errors = {'name': error.fieldError('name'), 'email': error.fieldError('email'), 'phone': error.fieldError('phone') ?? error.fieldError('phone_country')};
        _formError = error.errors.isEmpty ? apiErrorMessage(texts, error) : null;
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

    return PageScaffold(
      title: texts.t('app.account.edit_profile'),
      bottom: AppButton(key: const ValueKey('profile-save'), label: texts.t('app.common.save'), loading: _busy, onPressed: _save),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          if (_formError != null) FormBanner(message: _formError!),
          AppTextField(fieldKey: const ValueKey('profile-name'), label: texts.t('app.auth.name'), controller: _name, error: _errors['name'], autofillHints: const [AutofillHints.name]),
          AppTextField(fieldKey: const ValueKey('profile-email'), label: texts.t('app.auth.email'), controller: _email, error: _errors['email'], keyboardType: TextInputType.emailAddress),
          Text(texts.t('account.email_change_hint'), style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 12),
          IntlPhoneField(
            fieldKey: const ValueKey('profile-phone'),
            label: texts.t('app.auth.phone'),
            countryLabel: texts.t('app.auth.phone_country'),
            controller: _phone,
            countries: _countries,
            iso2: _phoneCountry ?? ref.watch(preferencesProvider).country,
            onCountry: (iso2) => setState(() => _phoneCountry = iso2),
            error: _errors['phone'],
          ),
        ],
      ),
    );
  }
}

/// Change password (APP-026, Figma 149:104651): current + new + confirm; other installs
/// are signed out by the server.
class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  Map<String, String?> _errors = const {};
  bool _busy = false;

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final texts = ref.read(textsProvider);
    final errors = {
      'current_password': Validators.required(texts, _current.text),
      'password': Validators.password(texts, _password.text),
      'password_confirmation': _confirm.text != _password.text ? texts.t('app.validation.password_match') : null,
    };
    setState(() => _errors = errors);

    if (errors.values.any((e) => e != null)) {
      return;
    }

    setState(() => _busy = true);

    try {
      final response = await ref.read(apiClientProvider).put('account/password', body: {
        'current_password': _current.text,
        'password': _password.text,
        'password_confirmation': _confirm.text,
      });

      if (mounted) {
        showAppMessage(context, response.message.isEmpty ? texts.t('app.settings.saved') : response.message);
        context.pop();
      }
    } on ApiException catch (error) {
      setState(() => _errors = {
            'current_password': error.fieldError('current_password') ?? (error.errors.isEmpty ? apiErrorMessage(texts, error) : null),
            'password': error.fieldError('password'),
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
    AppTextField field(String key, String label, TextEditingController c, {List<String> hints = const [AutofillHints.newPassword]}) => AppTextField(
          fieldKey: ValueKey('password-$key'),
          label: label,
          controller: c,
          error: _errors[key],
          obscure: true,
          showPasswordLabel: texts.t('app.auth.show_password'),
          hidePasswordLabel: texts.t('app.auth.hide_password'),
          autofillHints: hints,
        );

    return PageScaffold(
      title: texts.t('app.account.change_password'),
      bottom: AppButton(key: const ValueKey('password-save'), label: texts.t('account.update_password'), loading: _busy, onPressed: _save),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          field('current_password', texts.t('app.account.current_password'), _current, hints: const [AutofillHints.password]),
          field('password', texts.t('app.auth.password'), _password),
          Text(texts.t('account.password_hint'), style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 12),
          field('password_confirmation', texts.t('account.confirm_new_password'), _confirm),
        ],
      ),
    );
  }
}

/// Delete account (APP-027, Figma 149:104690): what is removed, then a confirm sheet
/// with the password; anonymised + soft deleted by the server, the app signs out.
class DeleteAccountScreen extends ConsumerWidget {
  const DeleteAccountScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return PageScaffold(
      title: texts.t('app.account.delete_account'),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: colors.dangerBg, borderRadius: BorderRadius.circular(10)),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AppIcon('alert_triangle', color: colors.danger),
                const SizedBox(width: 10),
                Expanded(child: Text(texts.t('account.delete_message'), style: text.bodyLarge)),
              ],
            ),
          ),
          const SizedBox(height: 24),
          AppButton(
            key: const ValueKey('delete-open'),
            label: texts.t('account.delete_confirm'),
            onPressed: () => showModalBottomSheet<void>(
              context: context,
              isScrollControlled: true,
              showDragHandle: true,
              builder: (_) => const _DeleteSheet(),
            ),
          ),
        ],
      ),
    );
  }
}

class _DeleteSheet extends ConsumerStatefulWidget {
  const _DeleteSheet();

  @override
  ConsumerState<_DeleteSheet> createState() => _DeleteSheetState();
}

class _DeleteSheetState extends ConsumerState<_DeleteSheet> {
  final _password = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _delete() async {
    final texts = ref.read(textsProvider);

    if (_password.text.isEmpty) {
      setState(() => _error = texts.t('app.validation.required'));

      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final response = await ref.read(apiClientProvider).delete('account', body: {'password': _password.text});
      await ref.read(sessionProvider.notifier).forget();

      if (mounted) {
        final router = GoRouter.of(context);
        final messenger = ScaffoldMessenger.of(context);
        Navigator.of(context).pop();
        router.go(Routes.account);
        messenger.showSnackBar(SnackBar(content: Text(response.message)));
      }
    } on ApiException catch (error) {
      setState(() => _error = error.fieldError('password') ?? apiErrorMessage(texts, error));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(texts.t('account.delete_title'), style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          Text(texts.t('account.delete_message'), style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 12),
          AppTextField(
            fieldKey: const ValueKey('delete-password'),
            label: texts.t('account.delete_password'),
            controller: _password,
            error: _error,
            obscure: true,
            showPasswordLabel: texts.t('app.auth.show_password'),
            hidePasswordLabel: texts.t('app.auth.hide_password'),
            autofillHints: const [AutofillHints.password],
          ),
          SizedBox(
            height: 52,
            child: FilledButton(
              key: const ValueKey('delete-confirm'),
              style: FilledButton.styleFrom(backgroundColor: context.colors.danger, shape: const StadiumBorder()),
              onPressed: _busy ? null : _delete,
              child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : Text(texts.t('account.delete_confirm')),
            ),
          ),
          const SizedBox(height: 8),
          AppButton(label: texts.t('account.cancel'), style: AppButtonStyle.outline, onPressed: () => Navigator.of(context).pop()),
        ],
      ),
    );
  }
}
