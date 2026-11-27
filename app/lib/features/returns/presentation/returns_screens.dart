import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/url_opener.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../../core/widgets/paged_list.dart';
import '../../../core/widgets/select_field.dart';
import '../../auth/presentation/auth_form.dart';
import '../../catalog/presentation/image_search_screen.dart';
import '../../catalog/presentation/product_screen.dart';
import '../data/returns_repository.dart';

StateTone returnTone(String status) => switch (status) {
      'rejected' || 'cancelled' || 'withdrawn' => StateTone.danger,
      'refunded' || 'exchanged' || 'closed' => StateTone.primary,
      'requested' || 'pending' => StateTone.warning,
      _ => StateTone.info,
    };

/// Returns list (APP-017).
class ReturnsScreen extends ConsumerWidget {
  const ReturnsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final locale = ref.watch(localeCodeProvider);
    final returns = ref.watch(returnsProvider);
    final text = Theme.of(context).textTheme;

    return PageScaffold(
      title: texts.t('returns.title'),
      body: switch (returns) {
        AsyncData(:final value) when value.items.isEmpty => AsyncStateView.empty(
            icon: 'return',
            title: texts.t('returns.empty_title'),
            body: texts.t('returns.empty_text'),
            actionLabel: texts.t('returns.view_orders'),
            onRetry: () => context.push(Routes.orders),
          ),
        AsyncData(:final value) => PagedScroll(
            onRefresh: () async => ref.invalidate(returnsProvider),
            onLoadMore: () => ref.read(returnsProvider.notifier).loadMore(),
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              itemCount: value.items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (_, i) {
                final row = value.items[i];

                return SurfaceCard(
                  key: ValueKey('return-${row.id}'),
                  onTap: () => context.push(Routes.returnDetail(row.id)),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        Expanded(child: Text(row.reference, style: text.bodyLarge?.copyWith(fontWeight: FontWeight.w600))),
                        StatusPill(label: row.statusLabel, tone: returnTone(row.status)),
                      ]),
                      const SizedBox(height: 4),
                      Text('${row.productName} × ${row.quantity}', style: text.bodyLarge),
                      Text(texts.t('returns.reason_date', {'reason': row.reasonName, 'date': formatDate(row.createdAt, locale)}), style: text.bodyMedium),
                      if (!row.refundAmount.isZero) Text(texts.t('returns.refund', {'amount': row.refundAmount.label}), style: text.bodyMedium),
                      if (row.lastMessage != null) Text(texts.t('returns.last_note', {'note': row.lastMessage}), style: text.bodyMedium, maxLines: 2, overflow: TextOverflow.ellipsis),
                    ],
                  ),
                );
              },
            ),
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(returnsProvider)),
        _ => const ListSkeleton(height: 110),
      },
    );
  }
}

/// Return detail (APP-018): summary, steps, conversation + reply, evidence, withdraw.
class ReturnDetailScreen extends ConsumerStatefulWidget {
  const ReturnDetailScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<ReturnDetailScreen> createState() => _ReturnDetailScreenState();
}

class _ReturnDetailScreenState extends ConsumerState<ReturnDetailScreen> {
  final _reply = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final texts = ref.read(textsProvider);

