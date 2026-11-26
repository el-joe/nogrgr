import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// Recorded request of the fake transport.
class SentRequest {
  SentRequest(this.method, this.path, this.headers, this.body, this.query);

  final String method;
  final String path;
  final Map<String, dynamic> headers;
  final dynamic body;
  final Map<String, dynamic> query;
}

class FakeReply {
  const FakeReply(this.status, [this.body, this.headers = const {}]);

  /// `{success: true, message, data}`.
  factory FakeReply.ok(Object? data, {String message = '', Map<String, String> headers = const {}}) =>
      FakeReply(200, {'success': true, 'message': message, 'data': data}, headers);

  factory FakeReply.error(int status, String message) => FakeReply(status, {'success': false, 'message': message});

  factory FakeReply.validation(Map<String, List<String>> errors) =>
      FakeReply(422, {'message': errors.values.first.first, 'errors': errors});

  final int status;
  final Object? body;
  final Map<String, String> headers;
}

/// Dio transport that answers from a handler (no network) and records every request.
/// Throwing [DioException] with type connectionError simulates being offline.
class FakeAdapter implements HttpClientAdapter {
  FakeAdapter(this.handler);

  Future<FakeReply> Function(SentRequest request) handler;
  final List<SentRequest> requests = [];
  bool offline = false;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final path = options.uri.path.replaceFirst(RegExp(r'^.*/api/mobile/v1/'), '').replaceFirst(RegExp(r'^.*/store-api/v1/'), 'store/');
    final request = SentRequest(options.method, path, Map.of(options.headers), options.data, Map.of(options.queryParameters));
    requests.add(request);

    if (offline) {
      throw DioException.connectionError(requestOptions: options, reason: 'offline');
    }

    final reply = await handler(request);
    final bytes = reply.body == null ? Uint8List(0) : Uint8List.fromList(utf8.encode(jsonEncode(reply.body)));

    return ResponseBody.fromBytes(
      bytes,
      reply.status,
      headers: {
        'content-type': ['application/json'],
        for (final entry in reply.headers.entries) entry.key.toLowerCase(): [entry.value],
      },
    );
  }

  @override
  void close({bool force = false}) {}

  List<SentRequest> to(String path) => requests.where((r) => r.path == path).toList();
}

Dio fakeDio(FakeAdapter adapter) => Dio()..httpClientAdapter = adapter;
