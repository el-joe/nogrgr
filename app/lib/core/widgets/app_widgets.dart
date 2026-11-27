import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';

/// Figma icon set (24 grid, 1.75 stroke) recoloured with the token colour.
class AppIcon extends StatelessWidget {
  const AppIcon(this.name, {super.key, this.size = 24, this.color, this.mirrorInRtl = false});

  final String name;
  final double size;
  final Color? color;

  /// Chevrons / back arrows flip in Arabic.
  final bool mirrorInRtl;

  @override
  Widget build(BuildContext context) {
    final icon = SvgPicture.asset(
      'assets/icons/$name.svg',
      width: size,
      height: size,
      fit: BoxFit.contain,
      colorFilter: ColorFilter.mode(color ?? context.colors.ink, BlendMode.srcIn),
    );

    if (mirrorInRtl && Directionality.of(context) == TextDirection.rtl) {
      return Transform.flip(flipX: true, child: icon);
    }

    return icon;
  }
}

enum AppButtonStyle { primary, outline }

/// "App/Button": 52 high, pill radius, 15 medium label.
class AppButton extends StatelessWidget {
  const AppButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.style = AppButtonStyle.primary,
    this.loading = false,
    this.width = double.infinity,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonStyle style;
  final bool loading;
  final double width;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final primary = style == AppButtonStyle.primary;
    final foreground = primary ? colors.onPrimary : colors.ink;
    final enabled = onPressed != null && !loading;

    return Semantics(
      button: true,
      enabled: enabled,
      label: label,
      excludeSemantics: true,
      child: SizedBox(
        width: width,
        height: 52,
        child: Material(
          color: primary ? colors.primary.withValues(alpha: enabled || loading ? 1 : 0.5) : colors.surface,
          shape: StadiumBorder(
            side: primary ? BorderSide.none : BorderSide(color: colors.border, width: 1.5),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: enabled ? onPressed : null,
            child: Center(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 180),
                child: loading
                    ? SizedBox(
                        key: const ValueKey('loading'),
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.2, color: foreground),
                      )
                    : Text(
                        label,
                        key: const ValueKey('label'),
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(color: foreground),
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Text link in primary (or muted) colour, 13 medium.
class AppLink extends StatelessWidget {
  const AppLink({super.key, required this.label, required this.onTap, this.muted = false, this.align = TextAlign.start});

  final String label;
  final VoidCallback onTap;
  final bool muted;
  final TextAlign align;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(4),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Text(
          label,
          textAlign: align,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(color: muted ? colors.muted : colors.primary),
        ),
      ),
    );
  }
}

/// "App/Input": 13 medium label, 50 high field (radius 6, border #E3E3E3, danger on
/// error), 11 helper / error line.
class AppTextField extends StatefulWidget {
  const AppTextField({
    super.key,
    required this.label,
    required this.controller,
    this.error,
    this.keyboardType,
    this.obscure = false,
    this.textInputAction = TextInputAction.next,
    this.autofillHints,
    this.onSubmitted,
    this.showPasswordLabel,
    this.hidePasswordLabel,
    this.prefix,
    this.fieldKey,
    this.maxLines = 1,
    this.minLines,
    this.hint,
  });

  final String label;
  final TextEditingController controller;
  final String? error;
  final TextInputType? keyboardType;
  final bool obscure;
  final TextInputAction textInputAction;
  final Iterable<String>? autofillHints;
  final ValueChanged<String>? onSubmitted;
  final String? showPasswordLabel;
  final String? hidePasswordLabel;
  final Widget? prefix;
  final Key? fieldKey;

  /// Multi-line message boxes (support, returns): `null` grows with the text.
  final int? maxLines;
  final int? minLines;
  final String? hint;

  @override
  State<AppTextField> createState() => _AppTextFieldState();
}

class _AppTextFieldState extends State<AppTextField> {
  late bool _hidden = widget.obscure;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final hasError = widget.error != null && widget.error!.isNotEmpty;
    OutlineInputBorder border(Color color, [double width = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.input),
          borderSide: BorderSide(color: color, width: width),
        );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(widget.label, style: text.labelMedium),
        const SizedBox(height: 6),
        TextField(
          key: widget.fieldKey,
          controller: widget.controller,
          keyboardType: widget.keyboardType,
          obscureText: _hidden,
          maxLines: widget.obscure ? 1 : widget.maxLines,
          minLines: widget.minLines,
          textInputAction: widget.textInputAction,
          autofillHints: widget.autofillHints,
          onSubmitted: widget.onSubmitted,
          style: text.bodyLarge,
          decoration: InputDecoration(
            isDense: true,
            hintText: widget.hint,
            filled: true,
            fillColor: colors.surface,
            constraints: const BoxConstraints(minHeight: 50),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 15),
            enabledBorder: border(hasError ? colors.danger : colors.border),
            focusedBorder: border(hasError ? colors.danger : colors.primary, 1.5),
            prefixIcon: widget.prefix,
            prefixIconConstraints: const BoxConstraints(minWidth: 0, minHeight: 0),
            suffixIcon: widget.obscure
                ? IconButton(
                    tooltip: _hidden ? widget.showPasswordLabel : widget.hidePasswordLabel,
                    onPressed: () => setState(() => _hidden = !_hidden),
                    icon: Icon(_hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined, color: colors.muted, size: 20),
                  )
                : null,
          ),
        ),
        const SizedBox(height: 6),
        AnimatedSize(
          duration: const Duration(milliseconds: 150),
          alignment: AlignmentDirectional.topStart,
          child: Text(
            hasError ? widget.error! : ' ',
            style: text.labelSmall?.copyWith(fontWeight: FontWeight.w400, color: hasError ? colors.danger : colors.muted),
          ),
        ),
      ],
    );
  }
}