    if (_reply.text.trim().length < 10) {
      setState(() => _error = texts.t('returns.reply_placeholder'));

      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref.read(returnsRepositoryProvider).reply(widget.id, _reply.text.trim());
      _reply.clear();
      ref.invalidate(returnProvider(widget.id));
    } on ApiException catch (error) {
      setState(() => _error = error.fieldError('message') ?? apiErrorMessage(texts, error));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _withdraw(ReturnRow row) async {
    final texts = ref.read(textsProvider);
    final ok = await confirmDialog(
      context,
      title: texts.t('returns.withdraw_title'),
      body: texts.t('returns.withdraw_text', {'reference': row.reference}),
      confirm: texts.t('returns.withdraw'),
      cancel: texts.t('returns.keep_request'),
    );

    if (!ok) {
      return;
    }

    try {
      await ref.read(returnsRepositoryProvider).withdraw(row.id);
      ref.invalidate(returnProvider(widget.id));
      ref.invalidate(returnsProvider);
    } on ApiException catch (error) {
      if (mounted) {
        showAppMessage(context, apiErrorMessage(texts, error));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final locale = ref.watch(localeCodeProvider);
    final value = ref.watch(returnProvider(widget.id));
    final text = Theme.of(context).textTheme;
    final colors = context.colors;
    const steps = ['requested', 'in_review', 'approved', 'received', 'refunded'];

    return PageScaffold(
      title: value.value?.reference ?? texts.t('returns.summary'),
      body: switch (value) {
        AsyncData(value: final row) => RefreshIndicator(
            onRefresh: () async => ref.invalidate(returnProvider(widget.id)),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                Row(
                  children: [
                    for (var i = 0; i < steps.length; i++)
                      Expanded(
                        child: Column(
                          children: [
                            Container(height: 4, margin: const EdgeInsets.symmetric(horizontal: 2), color: i < row.step ? colors.primary : colors.border),
                            const SizedBox(height: 4),
                            Text(texts.t('returns.steps.${steps[i]}'), style: text.labelSmall?.copyWith(fontWeight: FontWeight.w400), textAlign: TextAlign.center),
                          ],
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                SurfaceCard(
                  child: Column(
                    children: [
                      SummaryRow(label: texts.t('returns.order'), value: row.orderNumber),
                      SummaryRow(label: texts.t('returns.item'), value: '${row.productName} × ${row.quantity}'),
                      SummaryRow(label: texts.t('returns.reason'), value: row.reasonName),
                      SummaryRow(label: texts.t('returns.resolution'), value: row.typeLabel),
                      SummaryRow(label: texts.t('orders.status'), value: row.statusLabel),
                      if (!row.refundAmount.isZero) SummaryRow(label: texts.t('returns.refund_amount'), value: row.refundAmount.label, strong: true),
                      if (row.exchangeTracking != null) SummaryRow(label: texts.t('returns.exchange_tracking'), value: row.exchangeTracking!),
                    ],
                  ),
                ),
                if (row.description != null) ...[const SizedBox(height: 12), Text(row.description!, style: text.bodyLarge)],
                if (row.media.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    children: [
                      for (final m in row.media)
                        ActionChip(avatar: const Icon(Icons.attach_file, size: 16), label: Text(m.name), onPressed: () => ref.read(urlOpenerProvider)(m.url)),
                    ],
                  ),
                ],
                const SizedBox(height: 16),
                Text(texts.t('returns.conversation'), style: text.titleLarge),
                const SizedBox(height: 8),
                if (row.timeline.isEmpty) Text(texts.t('returns.no_messages'), style: text.bodyMedium),
                for (final event in row.timeline)
                  Align(
                    alignment: event.author == 'customer' ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(10),
                      constraints: const BoxConstraints(maxWidth: 300),
                      decoration: BoxDecoration(
                        color: event.author == 'customer' ? colors.primarySubtle : colors.surface,
                        border: Border.all(color: colors.border),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(texts.t(event.author == 'customer' ? 'returns.you' : 'returns.store'), style: text.labelSmall),
                          if (event.status != null) Text(texts.t('returns.status_changed', {'status': event.status}), style: text.bodyMedium),
                          if (event.message != null) Text(event.message!, style: text.bodyLarge),
                          Text(formatDate(event.at, locale), style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400)),
                        ],
                      ),
                    ),
                  ),
                if (row.canReply) ...[
                  AppTextField(fieldKey: const ValueKey('return-reply'), label: texts.t('returns.reply'), controller: _reply, error: _error, keyboardType: TextInputType.multiline),
                  AppButton(key: const ValueKey('return-send'), label: texts.t('returns.send_reply'), loading: _busy, onPressed: _send),
                ] else
                  Text(texts.t('returns.closed_note'), style: text.bodyMedium),
                if (row.canWithdraw) ...[
                  const SizedBox(height: 16),
                  Text(texts.t('returns.withdraw_hint'), style: text.bodyMedium),
                  TextButton(
                    key: const ValueKey('return-withdraw'),
                    onPressed: () => _withdraw(row),
                    child: Text(texts.t('returns.withdraw'), style: TextStyle(color: colors.danger)),
                  ),
                ],
              ],
            ),
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(returnProvider(widget.id))),
        _ => const ListSkeleton(),
      },
    );
  }
}

/// Request a return (APP-016): items + quantities, reason, refund / exchange,
/// description, photos / video (camera or gallery), policy window.
class ReturnRequestScreen extends ConsumerStatefulWidget {
  const ReturnRequestScreen({super.key, required this.orderUuid});

