import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/json.dart';
import '../../../core/models/product_card.dart';
import '../../../core/network/store_api.dart';
import '../../../core/providers.dart';
import '../../auth/data/session_controller.dart';

class ReturnRow {
  const ReturnRow({
    required this.id,
    required this.reference,
    required this.orderUuid,
    required this.orderNumber,
    required this.productName,
    required this.quantity,
    required this.typeLabel,
    required this.reasonName,
    required this.status,
    required this.statusLabel,
    required this.step,
    required this.refundAmount,
    required this.canWithdraw,
    required this.canReply,
    this.variantLabel,
    this.lastMessage,
    this.createdAt,
    this.description,
    this.amounts = const {},
    this.exchangeTracking,
    this.timeline = const [],
    this.media = const [],
  });

  factory ReturnRow.fromJson(Map<String, dynamic> json) => ReturnRow(
        id: jsonInt(json['id']),
        reference: '${json['reference'] ?? ''}',
        orderUuid: '${json['order_uuid'] ?? ''}',
        orderNumber: '${json['order_number'] ?? ''}',
        productName: '${json['product_name'] ?? ''}',
        variantLabel: jsonString(json['variant_label']),
        quantity: jsonInt(json['quantity'], 1),
        typeLabel: '${json['type_label'] ?? ''}',
        reasonName: '${jsonMap(json['reason'])['name'] ?? ''}',
        status: '${json['status'] ?? ''}',
        statusLabel: '${json['status_label'] ?? ''}',
        step: jsonInt(json['step']),
        refundAmount: Money.fromJson(json['refund_amount']),
        lastMessage: jsonString(json['last_message']),
        canWithdraw: json['can_withdraw'] == true,
        canReply: json['can_reply'] == true,
        createdAt: jsonDate(json['created_at']),
        description: jsonString(json['description']),
        amounts: {for (final e in jsonMap(json['amounts']).entries) e.key: Money.fromJson(e.value)},
        exchangeTracking: jsonString(json['exchange_tracking_number']),
        timeline: [
          for (final t in jsonList(json['timeline']))
            (author: '${t['author'] ?? 'store'}', status: jsonString(t['status_label']), message: jsonString(t['message']), at: jsonDate(t['created_at'])),
        ],
        media: [for (final m in jsonList(json['media'])) (name: '${m['name'] ?? ''}', url: '${m['url'] ?? ''}', kind: '${m['kind'] ?? ''}')],
      );

  final int id;
  final String reference;
  final String orderUuid;
  final String orderNumber;
  final String productName;
  final String? variantLabel;
  final int quantity;
  final String typeLabel;
  final String reasonName;
  final String status;
  final String statusLabel;
  final int step;
  final Money refundAmount;
  final String? lastMessage;
  final bool canWithdraw;
  final bool canReply;
  final DateTime? createdAt;
  final String? description;
  final Map<String, Money> amounts;
  final String? exchangeTracking;
  final List<({String author, String? status, String? message, DateTime? at})> timeline;
  final List<({String name, String url, String kind})> media;
}

class ReturnLine {
  const ReturnLine({required this.orderItemId, required this.name, required this.available, required this.returnable, this.variant, this.image, required this.total});

  final int orderItemId;
  final String name;
  final String? variant;
  final String? image;
  final int available;
  final bool returnable;
  final Money total;
}

class ReturnReasonOption {
  const ReturnReasonOption({required this.code, required this.name, required this.requiresVideo});

  final String code;
  final String name;
  final bool requiresVideo;
}

class ReturnOptions {
  const ReturnOptions({
    required this.canReturn,
    required this.lines,
    required this.reasons,
    required this.allowExchanges,
    this.blockedMessage,
    this.windowDays,
    this.deadline,
    this.payerLabel,
    this.policyText,
  });

  factory ReturnOptions.fromJson(Map<String, dynamic> json) {
    final policy = jsonMap(json['policy']);

    return ReturnOptions(
      canReturn: json['can_return'] == true,
      blockedMessage: jsonString(json['blocked_message']),
      lines: [
        for (final l in jsonList(json['lines']))
          ReturnLine(
            orderItemId: jsonInt(l['order_item_id']),
            name: '${l['name'] ?? ''}',
            variant: jsonString(l['variant_label']),
            image: jsonString(l['image_url']),
            available: jsonInt(l['available']),
            returnable: l['returnable'] == true,
            total: Money.fromJson(l['total']),
          ),
      ],
      reasons: [for (final r in jsonList(json['reasons'])) ReturnReasonOption(code: '${r['code']}', name: '${r['name']}', requiresVideo: r['requires_video'] == true)],
      allowExchanges: policy['allow_exchanges'] == true,
      windowDays: jsonIntOrNull(policy['window_days']),
      deadline: jsonDate(policy['deadline']),
      payerLabel: jsonString(policy['return_shipping_payer_label']),
      policyText: jsonString(policy['text']),
    );
  }

