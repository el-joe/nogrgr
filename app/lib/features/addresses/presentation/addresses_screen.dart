import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../../core/widgets/phone_field.dart';
import '../../../core/widgets/select_field.dart';
import '../../auth/presentation/auth_form.dart';
import '../data/address_repository.dart';

/// Addresses (APP-028): list with default badge, set default, edit, delete (confirm), add.
class AddressesScreen extends ConsumerWidget {
  const AddressesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final book = ref.watch(addressBookProvider);

    Future<void> act(Future<void> Function() action) async {
      try {
        await action();
        ref.invalidate(addressBookProvider);
      } on ApiException catch (error) {
        if (context.mounted) {
          showAppMessage(context, apiErrorMessage(texts, error));
        }
      }
    }

    final body = switch (book) {
      AsyncData(:final value) when value.addresses.isEmpty => AsyncStateView.empty(
          icon: 'map',
          title: texts.t('app.addresses.empty_title'),
          body: texts.t('app.addresses.empty_body'),
        ),
      AsyncData(:final value) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(addressBookProvider),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: value.addresses.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final address = value.addresses[index];

              return AddressCard(
                address: address,
                onEdit: () => context.push(Routes.addressEdit, extra: address),
                onDefault: address.isDefault ? null : () => act(() => ref.read(addressRepositoryProvider).makeDefault(address.id)),
                onDelete: () async {
                  final ok = await confirmDialog(
                    context,
                    title: texts.t('account.delete_address_title'),
                    body: texts.t('account.delete_address_message', {'address': address.oneLine}),
                    confirm: texts.t('app.common.delete'),
                    cancel: texts.t('account.cancel'),
                  );

                  if (ok) {
                    await act(() => ref.read(addressRepositoryProvider).delete(address.id));
                  }
                },
              );
            },
          ),
        ),
      AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(addressBookProvider)),
      _ => const ListSkeleton(height: 110, count: 3),
    };

    return PageScaffold(
      title: texts.t('app.addresses.title'),
      body: body,
      bottom: AppButton(key: const ValueKey('address-add'), label: texts.t('account.add_address'), onPressed: () => context.push(Routes.addressEdit)),
    );
  }
}

class AddressCard extends ConsumerWidget {
  const AddressCard({super.key, required this.address, this.onEdit, this.onDelete, this.onDefault, this.selected, this.onSelect});

  final Address address;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;
  final VoidCallback? onDefault;
  final bool? selected;
  final VoidCallback? onSelect;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final text = Theme.of(context).textTheme;
    final colors = context.colors;

    return SurfaceCard(
      onTap: onSelect,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (selected != null) ...[
            Icon(selected! ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: selected! ? colors.primary : colors.muted),
            const SizedBox(width: 10),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(child: Text(address.label ?? address.fullName, style: text.bodyLarge?.copyWith(fontWeight: FontWeight.w600))),
                    if (address.isDefault) ...[const SizedBox(width: 8), StatusPill(label: texts.t('app.addresses.default'), tone: StateTone.primary)],
                  ],
                ),
                const SizedBox(height: 4),
                Text(address.fullName, style: text.bodyMedium),
                Directionality(textDirection: TextDirection.ltr, child: Text(address.phone, style: text.bodyMedium)),
                Text(address.oneLine, style: text.bodyMedium),
                if (onEdit != null || onDefault != null || onDelete != null)
                  Wrap(
                    spacing: 4,
                    children: [
                      if (onEdit != null) TextButton(onPressed: onEdit, child: Text(texts.t('account.edit'))),
                      if (onDefault != null) TextButton(onPressed: onDefault, child: Text(texts.t('app.addresses.make_default'))),
                      if (onDelete != null) TextButton(onPressed: onDelete, child: Text(texts.t('app.common.delete'), style: TextStyle(color: colors.danger))),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Address add / edit (APP-028a): name, label, intl phone (shopping country pre-selected),
/// e-mail, country, city, state, street, default switch; 422 errors inline.
class AddressEditScreen extends ConsumerStatefulWidget {
  const AddressEditScreen({super.key, this.address});

  final Address? address;

  @override
  ConsumerState<AddressEditScreen> createState() => _AddressEditScreenState();
}

class _AddressEditScreenState extends ConsumerState<AddressEditScreen> {
  late final _name = TextEditingController(text: widget.address?.fullName);
  late final _label = TextEditingController(text: widget.address?.label);
  late final _phone = TextEditingController(text: widget.address?.phone);
  late final _email = TextEditingController(text: widget.address?.email);
  late final _line = TextEditingController(text: widget.address?.addressLine);
  late final _city = TextEditingController(text: widget.address?.city);
  late final _state = TextEditingController(text: widget.address?.state);
  late String? _phoneCountry = widget.address?.phoneCountry;
  late int? _countryId = widget.address?.countryId;
  late bool _default = widget.address?.isDefault ?? false;
  Map<String, String?> _errors = const {};
  String? _formError;
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_name, _label, _phone, _email, _line, _city, _state]) {
      c.dispose();
    }

    super.dispose();
  }