  final String orderUuid;

  @override
  ConsumerState<ReturnRequestScreen> createState() => _ReturnRequestScreenState();
}

class _ReturnRequestScreenState extends ConsumerState<ReturnRequestScreen> {
  final Map<int, int> _items = {};
  String? _reason;
  String _type = 'return';
  final _description = TextEditingController();
  final List<String> _photos = [];
  String? _video;
  Map<String, String?> _errors = const {};
  String? _formError;
  bool _busy = false;

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  Future<void> _addPhoto() async {
    final texts = ref.read(textsProvider);

    if (_photos.length >= 5) {
      showAppMessage(context, texts.t('returns.too_many_photos', {'count': 5}));

      return;
    }

    final path = await ref.read(imagePickerProvider)(ImageSource.gallery);

    if (path != null) {
      setState(() => _photos.add(path));
    }
  }

  Future<void> _addVideo() async {
    final file = await ImagePicker().pickVideo(source: ImageSource.gallery, maxDuration: const Duration(minutes: 2));

    if (file != null) {
      setState(() => _video = file.path);
    }
  }

  Future<void> _submit(ReturnOptions options) async {
    final texts = ref.read(textsProvider);
    final errors = <String, String?>{
      'items': _items.isEmpty ? texts.t('returns.need_item') : null,
      'reason_code': _reason == null ? texts.t('returns.choose_reason') : null,
      'description': _description.text.trim().length < 10 ? texts.t('returns.description_hint') : null,
    };
    final reason = options.reasons.where((r) => r.code == _reason).firstOrNull;

    if (reason != null && reason.requiresVideo && _video == null) {
      errors['video'] = texts.t('returns.video_required', {'reasons': reason.name});
    }

    setState(() {
      _errors = errors;
      _formError = null;
    });

    if (errors.values.any((e) => e != null)) {
      return;
    }

    setState(() => _busy = true);

    try {
      final message = await ref.read(returnsRepositoryProvider).create(
            widget.orderUuid,
            reasonCode: _reason!,
            description: _description.text.trim(),
            type: _type,
            items: _items,
            photos: _photos,
            video: _video,
          );
      ref.invalidate(returnsProvider);

      if (mounted) {
        showAppMessage(context, message);
        context.pushReplacement(Routes.returns);
      }
    } on ApiException catch (error) {
      setState(() {
        _errors = {
          'reason_code': error.fieldError('reason_code'),
          'description': error.fieldError('description'),
          'items': error.fieldError('items'),
          'video': error.fieldError('video'),
          'photos': error.fieldError('photos'),
        };
        _formError = apiErrorMessage(texts, error);
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
    final locale = ref.watch(localeCodeProvider);
    final options = ref.watch(returnOptionsProvider(widget.orderUuid));
    final text = Theme.of(context).textTheme;
    final colors = context.colors;

    return PageScaffold(
      title: texts.t('returns.request_title'),
      bottom: options.value?.canReturn == true
          ? AppButton(key: const ValueKey('return-submit'), label: texts.t('returns.submit'), loading: _busy, onPressed: () => _submit(options.value!))
          : null,
      body: switch (options) {
        AsyncData(:final value) when !value.canReturn => AsyncStateView.empty(
            icon: 'return',
            title: texts.t('returns.blocked_title'),
            body: value.blockedMessage,
            actionLabel: texts.t('returns.back_to_order'),
            onRetry: () => context.pop(),
          ),
        AsyncData(:final value) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (value.windowDays != null && value.deadline != null)
                Text(texts.t('returns.window', {'days': value.windowDays, 'date': formatDate(value.deadline, locale)}), style: text.bodyMedium),
              if (value.payerLabel != null) Text(texts.t('returns.shipping_payer', {'payer': value.payerLabel}), style: text.bodyMedium),
              if (_formError != null) ...[const SizedBox(height: 8), FormBanner(message: _formError!)],
              const SizedBox(height: 12),
              for (final line in value.lines)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: SurfaceCard(
                    onTap: line.returnable && line.available > 0
                        ? () => setState(() => _items.containsKey(line.orderItemId) ? _items.remove(line.orderItemId) : _items[line.orderItemId] = 1)
                        : null,
                    child: Row(
                      children: [
                        Checkbox(
                          key: ValueKey('return-line-${line.orderItemId}'),
                          value: _items.containsKey(line.orderItemId),
                          onChanged: line.returnable && line.available > 0
                              ? (v) => setState(() => v == true ? _items[line.orderItemId] = 1 : _items.remove(line.orderItemId))
                              : null,
                        ),
                        SizedBox(width: 48, height: 48, child: NetImage(line.image, radius: 6)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(line.name, style: text.bodyLarge, maxLines: 2, overflow: TextOverflow.ellipsis),
                              Text(line.returnable ? texts.t('returns.left', {'count': line.available}) : texts.t('returns.not_returnable'), style: text.bodyMedium),
                            ],
                          ),
                        ),
                        if (_items.containsKey(line.orderItemId) && line.available > 1)
                          QuantityStepper(value: _items[line.orderItemId]!, max: line.available, onChanged: (v) => setState(() => _items[line.orderItemId] = v)),
                      ],
                    ),
                  ),
                ),
              if (_errors['items'] != null) Text(_errors['items']!, style: TextStyle(color: colors.danger, fontSize: 11)),
              const SizedBox(height: 8),
              AppSelectField<String>(
                label: texts.t('returns.reason'),
                value: _reason,
                options: [for (final r in value.reasons) SelectOption(value: r.code, label: r.name)],
                onChanged: (code) => setState(() => _reason = code),
                helper: _errors['reason_code'],
              ),
              const SizedBox(height: 12),
              if (value.allowExchanges)
                Wrap(spacing: 8, children: [
                  AppChip(label: texts.t('returns.type_return'), selected: _type == 'return', onTap: () => setState(() => _type = 'return')),
                  AppChip(label: texts.t('returns.type_exchange'), selected: _type == 'exchange', onTap: () => setState(() => _type = 'exchange')),
                ]),
              const SizedBox(height: 12),
              AppTextField(
                fieldKey: const ValueKey('return-description'),
                label: texts.t('returns.description'),
                controller: _description,
                error: _errors['description'],
                keyboardType: TextInputType.multiline,
              ),
              Text(texts.t('returns.upload_limits'), style: text.bodyMedium),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final photo in _photos)
                    Stack(
                      children: [
                        ClipRRect(borderRadius: BorderRadius.circular(6), child: Image.file(File(photo), width: 64, height: 64, fit: BoxFit.contain)),
                        PositionedDirectional(
                          end: 0,
                          top: 0,
                          child: InkWell(
                            onTap: () => setState(() => _photos.remove(photo)),
                            child: Icon(Icons.cancel, size: 18, color: colors.danger, semanticLabel: texts.t('returns.remove_file', {'name': ''})),
                          ),
                        ),
                      ],
                    ),
                  ActionChip(avatar: const Icon(Icons.photo_outlined, size: 18), label: Text(texts.t('returns.add_photos')), onPressed: _addPhoto),
                  ActionChip(
                    avatar: const Icon(Icons.videocam_outlined, size: 18),
                    label: Text(_video == null ? texts.t('returns.add_video') : _video!.split('/').last),
                    onPressed: _addVideo,
                  ),
                ],
              ),
              if (_errors['video'] != null) Text(_errors['video']!, style: TextStyle(color: colors.danger, fontSize: 11)),
              if (value.policyText != null) ...[const SizedBox(height: 16), Text(value.policyText!, style: text.bodyMedium)],
            ],
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(returnOptionsProvider(widget.orderUuid))),
        _ => const ListSkeleton(),
      },
    );
  }
}