  final bool canReturn;
  final String? blockedMessage;
  final List<ReturnLine> lines;
  final List<ReturnReasonOption> reasons;
  final bool allowExchanges;
  final int? windowDays;
  final DateTime? deadline;
  final String? payerLabel;
  final String? policyText;
}

/// Customer returns of the Store API (APP-016 … 018).
class ReturnsRepository {
  ReturnsRepository(this._api);

  final StoreApi _api;

  Future<(List<ReturnRow>, PageMeta)> page(int page) async {
    final data = (await _api.get('returns', query: {'page': page, 'per_page': 20})).map;

    return (jsonList(data['returns']).map(ReturnRow.fromJson).toList(), PageMeta.fromJson(data['meta']));
  }

  Future<ReturnRow> show(int id) async => ReturnRow.fromJson((await _api.get('returns/$id')).map);

  Future<ReturnOptions> options(String orderUuid) async => ReturnOptions.fromJson((await _api.get('orders/$orderUuid/returns/options')).map);

  Future<String> create(
    String orderUuid, {
    required String reasonCode,
    required String description,
    required String type,
    required Map<int, int> items,
    List<String> photos = const [],
    String? video,
  }) async {
    final form = FormData();
    form.fields
      ..add(MapEntry('reason_code', reasonCode))
      ..add(MapEntry('description', description))
      ..add(MapEntry('type', type));
    var i = 0;

    items.forEach((id, quantity) {
      form.fields
        ..add(MapEntry('items[$i][order_item_id]', '$id'))
        ..add(MapEntry('items[$i][quantity]', '$quantity'));
      i++;
    });

    for (final photo in photos) {
      form.files.add(MapEntry('photos[]', await MultipartFile.fromFile(photo)));
    }

    if (video != null) {
      form.files.add(MapEntry('video', await MultipartFile.fromFile(video)));
    }

    return (await _api.post('orders/$orderUuid/returns', body: form)).message;
  }

  Future<ReturnRow> reply(int id, String message) async => ReturnRow.fromJson((await _api.post('returns/$id/messages', body: FormData.fromMap({'message': message}))).map);

  Future<ReturnRow> withdraw(int id) async => ReturnRow.fromJson((await _api.post('returns/$id/withdraw')).map);
}

final returnsRepositoryProvider = Provider<ReturnsRepository>((ref) => ReturnsRepository(ref.watch(storeApiProvider)));

class ReturnsState {
  const ReturnsState({this.items = const [], this.page = 0, this.hasMore = false});

  final List<ReturnRow> items;
  final int page;
  final bool hasMore;
}

class ReturnsController extends AsyncNotifier<ReturnsState> {
  @override
  Future<ReturnsState> build() async {
    if (!ref.watch(sessionProvider.select((s) => s.signedIn))) {
      return const ReturnsState();
    }

    ref.watch(localeCodeProvider);
    final (items, meta) = await ref.read(returnsRepositoryProvider).page(1);

    return ReturnsState(items: items, page: 1, hasMore: meta.hasMore);
  }

  Future<void> loadMore() async {
    final current = state.value;

    if (current == null || !current.hasMore) {
      return;
    }

    final (items, meta) = await ref.read(returnsRepositoryProvider).page(current.page + 1);
    state = AsyncData(ReturnsState(items: [...current.items, ...items], page: current.page + 1, hasMore: meta.hasMore));
  }
}

final returnsProvider = AsyncNotifierProvider<ReturnsController, ReturnsState>(ReturnsController.new);

final returnProvider = FutureProvider.autoDispose.family<ReturnRow, int>((ref, id) => ref.watch(returnsRepositoryProvider).show(id));

final returnOptionsProvider = FutureProvider.autoDispose.family<ReturnOptions, String>((ref, uuid) => ref.watch(returnsRepositoryProvider).options(uuid));
