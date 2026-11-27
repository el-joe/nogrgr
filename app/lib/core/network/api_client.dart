import 'dart:async';

import 'package:dio/dio.dart';

import '../auth/token_storage.dart';
import 'api_exception.dart';

/// Per-request headers that change at runtime (language, display currency, shopping
/// country, app version). Read on every request so a settings change applies at once.
class RequestContext {
  const RequestContext({
    required this.tenantId,
    required this.locale,
    this.currency,
    this.country,
    this.platform,
    this.appVersion,
  });

  final String tenantId;
  final String locale;
  final String? currency;
  final String? country;
  final String? platform;
  final String? appVersion;

  Map<String, String> toHeaders() => {
        'Accept': 'application/json',
        'X-Tenant': tenantId,
        'X-Locale': locale,
        'Accept-Language': locale,
        if (currency != null && currency!.isNotEmpty) 'X-Currency': currency!,
        if (country != null && country!.isNotEmpty) 'X-Country': country!,
        if (platform != null && platform!.isNotEmpty) 'X-App-Platform': platform!,
        if (appVersion != null && appVersion!.isNotEmpty) 'X-App-Version': appVersion!,
      };
}

/// Successful envelope `{success: true, message, data}`.
class ApiResponse {
  const ApiResponse({required this.statusCode, this.message = '', this.data, this.headers = const {}});

  final int statusCode;
  final String message;
  final dynamic data;
  final Map<String, String> headers;

  bool get notModified => statusCode == 304;

  Map<String, dynamic> get map => data is Map<String, dynamic> ? data as Map<String, dynamic> : const {};
}

/// The two APIs of the store (Mobile API v1 + Store API v1) behind one Dio:
/// tenant / locale / currency headers, bearer token, one automatic refresh on 401
/// (single-flight, rotating pair), envelope + error mapping.
class ApiClient {
  ApiClient({
    required String mobileBaseUrl,
    required this.storeBaseUrl,
    required this.tokens,
    required this.context,
    this.onSessionExpired,
    Dio? dio,
    Dio? refreshDio,
  })  : _dio = dio ?? Dio(),
        _refreshDio = refreshDio ?? dio ?? Dio() {
    _dio.options
      ..baseUrl = mobileBaseUrl.endsWith('/') ? mobileBaseUrl : '$mobileBaseUrl/'
      ..connectTimeout = const Duration(seconds: 15)
      ..receiveTimeout = const Duration(seconds: 30)
      ..responseType = ResponseType.json
      ..validateStatus = (status) => status != null && (status < 300 || status == 304);
    _dio.interceptors.add(InterceptorsWrapper(onRequest: _onRequest, onError: _onError));
  }

  final Dio _dio;
  final Dio _refreshDio;
  final String storeBaseUrl;
  final TokenStorage tokens;
  final RequestContext Function() context;

  /// Called when the refresh token is rejected (the pair is already cleared).
  final void Function()? onSessionExpired;

  Future<TokenPair?>? _refreshing;

  static const _skipAuth = 'nogrgr.skipAuth';
  static const _retried = 'nogrgr.retried';

  /// Full URL of a Store API v1 path (cart, checkout, orders …).
  String storeUrl(String path) => '${storeBaseUrl.endsWith('/') ? storeBaseUrl : '$storeBaseUrl/'}$path';

  Future<ApiResponse> get(String path, {Map<String, dynamic>? query, Map<String, String>? headers, bool auth = true}) =>
      _send('GET', path, query: query, headers: headers, auth: auth);

  Future<ApiResponse> post(String path, {Object? body, Map<String, String>? headers, bool auth = true}) =>
      _send('POST', path, body: body, headers: headers, auth: auth);

  Future<ApiResponse> put(String path, {Object? body, Map<String, String>? headers, bool auth = true}) =>
      _send('PUT', path, body: body, headers: headers, auth: auth);

  Future<ApiResponse> patch(String path, {Object? body, Map<String, String>? headers, bool auth = true}) =>
      _send('PATCH', path, body: body, headers: headers, auth: auth);

  Future<ApiResponse> delete(String path, {Object? body, Map<String, String>? headers, bool auth = true}) =>
      _send('DELETE', path, body: body, headers: headers, auth: auth);

