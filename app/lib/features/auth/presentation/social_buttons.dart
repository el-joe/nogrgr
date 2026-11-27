import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../bootstrap/data/startup_controller.dart';
import '../data/session_controller.dart';
import '../data/social_sign_in.dart';
import 'auth_form.dart';

/// Providers to offer: the store configured them (`bootstrap.auth.social_providers`)
/// AND this build / platform can run the SDK (client ids in tenant.json). Never a
/// button that cannot work.
final offeredSocialProvidersProvider = Provider<List<SocialProvider>>((ref) {
  final listed = ref.watch(startupProvider.select((s) => s.bootstrap?.socialProviders)) ?? const <String>[];
  final authenticator = ref.watch(socialAuthenticatorProvider);

  return [
    for (final name in listed)
      if (SocialProvider.parse(name) case final provider? when authenticator.supports(provider)) provider,
  ];
});

/// "or continue with" + Google / Apple buttons of sign in / sign up (APP-020 / 021,
/// Figma 149:104141 / 149:104186). Renders nothing when no provider is offered.
class SocialSignInButtons extends ConsumerStatefulWidget {
  const SocialSignInButtons({super.key});

  @override
  ConsumerState<SocialSignInButtons> createState() => _SocialSignInButtonsState();
}

class _SocialSignInButtonsState extends ConsumerState<SocialSignInButtons> {
  SocialProvider? _busy;
  String? _error;

  Future<void> _signIn(SocialProvider provider) async {
    final texts = ref.read(textsProvider);
    final label = texts.t('account.social.${provider.name}');
    setState(() {
      _busy = provider;
      _error = null;
    });

    try {
      final credential = await ref.read(socialAuthenticatorProvider).obtain(provider);

      if (credential == null) {
        return; // Cancelled in the native sheet.
      }

      final session = await ref.read(sessionProvider.notifier).social(
            provider.name,
            idToken: credential.idToken,
            nonce: credential.nonce,
            name: credential.name,
          );

      if (!mounted) {
        return;
      }

      if (session.message.isNotEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(session.message)));
      }

      context.go(session.customer.emailVerified ? Routes.account : Routes.verify);
    } on ApiException catch (error) {
      setState(() => _error = switch (error.statusCode) {
            404 => texts.t('app.auth.social_unavailable'),
            _ => apiErrorMessage(texts, error),
          });
    } on SocialSignInFailure {
      setState(() => _error = texts.t('app.auth.social_failed', {'provider': label}));
    } finally {
      if (mounted) {
        setState(() => _busy = null);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final providers = ref.watch(offeredSocialProvidersProvider);

    if (providers.isEmpty) {
      return const SizedBox.shrink();
    }

    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: Divider(color: colors.border)),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Text(texts.t('account.or_continue'), style: text.bodyMedium?.copyWith(color: colors.muted)),
            ),
            Expanded(child: Divider(color: colors.border)),
          ],
        ),
        const SizedBox(height: 16),
        if (_error != null) ...[FormBanner(message: _error!), const SizedBox(height: 12)],
        for (var i = 0; i < providers.length; i++) ...[
          if (i > 0) const SizedBox(height: 12),
          _SocialButton(
            key: ValueKey('social-${providers[i].name}'),
            provider: providers[i],
            label: texts.t('app.auth.continue_with', {'provider': texts.t('account.social.${providers[i].name}')}),
            loading: _busy == providers[i],
            onPressed: _busy == null ? () => _signIn(providers[i]) : null,
          ),
        ],
      ],
    );
  }
}

/// 52-high outlined pill with the provider mark (Google colours kept, Apple in ink).
class _SocialButton extends StatelessWidget {
  const _SocialButton({super.key, required this.provider, required this.label, required this.loading, required this.onPressed});

  final SocialProvider provider;
  final String label;
  final bool loading;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final mark = switch (provider) {
      SocialProvider.google => SvgPicture.asset('assets/images/brands/google.svg', width: 20, height: 20, fit: BoxFit.contain),
      SocialProvider.apple => Icon(Icons.apple, size: 22, color: colors.ink),
    };

    return Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      child: SizedBox(
        height: 52,
        child: OutlinedButton(
          onPressed: onPressed,
          style: OutlinedButton.styleFrom(
            foregroundColor: colors.ink,
            backgroundColor: colors.surface,
            side: BorderSide(color: colors.border),
            shape: const StadiumBorder(),
          ),
          child: loading
              ? SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: colors.primary))
              : Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    mark,
                    const SizedBox(width: 10),
                    Flexible(child: Text(label, style: Theme.of(context).textTheme.labelLarge?.copyWith(color: colors.ink), overflow: TextOverflow.ellipsis)),
                  ],
                ),
        ),
      ),
    );
  }
}
