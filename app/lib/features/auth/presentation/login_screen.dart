import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../data/session_controller.dart';
import 'auth_form.dart';
import 'social_buttons.dart';

/// APP-020 sign in (Figma 149:104141). Google / Apple buttons appear only for the
/// providers the store configured and this build can run (decision #108 e).
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  Map<String, String?> _errors = const {};
  String? _formError;
  bool _busy = false;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final texts = ref.read(textsProvider);
    final errors = {
      'email': Validators.email(texts, _email.text),
      'password': Validators.required(texts, _password.text),
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
      final customer = await ref.read(sessionProvider.notifier).login(_email.text.trim(), _password.text);

      if (!mounted) {
        return;
      }

      if (!customer.emailVerified) {
        context.go(Routes.verify);
      } else {
        context.go(Routes.account);
      }
    } on ApiException catch (error) {
      setState(() {
        _errors = {'email': error.fieldError('email'), 'password': error.fieldError('password')};
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
    final expired = ref.watch(sessionProvider.select((s) => s.expired));
    final text = Theme.of(context).textTheme;

    return AuthScaffold(
      title: texts.t('mobile.auth.login_title'),
      children: [
        Text(texts.t('app.auth.welcome_back'), style: text.displaySmall),
        Text(texts.t('app.auth.login_intro'), style: text.bodyLarge?.copyWith(color: context.colors.muted)),
        if (expired && _formError == null) FormBanner(message: texts.t('mobile.auth.session_expired')),
        if (_formError != null) FormBanner(message: _formError!),
        AppTextField(
          fieldKey: const ValueKey('login-email'),
          label: texts.t('app.auth.email'),
          controller: _email,
          error: _errors['email'],
          keyboardType: TextInputType.emailAddress,
          autofillHints: const [AutofillHints.email, AutofillHints.username],
        ),
        AppTextField(
          fieldKey: const ValueKey('login-password'),
          label: texts.t('app.auth.password'),
          controller: _password,
          error: _errors['password'],
          obscure: true,
          showPasswordLabel: texts.t('app.auth.show_password'),
          hidePasswordLabel: texts.t('app.auth.hide_password'),
          textInputAction: TextInputAction.done,
          autofillHints: const [AutofillHints.password],
          onSubmitted: (_) => _submit(),
        ),
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: AppLink(label: texts.t('app.auth.forgot_link'), onTap: () => context.push(Routes.forgot)),
        ),
        AppButton(
          key: const ValueKey('login-submit'),
          label: texts.t('mobile.auth.login_title'),
          loading: _busy,
          onPressed: _submit,
        ),
        const SocialSignInButtons(),
        AppLink(
          label: texts.t('app.auth.create_link'),
          align: TextAlign.center,
          onTap: () => context.pushReplacement(Routes.register),
        ),
        AppLink(
          label: texts.t('app.auth.guest'),
          muted: true,
          align: TextAlign.center,
          onTap: () => context.go(Routes.account),
        ),
      ],
    );
  }
}
