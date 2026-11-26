import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../data/auth_repository.dart';
import 'auth_form.dart';
import 'resend_countdown.dart';

class ResetArgs {
  const ResetArgs({required this.email, this.resendIn = 45});

  final String email;
  final int resendIn;
}

/// APP-023 reset password with the e-mailed code (Figma 149:104261): six code boxes,
/// resend countdown, new + confirm password, bottom "Update password".
class ResetPasswordScreen extends ConsumerStatefulWidget {
  const ResetPasswordScreen({super.key, required this.args});

  final ResetArgs args;

  @override
  ConsumerState<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends ConsumerState<ResetPasswordScreen> {
  final _code = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  Map<String, String?> _errors = const {};
  bool _busy = false;

  @override
  void dispose() {
    for (final controller in [_code, _password, _confirm]) {
      controller.dispose();
    }

    super.dispose();
  }

  Future<void> _submit() async {
    final texts = ref.read(textsProvider);
    final errors = {
      'code': Validators.code(texts, _code.text),
      'password': Validators.password(texts, _password.text),
      'password_confirmation': _confirm.text == _password.text ? null : texts.t('app.validation.password_match'),
    };
    setState(() => _errors = errors);

    if (errors.values.any((error) => error != null)) {
      return;
    }

    setState(() => _busy = true);

    try {
      final message = await ref.read(authRepositoryProvider).resetPassword(
            email: widget.args.email,
            code: _code.text,
            password: _password.text,
          );

      if (mounted) {
        showAppMessage(context, message.isNotEmpty ? message : texts.t('mobile.reset.done'));
        context.go(Routes.login);
      }
    } on ApiException catch (error) {
      setState(() {
        _errors = {
          'code': error.fieldError('code') ?? error.fieldError('email'),
          'password': error.fieldError('password'),
        };

        if (_errors.values.every((e) => e == null)) {
          _errors = {'code': apiErrorMessage(texts, error)};
        }
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
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return AuthScaffold(
      title: texts.t('mobile.reset.title'),
      bottom: AppButton(
        key: const ValueKey('reset-submit'),
        label: texts.t('app.auth.update_password'),
        loading: _busy,
        onPressed: _submit,
      ),
      children: [
        Text(texts.t('app.auth.reset_intro', {'email': widget.args.email}), style: text.bodyLarge?.copyWith(color: colors.muted)),
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            CodeInput(
              controller: _code,
              hasError: _errors['code'] != null,
              digitLabel: (number) => texts.t('app.auth.digit', {'number': number}),
            ),
            if (_errors['code'] != null) ...[
              const SizedBox(height: 6),
              Text(_errors['code']!, style: text.labelSmall?.copyWith(color: colors.danger, fontWeight: FontWeight.w400)),
            ],
          ],
        ),
        ResendCountdown(
          seconds: widget.args.resendIn,
          waitingLabel: (time) => texts.t('app.auth.resend_in', {'time': time}),
          resendLabel: texts.t('mobile.reset.resend'),
          onResend: () async {
            try {
              final wait = await ref.read(authRepositoryProvider).forgotPassword(widget.args.email);

              if (context.mounted) {
                showAppMessage(context, texts.t('mobile.reset.sent'));
              }

              return wait;
            } on ApiException catch (error) {
              if (context.mounted) {
                showAppMessage(context, apiErrorMessage(texts, error));
              }

              return error.retryAfter?.inSeconds ?? 0;
            }
          },
        ),
        AppTextField(
          fieldKey: const ValueKey('reset-password'),
          label: texts.t('mobile.reset.new_password'),
          controller: _password,
          error: _errors['password'],
          obscure: true,
          showPasswordLabel: texts.t('app.auth.show_password'),
          hidePasswordLabel: texts.t('app.auth.hide_password'),
          autofillHints: const [AutofillHints.newPassword],
        ),
        AppTextField(
          fieldKey: const ValueKey('reset-confirm'),
          label: texts.t('app.auth.confirm_password'),
          controller: _confirm,
          error: _errors['password_confirmation'],
          obscure: true,
          showPasswordLabel: texts.t('app.auth.show_password'),
          hidePasswordLabel: texts.t('app.auth.hide_password'),
          textInputAction: TextInputAction.done,
          autofillHints: const [AutofillHints.newPassword],
          onSubmitted: (_) => _submit(),
        ),
      ],
    );
  }
}
