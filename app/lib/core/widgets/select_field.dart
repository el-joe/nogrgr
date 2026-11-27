import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class SelectOption<T> {
  const SelectOption({required this.value, required this.label, this.leading});

  final T value;
  final String label;
  final String? leading;
}

/// "App/Input" used as a picker: label, 50-high box with the value and a trailing chevron, opens a bottom sheet
/// list (APP-003 / settings).
class AppSelectField<T> extends StatelessWidget {
  const AppSelectField({
    super.key,
    required this.label,
    required this.options,
    required this.value,
    required this.onChanged,
    this.helper,
  });

  final String label;
  final List<SelectOption<T>> options;
  final T? value;
  final ValueChanged<T> onChanged;
  final String? helper;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    SelectOption<T>? selected;

    for (final option in options) {
      if (option.value == value) {
        selected = option;
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(label, style: text.labelMedium),
        const SizedBox(height: 6),
        Semantics(
          button: true,
          label: label,
          value: selected?.label,
          excludeSemantics: true,
          child: Material(
            color: colors.surface,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.input),
              side: BorderSide(color: colors.border),
            ),
            child: InkWell(
              borderRadius: BorderRadius.circular(AppRadius.input),
              onTap: options.isEmpty ? null : () => _open(context),
              child: Container(
                height: 50,
                padding: const EdgeInsetsDirectional.only(start: 14, end: 10),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        [
                          if (selected?.leading != null && selected!.leading!.isNotEmpty) selected.leading!,
                          selected?.label ?? '',
                        ].join('  '),
                        key: const ValueKey('select-field-value'),
                        style: text.bodyLarge,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(Icons.keyboard_arrow_down_rounded, key: const ValueKey('select-field-chevron'), size: 22, color: colors.muted),
                  ],
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 6),
        Text(helper ?? ' ', style: text.labelSmall?.copyWith(fontWeight: FontWeight.w400, color: colors.muted)),
      ],
    );
  }

  Future<void> _open(BuildContext context) async {
    final colors = context.colors;
    final picked = await showModalBottomSheet<T>(
      context: context,
      backgroundColor: colors.surface,
      showDragHandle: true,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (sheetContext) => ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.7),
        child: SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: Text(label, style: Theme.of(sheetContext).textTheme.titleLarge),
              ),
              Flexible(
                child: ListView(
                  shrinkWrap: true,
                  children: [
                    for (final option in options)
                      ListTile(
                        leading: option.leading == null || option.leading!.isEmpty ? null : Text(option.leading!, style: const TextStyle(fontSize: 20)),
                        title: Text(option.label),
                        trailing: option.value == value ? Icon(Icons.check, color: colors.primary) : null,
                        onTap: () => Navigator.of(sheetContext).pop(option.value),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );

    if (picked != null) {
      onChanged(picked);
    }
  }
}

/// "App/Chip": pill, ink fill when selected, outlined otherwise.
class AppChip extends StatelessWidget {
  const AppChip({super.key, required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected ? colors.ink : colors.surface,
        shape: StadiumBorder(side: BorderSide(color: selected ? colors.ink : colors.border)),
        child: InkWell(
          customBorder: const StadiumBorder(),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            child: Text(
              label,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(color: selected ? colors.bg : colors.ink),
            ),
          ),
        ),
      ),
    );
  }
}