/// "App/Top bar": 52 high, back chevron + 17 semibold title.
class AppTopBar extends StatelessWidget implements PreferredSizeWidget {
  const AppTopBar({super.key, required this.title, this.onBack, this.backLabel, this.trailing});

  final String title;
  final VoidCallback? onBack;
  final String? backLabel;
  final Widget? trailing;

  @override
  Size get preferredSize => const Size.fromHeight(52);

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: AppTheme.overlayFor(context.colors.bg, navigationBar: context.colors.tabbar),
      child: _bar(context),
    );
  }

  Widget _bar(BuildContext context) {
    return Container(
      color: context.colors.bg,
      height: 52,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: [
          if (onBack != null) ...[
            Semantics(
              button: true,
              label: backLabel,
              child: InkResponse(
                onTap: onBack,
                radius: 22,
                child: const AppIcon('chevron_left', mirrorInRtl: true),
              ),
            ),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: Text(title, style: Theme.of(context).textTheme.titleLarge, maxLines: 1, overflow: TextOverflow.ellipsis),
          ),
          ?trailing,
        ],
      ),
    );
  }
}

enum StateTone { warning, info, danger, primary }

/// Centred system state (offline, update, maintenance, unavailable, empty, error):
/// 88 circle with a 40 icon, 22 semibold title, 15 muted body, 240-wide button.
class StateView extends StatelessWidget {
  const StateView({
    super.key,
    required this.icon,
    required this.title,
    this.body,
    this.actionLabel,
    this.onAction,
    this.tone = StateTone.warning,
    this.secondaryLabel,
    this.onSecondary,
  });

