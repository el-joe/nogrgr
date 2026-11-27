import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/router.dart';
import '../../../core/i18n/app_texts.dart';
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
import '../data/support_repository.dart';

/// Picks files (documents / images) of the allowed extensions; overridable in tests.
final filePickerProvider = Provider<Future<List<LocalFile>> Function(List<String> extensions)>((ref) {
  return (extensions) async {
    final picked = await FilePicker.pickFiles(type: extensions.isEmpty ? FileType.any : FileType.custom, allowedExtensions: extensions.isEmpty ? null : extensions);

    return [
      for (final file in picked)
        if (file.path != null) LocalFile(path: file.path!, name: file.name, size: await File(file.path!).length()),
    ];
  };
});

/// Status pill tone of a ticket (`tone` from the API).
StateTone ticketTone(TicketStatus status) => switch (status.tone) {
      'warning' => StateTone.warning,
      'brand' || 'success' => StateTone.primary,
      'danger' => StateTone.danger,
      _ => StateTone.info,
    };

/// Support tickets (Account › Support): Active / Closed tabs, paged rows with the
/// unread mark, "New ticket".
class TicketsScreen extends ConsumerStatefulWidget {
  const TicketsScreen({super.key});

  @override
  ConsumerState<TicketsScreen> createState() => _TicketsScreenState();
}

class _TicketsScreenState extends ConsumerState<TicketsScreen> {
  String _group = 'active';

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final provider = ticketsProvider(_group);
    final tickets = ref.watch(provider);

    final body = switch (tickets) {
      AsyncData(:final value) when value.items.isEmpty => AsyncStateView.empty(
          icon: 'lifebuoy',
          title: _group == 'active' ? texts.t('support.tickets.empty_title') : texts.t('support.tickets.filter_empty'),
          body: _group == 'active' ? texts.t('support.tickets.empty_text') : null,
          actionLabel: _group == 'active' ? texts.t('support.tickets.new') : null,
          onRetry: _group == 'active' ? () => context.push(Routes.newTicket) : null,
        ),
      AsyncData(:final value) => PagedScroll(
          onRefresh: () async => ref.invalidate(provider),
          onLoadMore: () => ref.read(provider.notifier).loadMore(),
          child: ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            itemCount: value.items.length + (value.loadingMore ? 1 : 0),
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (_, index) => index >= value.items.length ? const LoadingMore() : TicketTile(ticket: value.items[index]),
          ),
        ),
      AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(provider)),
      _ => const ListSkeleton(height: 96, count: 4),
    };

    return PageScaffold(
      title: texts.t('support.tickets.title'),
      trailing: IconButton(
        key: const ValueKey('tickets-new'),
        tooltip: texts.t('support.tickets.new'),
        icon: Icon(Icons.add, color: context.colors.ink),
        onPressed: () => context.push(Routes.newTicket),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Text(texts.t('support.tickets.intro'), style: Theme.of(context).textTheme.bodyMedium),
          ),
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              children: [
                for (final group in const ['active', 'closed']) ...[
                  AppChip(
                    key: ValueKey('tickets-tab-$group'),
                    label: texts.t('support.tickets.tabs.$group'),
                    selected: _group == group,
                    onTap: () => setState(() => _group = group),
                  ),
                  const SizedBox(width: 8),
                ],
              ],
            ),
          ),
          Expanded(child: body),
        ],
      ),
    );
  }
}

