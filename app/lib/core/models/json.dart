/// Lenient readers for API JSON (fields may be missing or null on older servers).
Map<String, dynamic> jsonMap(Object? value) => value is Map<String, dynamic> ? value : const {};

List<Map<String, dynamic>> jsonList(Object? value) =>
    value is List ? value.whereType<Map<String, dynamic>>().toList() : const [];

int jsonInt(Object? value, [int fallback = 0]) => switch (value) {
      int v => v,
      num v => v.toInt(),
      String v => int.tryParse(v) ?? fallback,
      _ => fallback,
    };

int? jsonIntOrNull(Object? value) => value == null ? null : jsonInt(value);

double jsonDouble(Object? value) => switch (value) {
      num v => v.toDouble(),
      String v => double.tryParse(v) ?? 0,
      _ => 0,
    };

String? jsonString(Object? value) => value == null || '$value'.isEmpty ? null : '$value';

DateTime? jsonDate(Object? value) => value is String ? DateTime.tryParse(value)?.toLocal() : null;

/// `meta {current_page, last_page, total}` of a paged list.
class PageMeta {
  const PageMeta({this.currentPage = 1, this.lastPage = 1, this.total = 0});

  factory PageMeta.fromJson(Object? json) {
    final map = jsonMap(json);

    return PageMeta(
      currentPage: jsonInt(map['current_page'], 1),
      lastPage: jsonInt(map['last_page'], 1),
      total: jsonInt(map['total']),
    );
  }

  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}
