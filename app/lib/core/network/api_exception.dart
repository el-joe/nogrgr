/// One failed API call, mapped from the server envelope
/// (`{success: false, message}` / 422 `{message, errors}`) or from the transport.
enum ApiErrorKind {
  /// No connection, DNS failure or timeout: show the offline state.
  network,
  unauthorized,
  forbidden,
  notFound,
  validation,
  throttled,
  server,
  unknown,
}

class ApiException implements Exception {
  const ApiException({
    required this.kind,
    this.message = '',
    this.statusCode,
    this.errors = const {},
    this.retryAfter,
  });

  final ApiErrorKind kind;

  /// Server message (already translated by the API in `X-Locale`), may be empty.
  final String message;
  final int? statusCode;

  /// 422 field errors: field → messages.
  final Map<String, List<String>> errors;

  /// 429 `Retry-After`.
  final Duration? retryAfter;

  bool get isNetwork => kind == ApiErrorKind.network;

  /// First message of a field, for inline form errors.
  String? fieldError(String field) {
    final messages = errors[field];

    return messages == null || messages.isEmpty ? null : messages.first;
  }

  @override
  String toString() => 'ApiException($kind, $statusCode, $message)';
}