class TicketTile extends ConsumerWidget {
  const TicketTile({super.key, required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final locale = ref.watch(localeCodeProvider);
    final text = Theme.of(context).textTheme;
    final colors = context.colors;

    return SurfaceCard(
      key: ValueKey('ticket-${ticket.id}'),
      onTap: () => context.push(Routes.ticket(ticket.id)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  ticket.subject,
                  style: text.bodyLarge?.copyWith(fontWeight: ticket.unread ? FontWeight.w700 : FontWeight.w600),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 8),
              StatusPill(label: ticket.status.label, tone: ticketTone(ticket.status)),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            [ticket.reference, ticket.topic.label, if (ticket.order != null) texts.t('support.tickets.order', {'number': ticket.order!.number})].join(' · '),
            style: text.bodyMedium,
          ),
          if (ticket.excerpt != null) ...[
            const SizedBox(height: 6),
            Text(ticket.excerpt!, style: text.bodyMedium?.copyWith(color: colors.ink), maxLines: 2, overflow: TextOverflow.ellipsis),
          ],
          const SizedBox(height: 6),
          Row(
            children: [
              Expanded(
                child: Text(
                  texts.t('support.tickets.updated', {'date': formatDate(ticket.lastReplyAt ?? ticket.createdAt, locale)}),
                  style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400),
                ),
              ),
              if (ticket.unread)
                Row(
                  children: [
                    Container(width: 8, height: 8, decoration: BoxDecoration(color: colors.primary, shape: BoxShape.circle)),
                    const SizedBox(width: 6),
                    Text(texts.t('support.tickets.unread'), style: text.labelSmall?.copyWith(color: colors.primary)),
                  ],
                ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Files chosen for a new ticket / reply: add from files or camera, remove, limits.
class AttachmentPicker extends ConsumerWidget {
  const AttachmentPicker({super.key, required this.files, required this.onChanged, required this.maxFiles, required this.maxFileKb, this.fileTypes = const [], this.error});

  final List<LocalFile> files;
  final ValueChanged<List<LocalFile>> onChanged;
  final int maxFiles;
  final int maxFileKb;
  final List<String> fileTypes;
  final String? error;

  Future<void> _add(BuildContext context, WidgetRef ref, List<LocalFile> picked) async {
    final texts = ref.read(textsProvider);
    final accepted = <LocalFile>[];
    String? problem;

    for (final file in picked) {
      if (files.length + accepted.length >= maxFiles) {
        problem = texts.t('app.support.files_limit', {'count': maxFiles});
        break;
      }

      if (file.size > maxFileKb * 1024) {
        problem = texts.t('app.support.file_too_large', {'name': file.name, 'size': maxFileKb >= 1024 ? '${(maxFileKb / 1024).round()} MB' : '$maxFileKb KB'});
        continue;
      }

      accepted.add(file);
    }

    if (accepted.isNotEmpty) {
      onChanged([...files, ...accepted]);
    }

    if (problem != null && context.mounted) {
      showAppMessage(context, problem);
    }
  }

  Future<void> _fromFiles(BuildContext context, WidgetRef ref) async {
    final picked = await ref.read(filePickerProvider)(fileTypes);

    if (context.mounted) {
      await _add(context, ref, picked);
    }
  }

  Future<void> _fromCamera(BuildContext context, WidgetRef ref) async {
    final path = await ref.read(imagePickerProvider)(ImageSource.camera);

    if (path == null || !context.mounted) {
      return;
    }

    final file = File(path);
    final size = await file.length();

    if (context.mounted) {
      await _add(context, ref, [LocalFile(path: path, name: file.uri.pathSegments.last, size: size)]);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final full = files.length >= maxFiles;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(texts.t('support.tickets.fields.files'), style: text.labelMedium),
        const SizedBox(height: 4),
        Text(texts.t('support.tickets.files_hint'), style: text.bodyMedium),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final file in files)
              InputChip(
                key: ValueKey('attachment-${file.name}'),
                avatar: const Icon(Icons.attach_file, size: 16),
                label: Text(file.name, overflow: TextOverflow.ellipsis),
                deleteButtonTooltipMessage: texts.t('app.support.remove_file', {'name': file.name}),
                onDeleted: () => onChanged([...files]..remove(file)),
              ),
            ActionChip(
              key: const ValueKey('attachment-add'),
              avatar: Icon(Icons.upload_file, size: 16, color: colors.primary),
              label: Text(texts.t('app.support.files_add')),
              onPressed: full ? null : () => _fromFiles(context, ref),
            ),
            ActionChip(
              key: const ValueKey('attachment-camera'),
              avatar: Icon(Icons.photo_camera_outlined, size: 16, color: colors.primary),
              label: Text(texts.t('app.support.camera')),
              onPressed: full ? null : () => _fromCamera(context, ref),
            ),
          ],
        ),
        if (error != null) ...[const SizedBox(height: 6), Text(error!, style: text.labelSmall?.copyWith(color: colors.danger))],
      ],
    );
  }
}

/// First field error of `files` / `files.N` from a 422.
String? filesError(ApiException error) {
  final direct = error.fieldError('files');

  if (direct != null) {
    return direct;
  }

  for (final key in error.errors.keys) {
    if (key.startsWith('files.')) {
      return error.fieldError(key);
    }
  }

  return null;
}

/// Open a ticket: topic, optional own order, subject, message, attachments.
class NewTicketScreen extends ConsumerStatefulWidget {
  const NewTicketScreen({super.key, this.orderUuid});

