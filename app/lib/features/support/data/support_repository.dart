import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/json.dart';
import '../../../core/network/store_api.dart';
import '../../../core/providers.dart';
import '../../auth/data/session_controller.dart';

class LabelledValue {
  const LabelledValue({required this.value, required this.label});

  factory LabelledValue.fromJson(Object? json) {
    final map = jsonMap(json);

    return LabelledValue(value: '${map['value'] ?? ''}', label: '${map['label'] ?? map['value'] ?? ''}');
  }

  final String value;
  final String label;
}

class TicketStatus {
  const TicketStatus({required this.value, required this.label, required this.tone});

  factory TicketStatus.fromJson(Object? json) {
    final map = jsonMap(json);

    return TicketStatus(value: '${map['value'] ?? ''}', label: '${map['label'] ?? ''}', tone: '${map['tone'] ?? 'info'}');
  }

  final String value;
  final String label;

  /// info / warning / brand / success / neutral.
  final String tone;

  bool get closed => value == 'closed';
}

class TicketOrder {
  const TicketOrder({required this.uuid, required this.number});

  factory TicketOrder.fromJson(Map<String, dynamic> json) => TicketOrder(uuid: '${json['uuid']}', number: '${json['number'] ?? ''}');

  final String uuid;
  final String number;
}

class TicketAttachment {
  const TicketAttachment({required this.id, required this.name, required this.url, this.mimeType, this.sizeLabel = '', this.isImage = false});

  factory TicketAttachment.fromJson(Map<String, dynamic> json) => TicketAttachment(
        id: jsonInt(json['id']),
        name: '${json['name'] ?? ''}',
        url: '${json['url'] ?? ''}',
        mimeType: jsonString(json['mime_type']),
        sizeLabel: '${json['size_label'] ?? ''}',
        isImage: json['is_image'] == true,
      );

  final int id;
  final String name;

  /// 30-minute signed link.
  final String url;
  final String? mimeType;
  final String sizeLabel;
  final bool isImage;
}

class TicketMessage {
  const TicketMessage({required this.id, required this.fromCustomer, required this.authorName, required this.body, this.createdAt, this.attachments = const []});

  factory TicketMessage.fromJson(Map<String, dynamic> json) => TicketMessage(
        id: jsonInt(json['id']),
        fromCustomer: json['author'] == 'customer',
        authorName: '${json['author_name'] ?? ''}',
        body: '${json['body'] ?? ''}',
        createdAt: jsonDate(json['created_at']),
        attachments: jsonList(json['attachments']).map(TicketAttachment.fromJson).toList(),
      );

  final int id;
  final bool fromCustomer;
  final String authorName;
  final String body;
  final DateTime? createdAt;
  final List<TicketAttachment> attachments;
}

/// Customer ↔ store ticket (decision #108). Rows have no [messages]; the detail does.
class Ticket {
  const Ticket({
    required this.id,
    required this.reference,
    required this.subject,
    required this.topic,
    required this.status,
    this.order,
    this.unread = false,
    this.lastReplyByStore = false,
    this.lastReplyAt,
    this.excerpt,
    this.canReply = false,
    this.canClose = false,
    this.createdAt,
    this.messages = const [],
  });

  factory Ticket.fromJson(Map<String, dynamic> json) {
    final unread = json['unread'];

    return Ticket(
      id: jsonInt(json['id']),
      reference: '${json['reference'] ?? ''}',
      subject: '${json['subject'] ?? ''}',
      topic: LabelledValue.fromJson(json['topic']),
      status: TicketStatus.fromJson(json['status']),
      order: json['order'] is Map<String, dynamic> ? TicketOrder.fromJson(json['order'] as Map<String, dynamic>) : null,
      unread: unread == true || jsonInt(unread) > 0,
      lastReplyByStore: json['last_reply_by'] == 'store',
      lastReplyAt: jsonDate(json['last_reply_at']),
      excerpt: jsonString(json['excerpt']),
      canReply: json['can_reply'] == true,
      canClose: json['can_close'] == true,
      createdAt: jsonDate(json['created_at']),
      messages: jsonList(json['messages']).map(TicketMessage.fromJson).toList(),
    );
  }

  final int id;
  final String reference;
  final String subject;
  final LabelledValue topic;
  final TicketStatus status;
  final TicketOrder? order;
  final bool unread;
  final bool lastReplyByStore;
  final DateTime? lastReplyAt;
  final String? excerpt;
  final bool canReply;
  final bool canClose;
  final DateTime? createdAt;
  final List<TicketMessage> messages;
}

/// `support/tickets/options`: topics, the customer's recent orders, file limits.
class TicketOptions {
  const TicketOptions({required this.topics, required this.orders, this.maxFiles = 5, this.maxFileKb = 10240, this.fileTypes = const []});

