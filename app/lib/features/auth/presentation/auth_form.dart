import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/i18n/app_texts.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/widgets/app_widgets.dart';

final _emailPattern = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');

/// Client-side checks mirroring the API rules (the API stays the authority: its 422
/// messages replace these).
abstract final class Validators {
  static String? required(AppTexts texts, String value) =>
      value.trim().isEmpty ? texts.t('app.validation.required') : null;

  static String? email(AppTexts texts, String value) {
    if (value.trim().isEmpty) {
      return texts.t('app.validation.required');
    }

    return _emailPattern.hasMatch(value.trim()) ? null : texts.t('app.validation.email');
  }

  static String? password(AppTexts texts, String value) {
    if (value.isEmpty) {
      return texts.t('app.validation.required');
    }

    return value.length < 8 ? texts.t('app.validation.password_min') : null;
  }

  static String? code(AppTexts texts, String value) =>
      RegExp(r'^\d{6}$').hasMatch(value) ? null : texts.t('app.validation.code');
}

/// Message for a failed call: the API's own (translated) message, else ours.
String apiErrorMessage(AppTexts texts, ApiException error) {
  return switch (error.kind) {
    ApiErrorKind.network => texts.t('mobile.errors.network'),
    ApiErrorKind.throttled when error.retryAfter != null =>
      texts.t('app.validation.throttled', {'seconds': error.retryAfter!.inSeconds}),
    _ when error.message.isNotEmpty => error.message,
    _ => texts.t('mobile.errors.generic'),
  };
}

/// Back from an auth screen: pop when there is a page below, else the account tab.
void leaveAuth(BuildContext context) {
  if (context.canPop()) {
    context.pop();
  } else {
    context.go(Routes.account);
  }
}

/// Shared frame of the auth screens (Figma 149:104141 …): top bar with back chevron,
/// scrolling content (16 sides, 12 top, 16 gaps) and an optional bottom action bar.
class AuthScaffold extends ConsumerWidget {
  const AuthScaffold({super.key, required this.title, required this.children, this.bottom, this.onBack});

  final String title;
  final List<Widget> children;
  final Widget? bottom;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      body: SafeArea(
        bottom: bottom == null,
        child: AutofillGroup(
          child: Column(
            children: [
              AppTopBar(
                title: title,
                backLabel: ref.t('app.auth.back'),
                onBack: onBack ?? () => leaveAuth(context),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                  children: [
                    for (var i = 0; i < children.length; i++) ...[
                      if (i > 0) const SizedBox(height: 16),
                      children[i],
                    ],
                  ],
                ),
              ),
              if (bottom != null) BottomActionBar(child: bottom!),
            ],
          ),
        ),
      ),
    );
  }
}

/// Inline banner for a form-level error / notice.
class FormBanner extends StatelessWidget {
  const FormBanner({super.key, required this.message, this.danger = true});

  final String message;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final color = danger ? scheme.error : scheme.primary;

    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(8)),
        child: Text(message, style: theme.textTheme.labelMedium?.copyWith(color: color)),
      ),
    );
  }
}