  /// Pre-selected order (from an order's "Contact the store").
  final String? orderUuid;

  @override
  ConsumerState<NewTicketScreen> createState() => _NewTicketScreenState();
}

class _NewTicketScreenState extends ConsumerState<NewTicketScreen> {
  final _subject = TextEditingController();
  final _body = TextEditingController();
  String? _topic;
  late String _order = widget.orderUuid ?? '';
  List<LocalFile> _files = const [];
  Map<String, String?> _errors = const {};
  String? _formError;
  bool _busy = false;

  @override
  void dispose() {
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  Map<String, String?> _validate(AppTexts texts) {
    final subject = _subject.text.trim();
    final body = _body.text.trim();

    return {
      'topic': _topic == null ? texts.t('app.validation.required') : null,
      'subject': subject.isEmpty ? texts.t('app.validation.required') : (subject.length < 3 ? texts.t('app.support.subject_min') : null),
      'body': body.isEmpty ? texts.t('app.validation.required') : (body.length < 5 ? texts.t('app.support.body_min') : null),
    };
  }

  Future<void> _submit() async {
    final texts = ref.read(textsProvider);
    final errors = _validate(texts);
    setState(() {
      _errors = errors;
      _formError = null;
    });

    if (errors.values.any((e) => e != null)) {
      return;
    }

    setState(() => _busy = true);

    try {
      final result = await ref.read(supportRepositoryProvider).open(
            topic: _topic!,
            subject: _subject.text.trim(),
            body: _body.text.trim(),
            orderUuid: _order.isEmpty ? null : _order,
            files: _files,
          );
      ref.invalidate(ticketsProvider);

      if (!mounted) {
        return;
      }

      if (result.message.isNotEmpty) {
        showAppMessage(context, result.message);
      }

      context.pushReplacement(Routes.ticket(result.ticket.id));
    } on ApiException catch (error) {
      setState(() {
        _errors = {
          'topic': error.fieldError('topic'),
          'subject': error.fieldError('subject'),
          'body': error.fieldError('body'),
          'order': error.fieldError('order_uuid'),
          'files': filesError(error),
        };
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
    final options = ref.watch(ticketOptionsProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;

    return PageScaffold(
      title: texts.t('support.tickets.new_title'),
      bottom: options.hasValue
          ? AppButton(key: const ValueKey('ticket-send'), label: texts.t('support.tickets.send'), loading: _busy, onPressed: _submit)
          : null,
      body: switch (options) {
        AsyncData(value: final opts) => ListView(
            padding: const EdgeInsets.all(16),
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            children: [
              if (_formError != null) ...[FormBanner(message: _formError!), const SizedBox(height: 16)],
              AppSelectField<String>(
                key: const ValueKey('ticket-topic'),
                label: texts.t('support.tickets.fields.topic'),
                options: [for (final topic in opts.topics) SelectOption(value: topic.value, label: topic.label)],
                value: _topic,
                onChanged: (value) => setState(() => _topic = value),
              ),
              if (_errors['topic'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text(_errors['topic']!, style: text.labelSmall?.copyWith(color: colors.danger))),
              if (opts.orders.isNotEmpty) ...[
                const SizedBox(height: 16),
                AppSelectField<String>(
                  key: const ValueKey('ticket-order'),
                  label: texts.t('support.tickets.fields.order'),
                  options: [
                    SelectOption(value: '', label: texts.t('support.tickets.no_order')),
                    for (final order in opts.orders) SelectOption(value: order.uuid, label: texts.t('support.tickets.order', {'number': order.number})),
                  ],
                  value: _order,
                  onChanged: (value) => setState(() => _order = value),
                ),
                if (_errors['order'] != null) Padding(padding: const EdgeInsets.only(top: 6), child: Text(_errors['order']!, style: text.labelSmall?.copyWith(color: colors.danger))),
              ],
              const SizedBox(height: 16),
              AppTextField(
                fieldKey: const ValueKey('ticket-subject'),
                label: texts.t('support.tickets.fields.subject'),
                controller: _subject,
                error: _errors['subject'],
                hint: texts.t('support.tickets.placeholders.subject'),
              ),
              const SizedBox(height: 16),
              AppTextField(
                fieldKey: const ValueKey('ticket-body'),
                label: texts.t('support.tickets.fields.message'),
                controller: _body,
                error: _errors['body'],
                keyboardType: TextInputType.multiline,
                textInputAction: TextInputAction.newline,
                maxLines: null,
                minLines: 4,
                hint: texts.t('support.tickets.placeholders.message'),
              ),
              const SizedBox(height: 16),
              AttachmentPicker(
                files: _files,
                onChanged: (files) => setState(() => _files = files),
                maxFiles: opts.maxFiles,
                maxFileKb: opts.maxFileKb,
                fileTypes: opts.fileTypes,
                error: _errors['files'],
              ),
            ],
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(ticketOptionsProvider)),
        _ => const ListSkeleton(height: 56, count: 5),
      },
    );
  }
}

/// Ticket conversation: details, messages with files (signed links), reply with files,
/// close. A closed ticket shows the "open a new ticket" note instead of the composer.
class TicketScreen extends ConsumerStatefulWidget {
  const TicketScreen({super.key, required this.id});

  final int id;

  @override
  ConsumerState<TicketScreen> createState() => _TicketScreenState();
}

class _TicketScreenState extends ConsumerState<TicketScreen> {
  final _reply = TextEditingController();
  List<LocalFile> _files = const [];
  String? _error;
  String? _filesError;
  bool _busy = false;

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  void _refresh() {
    ref
      ..invalidate(ticketProvider(widget.id))
      ..invalidate(ticketsProvider);
  }

  Future<void> _send() async {
    final texts = ref.read(textsProvider);
    final body = _reply.text.trim();

    if (body.isEmpty && _files.isEmpty) {
      setState(() => _error = texts.t('app.validation.required'));

      return;
    }

    setState(() {
      _busy = true;
      _error = null;
      _filesError = null;
    });

    try {
      final message = await ref.read(supportRepositoryProvider).reply(widget.id, body: body, files: _files);
      _reply.clear();
      setState(() => _files = const []);
      _refresh();

      if (mounted && message.isNotEmpty) {
        showAppMessage(context, message);
      }
    } on ApiException catch (error) {
      setState(() {
        _filesError = filesError(error);
        _error = error.fieldError('body') ?? (_filesError == null ? apiErrorMessage(texts, error) : null);
      });
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _close(Ticket ticket) async {
    final texts = ref.read(textsProvider);
    final confirmed = await confirmDialog(
      context,
      title: texts.t('support.tickets.close_title'),
      body: texts.t('support.tickets.close_text', {'reference': ticket.reference}),
      confirm: texts.t('support.tickets.close'),
      cancel: texts.t('support.tickets.keep_open'),
    );

    if (!confirmed) {
      return;
    }

    try {
      final message = await ref.read(supportRepositoryProvider).close(ticket.id);
      _refresh();

      if (mounted && message.isNotEmpty) {
        showAppMessage(context, message);
      }
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
    final value = ref.watch(ticketProvider(widget.id));
    final text = Theme.of(context).textTheme;
    final colors = context.colors;
    final ticket = value.value;

    return PageScaffold(
      title: ticket?.reference ?? texts.t('support.tickets.title'),
      trailing: ticket != null && ticket.canClose
          ? TextButton(key: const ValueKey('ticket-close'), onPressed: () => _close(ticket), child: Text(texts.t('support.tickets.close'), style: TextStyle(color: colors.danger)))
          : null,
      body: switch (value) {
        AsyncData(value: final row) => RefreshIndicator(
            onRefresh: () async => ref.invalidate(ticketProvider(widget.id)),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                Text(row.subject, style: text.titleLarge),
                const SizedBox(height: 12),
                SurfaceCard(
                  child: Column(
                    children: [
                      SummaryRow(label: texts.t('support.tickets.reference'), value: row.reference),
                      SummaryRow(label: texts.t('support.tickets.fields.topic'), value: row.topic.label),
                      if (row.order != null) SummaryRow(label: texts.t('orders.title'), value: texts.t('support.tickets.order', {'number': row.order!.number})),
                      SummaryRow(label: texts.t('support.tickets.status'), value: row.status.label),
                      SummaryRow(label: texts.t('support.tickets.opened'), value: formatDate(row.createdAt, locale)),
                    ],
                  ),
                ),
                if (row.order != null)
                  Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: TextButton(
                      key: const ValueKey('ticket-order-link'),
                      onPressed: () => context.push(Routes.order(row.order!.uuid)),
                      child: Text(texts.t('support.tickets.order', {'number': row.order!.number})),
                    ),
                  ),
                const SizedBox(height: 16),
                Text(texts.t('support.tickets.conversation'), style: text.titleMedium),
                const SizedBox(height: 8),
                for (final message in row.messages) _MessageBubble(message: message),
                const SizedBox(height: 8),
                if (row.canReply) ...[
                  AppTextField(
                    fieldKey: const ValueKey('ticket-reply'),
                    label: texts.t('support.tickets.reply'),
                    controller: _reply,
                    error: _error,
                    keyboardType: TextInputType.multiline,
                    textInputAction: TextInputAction.newline,
                    maxLines: null,
                    minLines: 3,
                    hint: texts.t('support.tickets.placeholders.reply'),
                  ),
                  const SizedBox(height: 12),
                  ref.watch(ticketOptionsProvider).maybeWhen(
                        data: (opts) => AttachmentPicker(
                          files: _files,
                          onChanged: (files) => setState(() => _files = files),
                          maxFiles: opts.maxFiles,
                          maxFileKb: opts.maxFileKb,
                          fileTypes: opts.fileTypes,
                          error: _filesError,
                        ),
                        orElse: () => const SizedBox.shrink(),
                      ),
                  const SizedBox(height: 12),
                  AppButton(key: const ValueKey('ticket-reply-send'), label: texts.t('support.tickets.send_reply'), loading: _busy, onPressed: _send),
                ] else
                  Container(
                    key: const ValueKey('ticket-closed-note'),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(color: colors.infoBg, borderRadius: BorderRadius.circular(AppRadius.card)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(texts.t('support.tickets.closed_note'), style: text.bodyMedium?.copyWith(color: colors.ink)),
                        const SizedBox(height: 8),
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: AppLink(label: texts.t('support.tickets.new'), onTap: () => context.pushReplacement(Routes.newTicket)),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        AsyncError(:final error) => AsyncStateView.error(error: error, onRetry: () => ref.invalidate(ticketProvider(widget.id))),
        _ => const ListSkeleton(),
      },
    );
  }
}

class _MessageBubble extends ConsumerWidget {
  const _MessageBubble({required this.message});

  final TicketMessage message;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final locale = ref.watch(localeCodeProvider);
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final mine = message.fromCustomer;
    final open = ref.read(urlOpenerProvider);
    final images = message.attachments.where((file) => file.isImage).toList();
    final others = message.attachments.where((file) => !file.isImage).toList();

    return Align(
      alignment: mine ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
      child: Container(
        key: ValueKey('ticket-message-${message.id}'),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        constraints: const BoxConstraints(maxWidth: 300),
        decoration: BoxDecoration(
          color: mine ? colors.primarySubtle : colors.surface,
          border: Border.all(color: colors.border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              mine ? texts.t('support.tickets.you') : (message.authorName.isEmpty ? texts.t('support.tickets.store') : message.authorName),
              style: text.labelSmall,
            ),
            if (message.body.isNotEmpty) ...[const SizedBox(height: 4), Text(message.body, style: text.bodyLarge)],
            if (images.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  for (final file in images)
                    Semantics(
                      button: true,
                      label: file.name,
                      child: InkWell(
                        onTap: () => open(file.url),
                        child: SizedBox(width: 88, height: 88, child: NetImage(file.url, radius: 8)),
                      ),
                    ),
                ],
              ),
            ],
            for (final file in others)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: ActionChip(
                  key: ValueKey('ticket-file-${file.id}'),
                  avatar: const Icon(Icons.attach_file, size: 16),
                  label: Text(file.sizeLabel.isEmpty ? file.name : '${file.name} · ${file.sizeLabel}', overflow: TextOverflow.ellipsis),
                  onPressed: () => open(file.url),
                ),
              ),
            const SizedBox(height: 4),
            Text(formatDate(message.createdAt, locale), style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400)),
          ],
        ),
      ),
    );
  }
}