  factory TicketOptions.fromJson(Map<String, dynamic> json) => TicketOptions(
        topics: jsonList(json['topics']).map(LabelledValue.fromJson).toList(),
        orders: jsonList(json['orders']).map(TicketOrder.fromJson).toList(),
        maxFiles: jsonInt(json['max_files'], 5),
        maxFileKb: jsonInt(json['max_file_kb'], 10240),
        fileTypes: [for (final type in (json['file_types'] as List?) ?? const []) '$type'],
      );

  final List<LabelledValue> topics;
  final List<TicketOrder> orders;
  final int maxFiles;
  final int maxFileKb;

  /// Allowed extensions (jpg, png, pdf …).
  final List<String> fileTypes;
}

/// A file chosen on the device, not yet sent.
class LocalFile {
  const LocalFile({required this.path, required this.name, required this.size});

  final String path;
  final String name;
  final int size;
}

class TicketsPage {
  const TicketsPage({required this.items, required this.meta});

  final List<Ticket> items;
  final PageMeta meta;
}

/// Store API customer support tickets (`/store-api/v1/support/tickets`).
class SupportRepository {
  SupportRepository(this._api);

  final StoreApi _api;

  Future<TicketOptions> options() async => TicketOptions.fromJson((await _api.get('support/tickets/options')).map);

  /// [group] = `active` | `closed`.
  Future<TicketsPage> page(String group, int page) async {
    final data = (await _api.get('support/tickets', query: {'status': group, 'page': page, 'per_page': 20})).map;

    return TicketsPage(items: jsonList(data['items']).map(Ticket.fromJson).toList(), meta: PageMeta.fromJson(data['meta']));
  }

  /// Detail (marks the store's replies read).
  Future<Ticket> show(int id) async => Ticket.fromJson(jsonMap((await _api.get('support/tickets/$id')).map['ticket']));

  Future<({Ticket ticket, String message})> open({
    required String topic,
    required String subject,
    required String body,
    String? orderUuid,
    List<LocalFile> files = const [],
  }) async {
    final form = FormData.fromMap({'topic': topic, 'subject': subject, 'body': body, 'order_uuid': ?orderUuid});
    _attach(form, files);
    final response = await _api.post('support/tickets', body: form);

    return (ticket: Ticket.fromJson(jsonMap(response.map['ticket'])), message: response.message);
  }

  Future<String> reply(int id, {String? body, List<LocalFile> files = const []}) async {
    final form = FormData.fromMap({if (body != null && body.isNotEmpty) 'body': body});
    _attach(form, files);

    return (await _api.post('support/tickets/$id/messages', body: form)).message;
  }

  Future<String> close(int id) async => (await _api.post('support/tickets/$id/close')).message;

  void _attach(FormData form, List<LocalFile> files) {
    for (final file in files) {
      form.files.add(MapEntry('files[]', MultipartFile.fromFileSync(file.path, filename: file.name)));
    }
  }
}

final supportRepositoryProvider = Provider<SupportRepository>((ref) => SupportRepository(ref.watch(storeApiProvider)));

final ticketOptionsProvider = FutureProvider.autoDispose<TicketOptions>((ref) {
  ref.watch(localeCodeProvider);

  return ref.watch(supportRepositoryProvider).options();
});

final ticketProvider = FutureProvider.autoDispose.family<Ticket, int>((ref, id) {
  ref.watch(localeCodeProvider);

  return ref.watch(supportRepositoryProvider).show(id);
});

class TicketsState {
  const TicketsState({this.items = const [], this.page = 0, this.total = 0, this.hasMore = false, this.loadingMore = false});

  final List<Ticket> items;
  final int page;
  final int total;
  final bool hasMore;
  final bool loadingMore;
}

/// One tab of the ticket list (`active` / `closed`).
class TicketsController extends AsyncNotifier<TicketsState> {
  TicketsController(this.group);

  final String group;

  SupportRepository get _repo => ref.read(supportRepositoryProvider);

  @override
  Future<TicketsState> build() async {
    if (!ref.watch(sessionProvider.select((s) => s.signedIn))) {
      return const TicketsState();
    }

    ref.watch(localeCodeProvider);
    final first = await _repo.page(group, 1);

    return TicketsState(items: first.items, page: 1, total: first.meta.total, hasMore: first.meta.hasMore);
  }

  Future<void> loadMore() async {
    final current = state.value;

    if (current == null || !current.hasMore || current.loadingMore) {
      return;
    }

    state = AsyncData(TicketsState(items: current.items, page: current.page, total: current.total, hasMore: true, loadingMore: true));

    try {
      final next = await _repo.page(group, current.page + 1);
      state = AsyncData(TicketsState(items: [...current.items, ...next.items], page: current.page + 1, total: next.meta.total, hasMore: next.meta.hasMore));
    } catch (_) {
      state = AsyncData(current);
    }
  }
}

final ticketsProvider = AsyncNotifierProvider.family<TicketsController, TicketsState, String>(TicketsController.new);