  final String icon;
  final String title;
  final String? body;
  final String? actionLabel;
  final VoidCallback? onAction;
  final StateTone tone;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final (background, foreground) = switch (tone) {
      StateTone.warning => (colors.warningBg, colors.warning),
      StateTone.info => (colors.infoBg, colors.info),
      StateTone.danger => (colors.dangerBg, colors.danger),
      StateTone.primary => (colors.primarySubtle, colors.primary),
    };

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 88,
              height: 88,
              decoration: BoxDecoration(color: background, shape: BoxShape.circle),
              alignment: Alignment.center,
              child: AppIcon(icon, size: 40, color: foreground),
            ),
            const SizedBox(height: 16),
            Text(title, style: text.headlineSmall, textAlign: TextAlign.center),
            if (body != null && body!.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text(body!, style: text.bodyLarge?.copyWith(color: colors.muted), textAlign: TextAlign.center),
            ],
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 16),
              AppButton(label: actionLabel!, onPressed: onAction, width: 240),
            ],
            if (secondaryLabel != null && onSecondary != null) ...[
              const SizedBox(height: 8),
              AppLink(label: secondaryLabel!, onTap: onSecondary!, muted: true, align: TextAlign.center),
            ],
          ],
        ),
      ),
    );
  }
}

/// Six 48×56 code boxes (APP-023 / e-mail verification). One hidden field drives
/// them, so paste and SMS / mail autofill work; the active box gets the primary border.
class CodeInput extends StatefulWidget {
  const CodeInput({
    super.key,
    required this.controller,
    this.length = 6,
    this.onCompleted,
    this.hasError = false,
    this.digitLabel,
  });

  final TextEditingController controller;
  final int length;
  final ValueChanged<String>? onCompleted;
  final bool hasError;

  /// "Digit :number" accessibility label builder.
  final String Function(int number)? digitLabel;

  @override
  State<CodeInput> createState() => _CodeInputState();
}

class _CodeInputState extends State<CodeInput> {
  final _focus = FocusNode();
  String _completed = '';

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
    _focus.addListener(_changed);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    _focus.dispose();
    super.dispose();
  }

  void _changed() {
    if (!mounted) {
      return;
    }

    setState(() {});
    final text = widget.controller.text;

    // Once per entered code (focus changes also notify).
    if (text.length == widget.length && text != _completed) {
      _completed = text;
      widget.onCompleted?.call(text);
    } else if (text.length < widget.length) {
      _completed = '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final value = widget.controller.text;

    return GestureDetector(
      onTap: () => _focus.requestFocus(),
      child: Stack(
        children: [
          // The real input: invisible but focusable (keyboard, paste, autofill).
          Positioned.fill(
            child: Opacity(
              opacity: 0,
              child: TextField(
                key: const ValueKey('code-input'),
                controller: widget.controller,
                focusNode: _focus,
                keyboardType: TextInputType.number,
                autofillHints: const [AutofillHints.oneTimeCode],
                maxLength: widget.length,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                showCursor: false,
                decoration: const InputDecoration(counterText: '', border: InputBorder.none),
              ),
            ),
          ),
          IgnorePointer(
            child: Directionality(
              // Codes read left to right in every language.
              textDirection: TextDirection.ltr,
              child: Row(
                children: [
                  for (var i = 0; i < widget.length; i++) ...[
                    if (i > 0) const SizedBox(width: 8),
                    Semantics(
                      label: widget.digitLabel?.call(i + 1),
                      value: i < value.length ? value[i] : '',
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 150),
                        width: 48,
                        height: 56,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: colors.surface,
                          borderRadius: BorderRadius.circular(AppRadius.input),
                          border: Border.all(
                            color: widget.hasError
                                ? colors.danger
                                : (_focus.hasFocus && i == value.length.clamp(0, widget.length - 1))
                                    ? colors.primary
                                    : colors.border,
                            width: _focus.hasFocus && i == value.length.clamp(0, widget.length - 1) ? 1.5 : 1,
                          ),
                        ),
                        child: Text(
                          i < value.length ? value[i] : '',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// White bottom bar with a top border holding the main action (onboarding, reset).
class BottomActionBar extends StatelessWidget {
  const BottomActionBar({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Container(
      decoration: BoxDecoration(
        color: colors.surface,
        border: Border(top: BorderSide(color: colors.border)),
      ),
      padding: EdgeInsets.fromLTRB(16, 12, 16, 12 + MediaQuery.paddingOf(context).bottom),
      child: child,
    );
  }
}

/// Snack bar with the server message (already translated) or a fallback.
void showAppMessage(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
