import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../data/auth_repository.dart';
import '../data/session_controller.dart';
import 'auth_form.dart';
import 'resend_countdown.dart';

/// E-mail confirmation with the 6-digit code sent at sign-up (same layout as APP-023;
/// texts `auth.verify_*`). Submits on the sixth digit; "Later" keeps shopping.
class VerifyEmailScreen extends ConsumerStatefulWidget {
  const VerifyEmailScreen({super.key});

  @override
  ConsumerState<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends ConsumerState<VerifyEmailScreen> {
  final _code = TextEditingController();
  String? _error;
  bool _busy = false;
  int _resendIn = 0;
  int? _expiresIn;

  @override
  void initState() {
    super.initState();
    _loadStatus();
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _loadStatus() async {
    try {
      final status = await ref.read(authRepositoryProvider).verificationStatus();

      if (!mounted) {
        return;
      }

      if (status.verified) {
        ref.read(sessionProvider.notifier).markVerified();
        context.go(Routes.account);
      } else {
        setState(() {
          _resendIn = status.resendIn;
          _expiresIn = status.expiresInMinutes;
        });
      }
    } on ApiException {
      // The form still works; the countdown starts at 0.
    }
  }

  Future<void> _submit() async {
    final texts = ref.read(textsProvider);
    final error = Validators.code(texts, _code.text);
    setState(() => _error = error);

    if (error != null || _busy) {
      return;
    }

    setState(() => _busy = true);

    try {
      await ref.read(authRepositoryProvider).verifyEmail(_code.text);
      ref.read(sessionProvider.notifier).markVerified();

      if (mounted) {
        showAppMessage(context, texts.t('auth.verified'));
        context.go(Routes.account);
      }
    } on ApiException catch (error) {
      setState(() => _error = error.fieldError('code') ?? apiErrorMessage(texts, error));
      _code.clear();
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final email = ref.watch(sessionProvider.select((s) => s.customer?.email)) ?? '';
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return AuthScaffold(
      title: texts.t('auth.verify_title'),
      onBack: () => context.go(Routes.account),
      bottom: AppButton(
        key: const ValueKey('verify-submit'),
        label: texts.t('auth.verify_submit'),
        loading: _busy,
        onPressed: _submit,
      ),
      children: [
        Text(texts.t('auth.verify_text', {'email': email}), style: text.bodyLarge?.copyWith(color: colors.muted)),
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            CodeInput(
              controller: _code,
              hasError: _error != null,
              onCompleted: (_) => _submit(),
              digitLabel: (number) => texts.t('auth.digit', {'number': number}),
            ),
            if (_error != null) ...[
              const SizedBox(height: 6),
              Text(_error!, style: text.labelSmall?.copyWith(color: colors.danger, fontWeight: FontWeight.w400)),
            ],
          ],
        ),
        ResendCountdown(
          seconds: _resendIn,
          waitingLabel: (time) => texts.t('app.auth.resend_in', {'time': time}),
          resendLabel: texts.t('auth.resend'),
          onResend: () async {
            try {
              final status = await ref.read(authRepositoryProvider).resendVerification();

              if (context.mounted && status.message.isNotEmpty) {
                showAppMessage(context, status.message);
              }

              return status.resendIn;
            } on ApiException catch (error) {
              if (context.mounted) {
                showAppMessage(context, apiErrorMessage(texts, error));
              }

              return error.retryAfter?.inSeconds ?? 0;
            }
          },
        ),
        if (_expiresIn != null)
          Text(texts.t('auth.expires', {'minutes': _expiresIn}), style: text.bodyMedium?.copyWith(color: colors.muted)),
        AppLink(
          label: texts.t('app.auth.verify_later'),
          muted: true,
          align: TextAlign.center,
          onTap: () => context.go(Routes.account),
        ),
      ],
    );
  }
}
