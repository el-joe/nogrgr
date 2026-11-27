import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/json.dart';
import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../auth/data/session_controller.dart';

class InboxItem {
  const InboxItem({required this.id, required this.title, required this.body, required this.read, this.event, this.orderUuid, this.ticketId, this.createdAt});

  factory InboxItem.fromJson(Map<String, dynamic> json) => InboxItem(
        id: '${json['id']}',
        title: '${json['title'] ?? ''}',
        body: '${json['body'] ?? ''}',
        read: json['read'] == true,
        event: jsonString(json['event']),
        orderUuid: jsonString(json['order_uuid']),
        ticketId: jsonIntOrNull(json['ticket_id']),
        createdAt: jsonDate(json['created_at']),
      );

  final String id;
  final String title;
  final String body;
  final bool read;
  final String? event;
  final String? orderUuid;
  final int? ticketId;
  final DateTime? createdAt;

  InboxItem markRead() => InboxItem(id: id, title: title, body: body, read: true, event: event, orderUuid: orderUuid, ticketId: ticketId, createdAt: createdAt);
}

class InboxPage {
  const InboxPage({required this.items, required this.unread, required this.meta});

  final List<InboxItem> items;
  final int unread;
  final PageMeta meta;
}

/// Customer inbox (`/api/mobile/v1/notifications`, APP-030).
class NotificationsRepository {
  NotificationsRepository(this._api);

  final ApiClient _api;

  Future<InboxPage> page(int page) async {
    final data = (await _api.get('notifications', query: {'page': page, 'per_page': 20})).map;

    return InboxPage(items: jsonList(data['items']).map(InboxItem.fromJson).toList(), unread: jsonInt(data['unread_count']), meta: PageMeta.fromJson(data['meta']));
  }

  Future<int> unreadCount() async => jsonInt((await _api.get('notifications/unread-count')).map['unread_count']);

  Future<void> markRead(String id) => _api.post('notifications/$id/read');

  Future<void> markAllRead() => _api.post('notifications/read-all');

  Future<void> delete(String id) => _api.delete('notifications/$id');
}

final notificationsRepositoryProvider = Provider<NotificationsRepository>((ref) => NotificationsRepository(ref.watch(apiClientProvider)));

/// Bell badge on the home header (0 for guests).
final unreadCountProvider = FutureProvider<int>((ref) async {
  if (!ref.watch(sessionProvider.select((s) => s.signedIn))) {
    return 0;
  }

  try {
    return await ref.watch(notificationsRepositoryProvider).unreadCount();
  } catch (_) {
    return 0;
  }
});

class InboxState {
  const InboxState({this.items = const [], this.unread = 0, this.page = 0, this.hasMore = false});

  final List<InboxItem> items;
  final int unread;
  final int page;
  final bool hasMore;
}

class InboxController extends AsyncNotifier<InboxState> {
  NotificationsRepository get _repo => ref.read(notificationsRepositoryProvider);

  @override
  Future<InboxState> build() async {
    if (!ref.watch(sessionProvider.select((s) => s.signedIn))) {
      return const InboxState();
    }

    final first = await _repo.page(1);

    return InboxState(items: first.items, unread: first.unread, page: 1, hasMore: first.meta.hasMore);
  }

  Future<void> loadMore() async {
    final current = state.value;

    if (current == null || !current.hasMore) {
      return;
    }

    final next = await _repo.page(current.page + 1);
    state = AsyncData(InboxState(items: [...current.items, ...next.items], unread: next.unread, page: current.page + 1, hasMore: next.meta.hasMore));
  }

  Future<void> open(InboxItem item) async {
    final current = state.value;

    if (current == null || item.read) {
      return;
    }

    state = AsyncData(InboxState(
      items: [for (final i in current.items) i.id == item.id ? i.markRead() : i],
      unread: (current.unread - 1).clamp(0, 1 << 30),
      page: current.page,
      hasMore: current.hasMore,
    ));
    await _repo.markRead(item.id);
    ref.invalidate(unreadCountProvider);
  }

  Future<void> readAll() async {
    final current = state.value;

    if (current == null) {
      return;
    }

    await _repo.markAllRead();
    state = AsyncData(InboxState(items: [for (final i in current.items) i.markRead()], page: current.page, hasMore: current.hasMore));
    ref.invalidate(unreadCountProvider);
  }

  Future<void> remove(InboxItem item) async {
    final current = state.value;

    if (current == null) {
      return;
    }

    state = AsyncData(InboxState(
      items: current.items.where((i) => i.id != item.id).toList(),
      unread: item.read ? current.unread : (current.unread - 1).clamp(0, 1 << 30),
      page: current.page,
      hasMore: current.hasMore,
    ));

    try {
      await _repo.delete(item.id);
    } catch (_) {
      state = AsyncData(current);
      rethrow;
    }

    ref.invalidate(unreadCountProvider);
  }
}

final inboxProvider = AsyncNotifierProvider<InboxController, InboxState>(InboxController.new);