  Future<void> _save(AddressBook book) async {
    final texts = ref.read(textsProvider);
    final required = {'full_name': _name, 'phone': _phone, 'address_line_1': _line, 'city': _city};
    final errors = <String, String?>{
      for (final e in required.entries) e.key: e.value.text.trim().isEmpty ? texts.t('app.validation.required') : null,
      'country_id': _countryId == null ? texts.t('app.validation.required') : null,
    };
    setState(() {
      _errors = errors;
      _formError = null;
    });

    if (errors.values.any((e) => e != null)) {
      return;
    }

    final phoneCountry = IntlPhoneField.pick(_phoneCountries(book), _phoneCountry ?? ref.read(preferencesProvider).country)?.iso2;
    setState(() => _busy = true);

    try {
      await ref.read(addressRepositoryProvider).save({
        'full_name': _name.text.trim(),
        'label': _label.text.trim().isEmpty ? null : _label.text.trim(),
        'phone': _phone.text.trim(),
        'phone_country': phoneCountry,
        'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
        'address_line_1': _line.text.trim(),
        'city': _city.text.trim(),
        'state': _state.text.trim().isEmpty ? null : _state.text.trim(),
        'country_id': _countryId,
        'is_default': _default,
      }, id: widget.address?.id);
      ref.invalidate(addressBookProvider);

      if (mounted) {
        context.pop(true);
      }
    } on ApiException catch (error) {
      setState(() {
        _errors = {
          for (final f in ['full_name', 'label', 'email', 'address_line_1', 'city', 'state', 'country_id']) f: error.fieldError(f),
          'phone': error.fieldError('phone') ?? error.fieldError('phone_country'),
        };
        _formError = error.errors.isEmpty ? apiErrorMessage(texts, error) : texts.t('account.fix_fields');
      });
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  List<PhoneCountry> _phoneCountries(AddressBook book) => [for (final c in book.countries) (iso2: c.iso2, name: c.name, flag: c.flag, phoneCode: c.phoneCode)];

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final book = ref.watch(addressBookProvider);
    final value = book.value;

    if (value == null) {
      return PageScaffold(
        title: texts.t(widget.address == null ? 'account.add_address' : 'app.addresses.edit'),
        body: book.hasError ? AsyncStateView.error(error: book.error!, onRetry: () => ref.invalidate(addressBookProvider)) : const ListSkeleton(height: 60),
      );
    }

    final prefs = ref.watch(preferencesProvider);
    _countryId ??= value.countries.where((c) => c.iso2 == prefs.country).map((c) => c.id).firstOrNull ?? (value.countries.isEmpty ? null : value.countries.first.id);

    return PageScaffold(
      title: texts.t(widget.address == null ? 'account.add_address' : 'app.addresses.edit'),
      bottom: AppButton(key: const ValueKey('address-save'), label: texts.t('app.common.save'), loading: _busy, onPressed: () => _save(value)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          if (_formError != null) ...[FormBanner(message: _formError!), const SizedBox(height: 12)],
          AppTextField(fieldKey: const ValueKey('address-name'), label: texts.t('app.auth.name'), controller: _name, error: _errors['full_name'], autofillHints: const [AutofillHints.name]),
          AppTextField(label: texts.t('app.addresses.label'), controller: _label, error: _errors['label']),
          IntlPhoneField(
            fieldKey: const ValueKey('address-phone'),
            label: texts.t('app.auth.phone'),
            countryLabel: texts.t('app.auth.phone_country'),
            controller: _phone,
            countries: _phoneCountries(value),
            iso2: _phoneCountry ?? prefs.country,
            onCountry: (iso2) => setState(() => _phoneCountry = iso2),
            error: _errors['phone'],
          ),
          AppTextField(label: texts.t('app.auth.email'), controller: _email, error: _errors['email'], keyboardType: TextInputType.emailAddress),
          AppSelectField<int>(
            label: texts.t('app.addresses.country'),
            value: _countryId,
            options: [for (final c in value.countries) SelectOption(value: c.id, label: c.name, leading: c.flag)],
            onChanged: (id) => setState(() => _countryId = id),
            helper: _errors['country_id'],
          ),
          const SizedBox(height: 12),
          AppTextField(fieldKey: const ValueKey('address-city'), label: texts.t('app.addresses.city'), controller: _city, error: _errors['city']),
          AppTextField(label: texts.t('app.addresses.state'), controller: _state, error: _errors['state']),
          AppTextField(fieldKey: const ValueKey('address-line'), label: texts.t('app.addresses.street'), controller: _line, error: _errors['address_line_1'], textInputAction: TextInputAction.done),
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            title: Text(texts.t('app.addresses.set_default')),
            value: _default,
            onChanged: (v) => setState(() => _default = v),
          ),
        ],
      ),
    );
  }
}
