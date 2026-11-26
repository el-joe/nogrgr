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
import 'reset_password_screen.dart';

/// APP-022 forgot password (Figma 149:104231): e-mail → 6-digit code by mail (the API
/// never reveals whether the account exists) → APP-023.
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _email = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final texts = ref.read(textsProvider);
    final error = Validators.email(texts, _email.text);
    setState(() => _error = error);

    if (error != null) {
      return;
    }

    setState(() => _busy = true);

    try {
      final email = _email.text.trim();
      final wait = await ref.read(authRepositoryProvider).forgotPassword(email);

      if (mounted) {
        showAppMessage(context, texts.t('mobile.reset.sent'));
        context.pushReplacement(Routes.reset, extra: ResetArgs(email: email, resendIn: wait));
      }
    } on ApiException catch (error) {
      setState(() => _error = error.fieldError('email') ?? apiErrorMessage(texts, error));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);

    return AuthScaffold(
      title: texts.t('app.auth.forgot_title'),
      bottom: AppButton(
        key: const ValueKey('forgot-submit'),
        label: texts.t('app.auth.send_code'),
        loading: _busy,
        onPressed: _submit,
      ),
      children: [
        Text(texts.t('app.auth.forgot_intro'), style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: context.colors.muted)),
        AppTextField(
          fieldKey: const ValueKey('forgot-email'),
          label: texts.t('app.auth.email'),
          controller: _email,
          error: _error,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.done,
          autofillHints: const [AutofillHints.email],
          onSubmitted: (_) => _submit(),
        ),
      ],
    );
  }
}