  Future<ApiResponse> _send(
    String method,
    String path, {
    Map<String, dynamic>? query,
    Object? body,
    Map<String, String>? headers,
    bool auth = true,
  }) async {
    try {
      final response = await _dio.request<dynamic>(
        path,
        data: body,
        queryParameters: query,
        options: Options(method: method, headers: headers, extra: {_skipAuth: !auth}),
      );

      return _envelope(response);
    } on DioException catch (error) {
      throw mapDioError(error);
    }
  }

  ApiResponse _envelope(Response<dynamic> response) {
    final headers = <String, String>{};
    response.headers.forEach((name, values) => headers[name.toLowerCase()] = values.join(','));
    final body = response.data;

    if (body is Map<String, dynamic>) {
      return ApiResponse(
        statusCode: response.statusCode ?? 200,
        message: '${body['message'] ?? ''}',
        data: body['data'],
        headers: headers,
      );
    }

    return ApiResponse(statusCode: response.statusCode ?? 200, headers: headers);
  }

  Future<void> _onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    options.headers.addAll(context().toHeaders());

    if (options.extra[_skipAuth] != true) {
      final pair = await tokens.read();

      if (pair != null) {
        options.headers['Authorization'] = 'Bearer ${pair.accessToken}';
      }
    }

    handler.next(options);
  }

  Future<void> _onError(DioException error, ErrorInterceptorHandler handler) async {
    final request = error.requestOptions;
    final sentToken = request.headers['Authorization'] != null;

    if (error.response?.statusCode != 401 || !sentToken || request.extra[_retried] == true) {
      return handler.next(error);
    }

    final fresh = await refreshTokens();

    if (fresh == null) {
      return handler.next(error);
    }

    try {
      request.extra[_retried] = true;
      request.headers['Authorization'] = 'Bearer ${fresh.accessToken}';
      handler.resolve(await _dio.fetch<dynamic>(request));
    } on DioException catch (retryError) {
      handler.next(retryError);
    }
  }

  /// Rotates the token pair once even when several calls hit 401 together. Returns the
  /// new pair, or null when the refresh token is gone / rejected (session cleared).
  Future<TokenPair?> refreshTokens() {
    return _refreshing ??= _doRefresh().whenComplete(() => _refreshing = null);
  }

  Future<TokenPair?> _doRefresh() async {
    final current = await tokens.read();

    if (current == null) {
      return null;
    }

    if (current.refreshExpired) {
      await _expire();

      return null;
    }

    try {
      final response = await _refreshDio.post<dynamic>(
        '${_dio.options.baseUrl}auth/refresh',
        data: {'refresh_token': current.refreshToken},
        options: Options(headers: context().toHeaders(), extra: {_skipAuth: true}),
      );
      final data = (response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>;
      final pair = TokenPair.fromJson(data['tokens'] as Map<String, dynamic>);
      await tokens.write(pair);

      return pair;
    } on DioException catch (error) {
      final status = error.response?.statusCode;

      // A network failure keeps the pair: the next call tries again.
      if (status == 401 || status == 403 || status == 422) {
        await _expire();
      }

      return null;
    } on TypeError {
      // Unexpected body: keep the pair, the caller sees the original 401.
      return null;
    }
  }

  Future<void> _expire() async {
    await tokens.clear();
    onSessionExpired?.call();
  }

  /// Transport / HTTP error → [ApiException] (envelope message, 422 errors, Retry-After).
  static ApiException mapDioError(DioException error) {
    final response = error.response;

    if (response == null) {
      return const ApiException(kind: ApiErrorKind.network);
    }

    final status = response.statusCode ?? 0;
    final body = response.data;
    final message = body is Map && body['message'] != null ? '${body['message']}' : '';
    final errors = <String, List<String>>{};

    if (body is Map && body['errors'] is Map) {
      (body['errors'] as Map).forEach((field, messages) {
        errors['$field'] = messages is List ? messages.map((m) => '$m').toList() : ['$messages'];
      });
    }

    final kind = switch (status) {
      401 => ApiErrorKind.unauthorized,
      403 => ApiErrorKind.forbidden,
      404 => ApiErrorKind.notFound,
      422 => ApiErrorKind.validation,
      429 => ApiErrorKind.throttled,
      >= 500 => ApiErrorKind.server,
      _ => ApiErrorKind.unknown,
    };
    final retryAfter = int.tryParse(response.headers.value('retry-after') ?? '');

    return ApiException(
      kind: kind,
      message: message,
      statusCode: status,
      errors: errors,
      retryAfter: retryAfter == null ? null : Duration(seconds: retryAfter),
    );
  }
}
